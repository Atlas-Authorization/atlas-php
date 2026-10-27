<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/abac_schema` - 4 operations.
 */
final class AbacSchema extends Resource
{
    /**
     * `GET /v1/abac_schema` - The instance’s ABAC entity schema (`schema` is null and `version` 0 when
     * none is set).
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/abac_schema');
    }

    /**
     * `PUT /v1/abac_schema` - Set the ABAC entity schema: `entity_types` (attributes with `boolean`,
     * `long`, `string`, `set`, `entity` and `record` types, `required: false` for optional ones, and
     * `member_of`) and `actions` (`applies_to` principal and resource types and a `context`). Every
     * problem is reported with its path. Once set, policies and templates are type-checked when saved:
     * unknown attributes, optional attributes read without a `has` guard, wrong operand types, unknown
     * entity types and actions, and actions that do not apply to the policy’s principal and resource
     * types. The response lists the errors the schema would give the policies and links already stored.
     *
     * @param array{schema:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function set(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/abac_schema',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/abac_schema` - Remove the entity schema; policies are no longer type-checked.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request('DELETE', '/v1/abac_schema', query: $params, idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/abac_schema/validate` - Type-check policy (or, with `template: true`, template) source
     * against the saved schema, or one sent as `schema`, without saving anything. Returns every issue with
     * its policy number, clause, character offset, line and column. A syntax error is reported as an issue
     * too.
     *
     * @param array{source:string,schema?:array<string,mixed>,template?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function validate(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac_schema/validate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
