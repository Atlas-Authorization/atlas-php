<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1` - 8 operations.
 */
final class Flags extends Resource
{
    /**
     * `PUT /v1/organizations/:id/entitlements` - Assign an organization's entitlements (a plan, a feature
     * list and/or limits, optionally expiring) without a billing subscription. Replaces whatever was
     * assigned or overridden before; an empty body clears it and the organization falls back to its
     * billing plan. Reaches the `pla`, `fea` and `ent` session claims on the next token.
     *
     * @param array{plan?:string|null,features?:list<string>|null,limits?:array<string,mixed>|null,expires_at?:int|string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function assignOrganizationEntitlements(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/organizations/' . rawurlencode($id) . '/entitlements',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/feature_flags` - Create a feature flag: a typed value (`boolean`, `string`, `int` or
     * `json`) with a `default_value`, an `off_value` (served while `enabled` is false; defaults to false
     * for a boolean, else the default) and ordered targeting `rules` by org, user, plan and percentage.
     * 409 when the key exists.
     *
     * @param array{key:string,type:'boolean'|'string'|'int'|'json',description?:string|null,enabled?:bool,default_value:bool|string|int|int|float|array<string,mixed>|list<mixed>|null,off_value?:bool|string|int|int|float|array<string,mixed>|list<mixed>|null,rules?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/feature_flags',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/feature_flags` - List feature flags ordered by key. `starting_after` is the key of the last
     * flag of the previous page.
     *
     * @param array{starting_after?:string,limit?:int} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/feature_flags', query: $params);
    }

    /**
     * `GET /v1/feature_flags/:key` - Read one feature flag.
     *
     * @return array<string,mixed>
     */
    public function retrieve(string $key): array
    {
        return $this->http->request('GET', '/v1/feature_flags/' . rawurlencode($key));
    }

    /**
     * `PATCH /v1/feature_flags/:key` - Change a flag's description, kill switch, default or off value, or
     * replace its rules. The type and key are fixed.
     *
     * @param array{description?:string|null,enabled?:bool,default_value?:bool|string|int|int|float|array<string,mixed>|list<mixed>|null,off_value?:bool|string|int|int|float|array<string,mixed>|list<mixed>|null,rules?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $key, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/feature_flags/' . rawurlencode($key),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/feature_flags/:key` - Delete a feature flag.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $key, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/feature_flags/' . rawurlencode($key),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/feature_flags/evaluate` - Evaluate every flag (or the listed `keys`) for a subject
     * (`user_id`, `org_id`, `plan`). When `plan` is omitted it is the organization's (else the user's)
     * resolved plan. Each result carries the `value` and a `reason` (`off`, `default` or `rule:<index>`).
     *
     * @param array{user_id?:string,org_id?:string,plan?:string,keys?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function evaluate(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/feature_flags/evaluate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/users/:id/entitlements` - Assign a user's entitlements the same way as for an organization.
     *
     * @param array{plan?:string|null,features?:list<string>|null,limits?:array<string,mixed>|null,expires_at?:int|string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function assignUserEntitlements(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/users/' . rawurlencode($id) . '/entitlements',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
