<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `GET /v1/whoami` — who the calling key is: its kind, scopes, owner, expiry, and the instance or account it belongs to.
 */
final class Whoami extends Resource
{
    /**
     * The calling credential (`key`, `instance`, `account`, `scopes`; `auth_method` is `client_credentials` or `bearer_secret`).
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/whoami');
    }
}
