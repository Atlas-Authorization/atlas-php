<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/organization_settings_schema` — the JSON Schema organization settings are validated against.
 */
final class OrganizationSettingsSchema extends Resource
{
    /**
     * `GET /v1/organization_settings_schema` — the instance-wide JSON Schema for organization settings.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/organization_settings_schema');
    }

    /**
     * `POST /v1/organization_settings_schema` — register the organization settings JSON Schema (first
     * registration).
     *
     * @param array<string,mixed> $schema
     *
     * @return array<string,mixed>
     */
    public function create(array $schema, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/organization_settings_schema',
            body: ['schema' => $schema === [] ? new \stdClass() : $schema],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/organization_settings_schema` — replace the organization settings JSON Schema (bumps its
     * version).
     *
     * @param array<string,mixed> $schema
     *
     * @return array<string,mixed>
     */
    public function replace(array $schema, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/organization_settings_schema',
            body: ['schema' => $schema === [] ? new \stdClass() : $schema],
            idempotencyKey: $idempotencyKey,
        );
    }
}
