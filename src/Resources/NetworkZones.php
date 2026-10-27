<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/network_zones` - 6 operations.
 */
final class NetworkZones extends Resource
{
    /**
     * `GET /v1/network_zones` - List reusable network zones — named sets of CIDRs, countries, ASNs and
     * anonymizer classes (tor, datacenter, vpn) that network ACL rules reference by zone_id.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/network_zones', query: $params);
    }

    /**
     * `POST /v1/network_zones` - Create a network zone. `name` is unique per instance; `cidrs`,
     * `countries` (ISO alpha-2), `asns` and `anonymizers` are unioned (any match = in the zone). Malformed
     * entries are refused.
     *
     * @param array{name?:string,description?:string|null,cidrs?:list<string>,countries?:list<string>,asns?:list<int>,anonymizers?:list<'tor'|'datacenter'|'vpn'>,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/network_zones',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/network_zones/:id` - Read one network zone.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/network_zones/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/network_zones/:id` - Update a network zone. Sending any of
     * cidrs/countries/asns/anonymizers replaces that list; omitted lists keep their value.
     *
     * @param array{name?:string,description?:string|null,cidrs?:list<string>,countries?:list<string>,asns?:list<int>,anonymizers?:list<'tor'|'datacenter'|'vpn'>,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/network_zones/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/network_zones/:id` - Delete a network zone. The network ACL rules that reference it are
     * deleted with it.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/network_zones/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/network_zones/:id/check` - What-if: does an IP address fall inside this zone? Returns the
     * resolved country / ASN / Tor / datacenter facts alongside the answer. Facts the instance has no
     * IP-intelligence data for are null and never match.
     *
     * @param array{ip?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function check(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/network_zones/' . rawurlencode($id) . '/check',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
