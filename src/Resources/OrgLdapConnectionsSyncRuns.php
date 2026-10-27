<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/org_ldap_connections/:id/sync_runs` - 3 operations.
 */
final class OrgLdapConnectionsSyncRuns extends Resource
{
    /**
     * `POST /v1/org_ldap_connections/:id/sync_runs` - Queue a sync run now (202). `full` (default) lists
     * the whole directory and, when disable-on-removal is on, bans users that left it; `delta` only reads
     * entries changed since the last successful run. Refused with 409 while another run is queued or
     * running.
     *
     * @param array{kind?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function create(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/org_ldap_connections/' . rawurlencode($id) . '/sync_runs',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/org_ldap_connections/:id/sync_runs` - The sync run log, newest first, with per-run counts
     * (seen, created, updated, skipped, disabled, role changes, groups, errors) and any failure message.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/org_ldap_connections/' . rawurlencode($id) . '/sync_runs',
            query: $params,
        );
    }

    /**
     * `GET /v1/org_ldap_connections/:id/sync_runs/:runId` - Read one LDAP directory sync run with its
     * counts and error.
     *
     * @return array<string,mixed>
     */
    public function get(string $id, string $runId): array
    {
        return $this->http->request(
            'GET',
            '/v1/org_ldap_connections/' . rawurlencode($id) . '/sync_runs/' . rawurlencode($runId),
        );
    }
}
