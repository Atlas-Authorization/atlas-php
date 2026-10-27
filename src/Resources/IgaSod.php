<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/iga` - 6 operations.
 */
final class IgaSod extends Resource
{
    /**
     * `GET /v1/iga/sod_rules` - List separation-of-duties rules: pairs of entitlements one person should
     * not hold together.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function listRules(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/sod_rules', query: $params);
    }

    /**
     * `POST /v1/iga/sod_rules` - Create a rule. `mode` is `block` (refuse) or `require_approval` (an extra
     * approval step on a request; `approver` names who, default the owner).
     *
     * @param array{name:string,description?:string,entitlement_a:string,entitlement_b:string,mode?:'block'|'require_approval',approver?:array{type:'manager'|'owner'|'group'|'users',ids?:list<mixed>,mode?:'any'|'all',timeout_seconds?:int,escalate_to?:array<string,mixed>},enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function createRule(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/sod_rules',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PATCH /v1/iga/sod_rules/:id` - Change a separation-of-duties rule.
     *
     * @param array{name?:string,description?:string,mode?:'block'|'require_approval',approver?:array{type:'manager'|'owner'|'group'|'users',ids?:list<mixed>,mode?:'any'|'all',timeout_seconds?:int,escalate_to?:array<string,mixed>},enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function updateRule(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/iga/sod_rules/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/iga/sod_rules/:id` - Delete a rule and its recorded violations.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function deleteRule(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/iga/sod_rules/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/iga/sod_violations` - The violations report: users who hold both sides of a rule (found by
     * a scan, or accepted at request or assignment time).
     *
     * @param array{status?:'open'|'accepted'|'resolved',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function violations(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/sod_violations', query: $params);
    }

    /**
     * `POST /v1/iga/sod_violations/scan` - Check every rule against what people hold right now: record new
     * conflicts and resolve those that cleared.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function scan(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/sod_violations/scan',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
