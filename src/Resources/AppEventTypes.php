<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/app_event_types` — the catalog of your application's event types. An AppEventType is
 * `{object: 'app_event_type', id, type, description, schema, deprecated, created_at, updated_at}`;
 * `schema` (JSON Schema) is what every published `data` must match, null for any object; a
 * deprecated type is still publishable but hidden from the endpoint picker. Address one by its
 * `aet_` id or by its name.
 */
final class AppEventTypes extends Resource
{
    /** @return array<string,mixed> a ListPage of AppEventType */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/app_event_types');
    }

    /**
     * @param array{type:string,description?:string|null,schema?:array<string,mixed>|null,deprecated?:bool} $body
     *   `type` is a lowercase dotted name, e.g. `call.completed`
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/app_event_types', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $idOrType): array
    {
        return $this->http->request('GET', '/v1/app_event_types/' . rawurlencode($idOrType));
    }

    /**
     * @param array{description?:string|null,schema?:array<string,mixed>|null,deprecated?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $idOrType, array $body): array
    {
        return $this->http->request('PATCH', '/v1/app_event_types/' . rawurlencode($idOrType), body: $body);
    }

    /** @return array<string,mixed> `{object: 'app_event_type', id, type, deleted: true}` */
    public function delete(string $idOrType): array
    {
        return $this->http->request('DELETE', '/v1/app_event_types/' . rawurlencode($idOrType));
    }
}
