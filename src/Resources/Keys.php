<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/keys` — every credential on the instance in one standard shape, and leaked-key revocation.
 */
final class Keys extends Resource
{
    /**
     * Every key (secret keys, publishable keys, partner links) in one list.
     *
     * @return array<string,mixed>
     */
    public function list(array $query = []): array
    {
        return $this->http->request('GET', '/v1/keys', query: $query);
    }

    /**
     * Revoke leaked Atlas credentials: `{secrets: [..], url?}`. Needs no key (a leak-scanner callback); answers `results[]` with `outcome` `revoked`, `already_revoked` or `not_found`.
     *
     * @return array<string,mixed>
     */
    public function revokeLeaked(array $body): array
    {
        return $this->http->request('POST', '/v1/keys/revoke_leaked', body: $body);
    }
}
