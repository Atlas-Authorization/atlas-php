<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1` - 5 operations.
 */
final class Devices extends Resource
{
    /**
     * `GET /v1/devices` - List registered devices, filtered by user_id or source.
     *
     * @param array{user_id?:string,source?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/devices', query: $params);
    }

    /**
     * `PUT /v1/devices` - Create or refresh one device by (source, external_id), reporting
     * managed/compliant posture. Tie it to a user with user_id or a verified user_email. Access policies
     * read the result through the deviceTrust condition: trusted = a managed, compliant device reported
     * within 30 days.
     *
     * @param array{external_id?:string,source?:string,user_id?:string|null,user_email?:string,name?:string|null,platform?:string|null,managed?:bool,compliant?:bool,posture?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function report(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('PUT', '/v1/devices', body: $body, query: $params, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/devices/:id` - Fetch a device and its latest posture.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/devices/' . rawurlencode($id));
    }

    /**
     * `DELETE /v1/devices/:id` - Remove a device from the registry.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function remove(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/devices/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/device_posture` - Posture ingestion for your MDM/EDR or its relay: up to 500 device
     * reports per call, same fields as PUT /v1/devices.
     *
     * @param array{devices?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function ingestPosture(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/device_posture',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
