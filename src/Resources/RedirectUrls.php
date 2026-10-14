<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/redirect_urls` — the redirect URL allowlist.
 */
final class RedirectUrls extends Resource
{
    /**
     * `GET /v1/redirect_urls` — the allowlisted redirect URLs.
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/redirect_urls');
    }

    /**
     * `POST /v1/redirect_urls` — allow a redirect URL (idempotent on the URL).
     *
     * @return array<string,mixed>
     */
    public function create(string $url): array
    {
        return $this->http->request('POST', '/v1/redirect_urls', body: ['url' => $url]);
    }

    /**
     * `GET /v1/redirect_urls/:id` — one allowlisted redirect URL.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/redirect_urls/' . rawurlencode($id));
    }

    /**
     * `DELETE /v1/redirect_urls/:id` — remove a redirect URL from the allowlist.
     *
     * @return array<string,mixed>
     */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/redirect_urls/' . rawurlencode($id));
    }
}
