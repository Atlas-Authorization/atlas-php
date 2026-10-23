<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/oauth_initial_access_tokens` — tokens that gate OAuth dynamic client
 * registration (RFC 7591 §3) once `oauthProvider.requireInitialAccessToken` is on.
 */
final class OAuthInitialAccessTokens extends Resource
{
    /** @return array<string,mixed> */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/oauth_initial_access_tokens');
    }

    /**
     * Mint a token; `token` is returned exactly once.
     *
     * @param array{description?:string,expires_in_seconds?:int,max_uses?:int} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body = []): array
    {
        return $this->http->request('POST', '/v1/oauth_initial_access_tokens', body: $body === [] ? (object) [] : $body);
    }

    /** @return array<string,mixed> */
    public function revoke(string $id): array
    {
        return $this->http->request('DELETE', '/v1/oauth_initial_access_tokens/' . rawurlencode($id));
    }
}
