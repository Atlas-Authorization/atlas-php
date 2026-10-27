<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/group_rules` - 7 operations.
 */
final class GroupsRules extends Resource
{
    /**
     * `GET /v1/group_rules` - Group rules, lowest priority number first.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/group_rules', query: $params);
    }

    /**
     * `POST /v1/group_rules` - Create a rule: when a user matches condition, run the actions (add to a
     * group, grant an app role, set a trait). → 201.
     *
     * @param array{name:string,enabled?:bool,priority?:int,condition:array<string,mixed>,actions:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/group_rules',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/group_rules/apply` - Run every enabled rule in priority order. One result per rule.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function applyAll(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/group_rules/apply', query: $params, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/group_rules/:id` - One group rule, with its condition and actions.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/group_rules/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/group_rules/:id` - Change a group rule, its condition or actions.
     *
     * @param array{name?:string,enabled?:bool,priority?:int,condition?:array<string,mixed>,actions?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/group_rules/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/group_rules/:id` - Delete a rule. Memberships it created stay until removed.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/group_rules/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/group_rules/:id/apply` - Run one rule against every matching user now. Returns the match
     * count and what changed.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function apply(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/group_rules/' . rawurlencode($id) . '/apply',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
