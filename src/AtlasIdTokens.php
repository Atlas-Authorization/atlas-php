<?php

declare(strict_types=1);

namespace Atlas;

use Atlas\Exception\ConfigException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * The browser route for integration tokens of Sign in with Atlas users: `POST /v1/client/integrations/:id/atlas_id_tokens`. An app
 * with no server of its own gets a token for a service from a user's Sign in with Atlas access token. It is normally called from
 * browser code; this is the same call for a backend-for-frontend.
 *
 * The access token is sent as `Authorization: Bearer`, or with `$dpopProof` (a DPoP proof for this exact request) as `DPoP`: required
 * when the token is DPoP-bound. Atlas checks the `Origin` against the Sign in with Atlas app's web origins, so pass `$origin` when
 * calling server-side. No cookies are sent.
 *
 *     $tokens = new AtlasIdTokens('https://clerk.example.com', 'pk_live_…');
 *     $token = $tokens->fetch('referalli', $userAccessToken, origin: 'https://app.example.com');
 *     $token['jwt'] ?? null;
 */
final class AtlasIdTokens
{
    private readonly string $base;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requests;

    public function __construct(
        string $frontendApi,
        private readonly string $publishableKey,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
    ) {
        $this->base = rtrim($frontendApi, '/');
        if ($httpClient === null || $requestFactory === null) {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new ConfigException('Pass a PSR-18 client and a PSR-17 request factory, or install guzzlehttp/guzzle.');
            }
            $httpClient ??= new \GuzzleHttp\Client(['timeout' => 10, 'http_errors' => false]);
            $requestFactory ??= new \GuzzleHttp\Psr7\HttpFactory();
        }
        $this->client = $httpClient;
        $this->requests = $requestFactory;
    }

    /**
     * @return array{jwt:string,expires_in:int,integration:string,audience:string,issuer:string,jwks_uri:string}|null null when the
     *   integration is not enabled for Sign in with Atlas users, the token is not accepted or the origin is not the app's
     */
    public function fetch(string $integration, string $accessToken, ?string $dpopProof = null, ?string $origin = null): ?array
    {
        if ($accessToken === '') {
            return null;
        }
        // The publishable key also rides in the query, so a CORS preflight (no custom headers) can name the instance.
        $url = $this->base . '/v1/client/integrations/' . rawurlencode($integration) . '/atlas_id_tokens?publishable_key=' . rawurlencode($this->publishableKey);
        $request = $this->requests->createRequest('POST', $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', ($dpopProof !== null ? 'DPoP ' : 'Bearer ') . $accessToken);
        if ($dpopProof !== null) {
            $request = $request->withHeader('DPoP', $dpopProof);
        }
        if ($origin !== null) {
            $request = $request->withHeader('Origin', $origin);
        }
        $response = $this->client->sendRequest($request);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
            return null;
        }
        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) && isset($data['jwt']) && is_string($data['jwt']) && $data['jwt'] !== '' ? $data : null;
    }
}
