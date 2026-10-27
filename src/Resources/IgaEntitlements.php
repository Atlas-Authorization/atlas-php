<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/iga/entitlements` - 5 operations.
 */
final class IgaEntitlements extends Resource
{
    /**
     * `GET /v1/iga/entitlements` - List the governed entitlement catalog.
     *
     * @param array{kind?:'role'|'org_membership'|'group'|'resource_role'|'app_assignment'|'custom',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/entitlements', query: $params);
    }

    /**
     * `POST /v1/iga/entitlements` - Add an entitlement to the catalog: a role, organization membership,
     * group, resource role, installed-app assignment or custom item, with an owner, a risk level and
     * approval rules. The target must exist. 409 for a key already in use.
     *
     * @param array{key:string,name:string,description?:string|null,kind:'role'|'org_membership'|'group'|'resource_role'|'app_assignment'|'custom',target:array<string,mixed>,owner_user_id?:string|null,risk_level?:'low'|'medium'|'high'|'critical',approval_rules?:array{steps:list<mixed>},max_duration_seconds?:int|null,requestable?:bool,require_justification?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/entitlements',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/iga/entitlements/:id` - Read one entitlement by id or key.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/iga/entitlements/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/iga/entitlements/:id` - Change an entitlement (its kind cannot change). A change to the
     * approval rules applies to requests filed afterwards.
     *
     * @param array{key?:string,name?:string,description?:string|null,kind?:'role'|'org_membership'|'group'|'resource_role'|'app_assignment'|'custom',target?:array<string,mixed>,owner_user_id?:string|null,risk_level?:'low'|'medium'|'high'|'critical',approval_rules?:array{steps:list<mixed>},max_duration_seconds?:int|null,requestable?:bool,require_justification?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/iga/entitlements/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/iga/entitlements/:id` - Remove an entitlement. 409 while it still has active grants.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/iga/entitlements/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
