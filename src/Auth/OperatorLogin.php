<?php

declare(strict_types=1);

namespace Atlas\Auth;

use Atlas\Exception\ConfigException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sign-in and token helpers for a CLI or a CI job, over PSR-18, with no secret key: the operator device flow (RFC 8628),
 * token exchange for a short-lived resource-bound token (RFC 8693), `client_credentials` with a partner link, and
 * leaked-key revocation.
 *
 *     $login = new OperatorLogin();
 *     $start = $login->start();                       // show $start['verification_uri_complete'] and $start['user_code']
 *     $tokens = $login->poll($start);                 // blocks until approved (throws OAuthFlowException otherwise)
 *     $scoped = $login->exchange($tokens['access_token'], 'inst_123', ['users:read']);
 *     $partner = $login->partnerLinkToken($clientId, $clientSecret, 'users:read');
 */
final class OperatorLogin
{
    public const DEFAULT_API_URL = 'https://api.atlasauth.net';

    private readonly string $apiUrl;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requests;
    private readonly StreamFactoryInterface $streams;
    /** @var callable(int):void */
    private $sleep;

    /**
     * @param (callable(int):void)|null $sleep called with the milliseconds to wait between polls (tests pass a no-op)
     */
    public function __construct(
        string $apiUrl = self::DEFAULT_API_URL,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?callable $sleep = null,
    ) {
        $this->apiUrl = rtrim($apiUrl, '/');
        if ($httpClient === null || $requestFactory === null || $streamFactory === null) {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new ConfigException('Pass a PSR-18 client and PSR-17 factories, or install guzzlehttp/guzzle.');
            }
            $factory = new \GuzzleHttp\Psr7\HttpFactory();
            $httpClient ??= new \GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10, 'http_errors' => false]);
            $requestFactory ??= $factory;
            $streamFactory ??= $factory;
        }
        $this->client = $httpClient;
        $this->requests = $requestFactory;
        $this->streams = $streamFactory;
        $this->sleep = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /**
     * `GET /v1/operator` — where the device flow and token exchange live.
     *
     * @return array<string,mixed> `{client_id, scope, device_authorization_endpoint, token_endpoint, ..., audiences?: list<string> (other products that accept Atlas operators), access_token_claims?: list<string>}`
     */
    public function configuration(): array
    {
        $response = $this->client->sendRequest(
            $this->requests->createRequest('GET', $this->apiUrl . '/v1/operator')->withHeader('Accept', 'application/json'),
        );
        $json = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() !== 200 || !is_array($json)) {
            throw new OAuthFlowException('not_configured', 'The operator issuer is not configured on this deployment.', $response->getStatusCode());
        }

        return $json;
    }

    /**
     * Start the device flow.
     *
     * @return array<string,mixed> the device authorization (`device_code`, `user_code`, `verification_uri`,
     *   `verification_uri_complete`, `expires_in`, `interval`) plus `config`
     */
    public function start(?array $config = null): array
    {
        $config ??= $this->configuration();
        [$status, $out] = $this->form((string) $config['device_authorization_endpoint'], [
            'client_id' => (string) $config['client_id'],
            'scope' => (string) ($config['scope'] ?? ''),
        ]);
        if ($status !== 200) {
            throw new OAuthFlowException((string) ($out['error'] ?? 'error'), (string) ($out['error_description'] ?? 'Device authorization failed.'), $status);
        }

        return $out + ['config' => $config];
    }

    /**
     * Poll until the person approves (returns the tokens), denies or the code expires (throws). Honours `interval` and `slow_down`.
     *
     * @param array<string,mixed> $start the result of {@see start()}
     *
     * @return array<string,mixed> `{access_token, token_type, expires_in, ...}`
     */
    public function poll(array $start): array
    {
        $config = $start['config'];
        $interval = max(1, (int) ($start['interval'] ?? 5)) * 1000;
        $deadline = microtime(true) + (int) $start['expires_in'];
        while (microtime(true) < $deadline) {
            ($this->sleep)($interval);
            [$status, $out] = $this->form((string) $config['token_endpoint'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
                'device_code' => (string) $start['device_code'],
                'client_id' => (string) $config['client_id'],
            ]);
            if ($status === 200) {
                return $out;
            }
            $error = $out['error'] ?? null;
            if ($error === 'authorization_pending') {
                continue;
            }
            if ($error === 'slow_down') {
                $interval += 5000;
                continue;
            }
            throw new OAuthFlowException((string) ($error ?? 'error'), (string) ($out['error_description'] ?? 'Sign-in failed.'), $status);
        }
        throw new OAuthFlowException('expired_token', 'The sign-in code expired. Run the login again.', 400);
    }

    /**
     * RFC 8693: swap an operator token for a one-hour, resource-bound Atlas token.
     *
     * @param string|list<string> $scope
     *
     * @return array<string,mixed>
     */
    public function exchange(string $subjectToken, string $resource, string|array $scope): array
    {
        [$status, $out] = $this->form($this->apiUrl . '/v1/oauth/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
            'subject_token' => $subjectToken,
            'subject_token_type' => 'urn:ietf:params:oauth:token-type:access_token',
            'audience' => 'atlas',
            'resource' => $resource,
            'scope' => is_array($scope) ? implode(' ', $scope) : $scope,
        ]);
        if ($status !== 200) {
            $first = is_array($out['errors'] ?? null) ? ($out['errors'][0] ?? []) : [];
            throw new OAuthFlowException(
                (string) ($out['error'] ?? $first['code'] ?? 'error'),
                (string) ($out['error_description'] ?? $first['message'] ?? 'Token exchange failed.'),
                $status,
            );
        }

        return $out;
    }

    /**
     * `client_credentials` with a partner link's credentials (a partner product calling an instance).
     *
     * @return array<string,mixed>
     */
    public function partnerLinkToken(string $clientId, string $clientSecret, ?string $scope = null): array
    {
        $body = ['grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => $clientSecret];
        if ($scope !== null && $scope !== '') {
            $body['scope'] = $scope;
        }
        [$status, $out] = $this->form($this->apiUrl . '/v1/oauth/token', $body);
        if ($status !== 200) {
            throw new OAuthFlowException((string) ($out['error'] ?? 'error'), (string) ($out['error_description'] ?? 'Token request failed.'), $status);
        }

        return $out;
    }

    /**
     * `POST /v1/keys/revoke_leaked` — revoke leaked Atlas credentials (needs no key).
     *
     * @param list<string> $secrets
     *
     * @return array{results:list<array{index:int,kind:string,outcome:string}>}
     */
    public function revokeLeakedKeys(array $secrets, ?string $url = null): array
    {
        $payload = json_encode(['secrets' => $secrets] + ($url !== null ? ['url' => $url] : []), JSON_THROW_ON_ERROR);
        $request = $this->requests->createRequest('POST', $this->apiUrl . '/v1/keys/revoke_leaked')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streams->createStream($payload));
        $response = $this->client->sendRequest($request);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new OAuthFlowException('request_failed', 'revoke_leaked failed (' . $response->getStatusCode() . ').', $response->getStatusCode());
        }

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string,string> $fields
     *
     * @return array{0:int,1:array<string,mixed>}
     */
    private function form(string $url, array $fields): array
    {
        $request = $this->requests->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streams->createStream(http_build_query($fields, '', '&', PHP_QUERY_RFC3986)));
        $response = $this->client->sendRequest($request);
        $json = json_decode((string) $response->getBody(), true);

        return [$response->getStatusCode(), is_array($json) ? $json : []];
    }
}
