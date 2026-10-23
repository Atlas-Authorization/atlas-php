<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/scim_tokens` — inbound SCIM 2.0 bearer tokens (secret revealed once). */
final class ScimTokens extends Resource
{
    /** @return array<string,mixed> a ListPage of tokens */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/scim_tokens');
    }

    /**
     * @param array{organization_id:string,name?:string,connection_id?:string} $body
     *
     * @return array<string,mixed> the token plus its once-only secret
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/scim_tokens', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function revoke(string $id): array
    {
        return $this->http->request('POST', '/v1/scim_tokens/' . rawurlencode($id) . '/revoke');
    }

    /**
     * Change a SCIM token's `name`, `scopes` (`['read']` = read-only) or
     * `role_mappings` (`[['value' => ..., 'role' => ...]]`). The secret is unchanged.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/scim_tokens/' . rawurlencode($id), body: $body);
    }
}
