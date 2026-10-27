<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/abac_templates` - 5 operations.
 */
final class AbacTemplates extends Resource
{
    /**
     * `GET /v1/abac_templates` - List the ABAC policy templates, with their slots and how many links each
     * has.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/abac_templates', query: $params);
    }

    /**
     * `POST /v1/abac_templates` - Create a policy template: policy source whose scope uses the slots
     * `?principal` and/or `?resource` (`principal == ?principal`, `resource in ?resource`, `resource is
     * Doc in ?resource`). Slots are filled by a link. With an entity schema set, the template is
     * type-checked on save (a slot stands for any entity type).
     *
     * @param array{name:string,description?:string|null,source:string,fga_store_id?:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac_templates',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/abac_templates/:id` - Read one policy template.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/abac_templates/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/abac_templates/:id` - Change a template. A new `source` bumps `version` and takes effect
     * on every link at once (`links_updated` counts them). It is refused, with one error per problem and
     * the link named, if any link could no longer be filled in or would fail the entity schema.
     *
     * @param array{name?:string,description?:string|null,source?:string,fga_store_id?:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/abac_templates/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/abac_templates/:id` - Delete a template. A template with links is refused with 409
     * unless `force=true`, which deletes the links too.
     *
     * @param array{dry_run?:'true'|'false',force?:bool} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/abac_templates/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
