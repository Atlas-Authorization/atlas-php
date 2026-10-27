<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1` - 10 operations.
 */
final class Groups extends Resource
{
    public readonly GroupsRules $rules;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->rules = new GroupsRules($http);
    }

    /**
     * `GET /v1/groups` - Every instance group with its member count, by slug.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/groups', query: $params);
    }

    /**
     * `POST /v1/groups` - Create an instance group. Emits group.created. → 201.
     *
     * @param array{slug:string,name:string,description?:string|null,parent_id?:string|null,attributes?:array<string,mixed>,app_role_keys?:list<string>,include_in_token?:bool,dynamic_filter?:array<string,mixed>|null,sso_match?:array<string,mixed>|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/groups', body: $body, query: $params, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/groups/:id` - One group, by id or slug.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/groups/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/groups/:id` - Change a group. Emits group.updated.
     *
     * @param array{slug?:string,name?:string,description?:string|null,parent_id?:string|null,attributes?:array<string,mixed>,app_role_keys?:list<string>,include_in_token?:bool,dynamic_filter?:array<string,mixed>|null,sso_match?:array<string,mixed>|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/groups/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/groups/:id` - Delete a group: its memberships go, its children move to the top level.
     * Emits group.deleted.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/groups/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/groups/:id/members` - The members of one group (direct members only), with how each joined
     * (manual, dynamic, sso, rule). Cursor paged by user id.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function listMembers(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/groups/' . rawurlencode($id) . '/members', query: $params);
    }

    /**
     * `POST /v1/groups/:id/members` - Add up to 500 users by hand. A member who joined by filter, SSO or
     * rule becomes manual, so a re-apply leaves them. Emits group.member_added per new member.
     *
     * @param array{user_ids:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function addMembers(string $id, array $body, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/groups/' . rawurlencode($id) . '/members',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/groups/:id/members/remove` - Remove up to 500 users from a group. Emits
     * group.member_removed.
     *
     * @param array{user_ids:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function removeMembers(string $id, array $body, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/groups/' . rawurlencode($id) . '/members/remove',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/groups/:id/reapply` - Re-evaluate a dynamic group now: users matching dynamic_filter are
     * added, those who no longer match are removed; manual members are never touched (400 when the group
     * has no dynamic_filter, or the filter matches over 50,000 users).
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function reapply(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/groups/' . rawurlencode($id) . '/reapply',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/users/:id/groups` - A user's direct groups and how they joined each.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function forUser(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/groups', query: $params);
    }
}
