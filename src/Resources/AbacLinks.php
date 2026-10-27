<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/abac_policy_links` - 5 operations.
 */
final class AbacLinks extends Resource
{
    /**
     * `GET /v1/abac_policy_links` - List template links: a template with its slots filled by entities.
     * Each link’s `source` is what the policy engine evaluates.
     *
     * @param array{template_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/abac_policy_links', query: $params);
    }

    /**
     * `POST /v1/abac_policy_links` - Link a template to entities. Every slot the template uses needs its
     * entity (`principal` for `?principal`, `resource` for `?resource`); one it does not use is refused.
     * The link is evaluated together with the other enabled policies, always from the template’s current
     * source, and is type-checked against the entity schema when one is set.
     *
     * @param array{template_id:string,name:string,principal?:array{type?:string,id?:string}|null,resource?:array{type?:string,id?:string}|null,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac_policy_links',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/abac_policy_links/:id` - Read one template link.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/abac_policy_links/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/abac_policy_links/:id` - Rename, enable or disable a link, or point its slots at other
     * entities.
     *
     * @param array{template_id?:string,name?:string,principal?:array{type?:string,id?:string}|null,resource?:array{type?:string,id?:string}|null,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/abac_policy_links/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/abac_policy_links/:id` - Delete a link; the template stays.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/abac_policy_links/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
