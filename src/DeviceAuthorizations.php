<?php

declare(strict_types=1);

namespace Atlas;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Device sign-in approval (OAuth 2.0 Device Authorization Grant, RFC 8628) from your own
 * app: the signed-in user types — or scans — the code a TV, console or CLI shows, and
 * your app approves it instead of sending them to Atlas's hosted `/oauth2/device` page.
 *
 * These calls run on the Frontend API as the signed-in USER: they need the user's
 * session token (a native app's session JWT), never a secret key, PAT or OAuth token.
 *
 *     $devices = new DeviceAuthorizations('https://clerk.example.com', $sessionToken);
 *     $auth = $devices->lookup($typedCode);
 *     $devices->approve($auth); // or $devices->deny($auth)
 */
final class DeviceAuthorizations
{
    private const BASE = '/v1/client/me/device_authorizations';

    private readonly string $base;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;

    public function __construct(
        string $frontendApi,
        private readonly string $sessionToken,
        private readonly ?string $publishableKey = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
    ) {
        $this->base = rtrim($frontendApi, '/');
        $this->client = $httpClient ?? new \GuzzleHttp\Client(['timeout' => 10, 'http_errors' => false]);
        $this->requestFactory = $requestFactory ?? new \GuzzleHttp\Psr7\HttpFactory();
    }

    /** Normalise what a person typed into the `XXXX-XXXX` form devices show. */
    public static function formatUserCode(string $value): string
    {
        $cleaned = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($value));

        return strlen($cleaned) === 8 ? substr($cleaned, 0, 4) . '-' . substr($cleaned, 4) : trim($value);
    }

    /** The `user_code` of a verification URL the device displayed, formatted. */
    public static function userCodeFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['query'])) {
            return null;
        }
        parse_str($parts['query'], $query);
        $value = $query['user_code'] ?? null;

        return is_string($value) && $value !== '' ? self::formatUserCode($value) : null;
    }

    /**
     * Find a pending device sign-in by the code the device shows.
     *
     * @return array<string,mixed>
     */
    public function lookup(string $userCode): array
    {
        return $this->call(self::BASE . '/lookup', ['user_code' => self::formatUserCode($userCode)]);
    }

    /**
     * Approve for the signed-in user; the device's next poll receives its tokens.
     *
     * @param array{id:string,user_code:string} $authorization
     *
     * @return array<string,mixed>
     */
    public function approve(array $authorization): array
    {
        return $this->call(self::BASE . '/' . rawurlencode($authorization['id']) . '/approve', ['user_code' => $authorization['user_code']]);
    }

    /**
     * Deny; the device's next poll is told `access_denied`.
     *
     * @param array{id:string,user_code:string} $authorization
     *
     * @return array<string,mixed>
     */
    public function deny(array $authorization): array
    {
        return $this->call(self::BASE . '/' . rawurlencode($authorization['id']) . '/deny', ['user_code' => $authorization['user_code']]);
    }

    /**
     * @param array<string,string> $body
     *
     * @return array<string,mixed>
     */
    private function call(string $path, array $body): array
    {
        $request = $this->requestFactory->createRequest('POST', $this->base . $path)
            ->withHeader('Authorization', 'Bearer ' . $this->sessionToken)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(\GuzzleHttp\Psr7\Utils::streamFor(json_encode($body, JSON_THROW_ON_ERROR)));
        if ($this->publishableKey !== null) {
            $request = $request->withHeader('x-publishable-key', $this->publishableKey);
        }
        $response = $this->client->sendRequest($request);
        $data = json_decode((string) $response->getBody(), true);
        $status = $response->getStatusCode();
        if ($status < 200 || $status > 299) {
            $first = is_array($data) && isset($data['errors'][0]) && is_array($data['errors'][0]) ? $data['errors'][0] : [];
            throw new DeviceAuthorizationException(
                $status,
                (string) ($first['code'] ?? 'UNKNOWN'),
                (string) ($first['message'] ?? 'The request failed.'),
                isset($first['param']) ? (string) $first['param'] : null,
                isset($first['reason']) ? (string) $first['reason'] : null,
            );
        }

        return is_array($data) ? $data : [];
    }
}
