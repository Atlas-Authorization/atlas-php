<?php

declare(strict_types=1);

namespace Atlas\Auth;

use Atlas\Exception\ConfigException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * An RFC 8628 device authorization flow against any Atlas instance's OAuth server, for a CLI or TV app of your own. `issuer` is the
 * instance's issuer (its Frontend API host) and `clientId` an OAuth client with the
 * `urn:ietf:params:oauth:grant-type:device_code` grant. Show the person `verification_uri_complete` (or `verification_uri` + `user_code`),
 * then {@see poll()}.
 *
 *     $flow = new DeviceFlow('https://acme-fapi.atlasauth.net', $clientId);
 *     $start = $flow->start(scope: ['openid', 'profile']);
 *     echo "Open {$start['verification_uri_complete']}\n";
 *     $tokens = $flow->poll($start);
 */
final class DeviceFlow
{
    private readonly string $issuer;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requests;
    private readonly StreamFactoryInterface $streams;
    /** @var callable(int):void */
    private $sleep;

    /** @param (callable(int):void)|null $sleep called with the milliseconds to wait between polls (tests pass a no-op) */
    public function __construct(
        string $issuer,
        private readonly string $clientId,
        private readonly ?string $clientSecret = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?callable $sleep = null,
    ) {
        $this->issuer = rtrim($issuer, '/');
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
     * @param string|list<string>|null $scope
     *
     * @return array<string,mixed> `{device_code, user_code, verification_uri, verification_uri_complete?, expires_in, interval?}`
     *
     * @throws OAuthFlowException
     */
    public function start(string|array|null $scope = null, ?string $resource = null): array
    {
        [$status, $out] = $this->form($this->issuer . '/oauth2/device_authorization', array_filter([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => is_array($scope) ? implode(' ', $scope) : $scope,
            'resource' => $resource,
        ], static fn ($v): bool => $v !== null && $v !== ''));
        if ($status !== 200) {
            throw new OAuthFlowException((string) ($out['error'] ?? 'error'), (string) ($out['error_description'] ?? 'Device authorization failed.'), $status);
        }

        return $out;
    }

    /**
     * Poll until the person approves (returns the tokens), denies (`access_denied`) or the code expires (`expired_token`); both throw.
     * Honours `interval` and adds 5 s on each `slow_down`.
     *
     * @param array<string,mixed> $start the result of {@see start()}
     *
     * @return array<string,mixed>
     *
     * @throws OAuthFlowException
     */
    public function poll(array $start): array
    {
        $interval = max(1, (int) ($start['interval'] ?? 5)) * 1000;
        $deadline = microtime(true) + (int) $start['expires_in'];
        while (microtime(true) < $deadline) {
            ($this->sleep)($interval);
            [$status, $out] = $this->form($this->issuer . '/oauth2/token', array_filter([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
                'device_code' => (string) $start['device_code'],
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ], static fn ($v): bool => $v !== null));
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
