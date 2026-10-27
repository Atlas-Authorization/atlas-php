<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/directory_pull_sources` - 6 operations.
 */
final class DirectoryPullSources extends Resource
{
    public readonly DirectoryPullSourcesSyncRuns $syncRuns;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->syncRuns = new DirectoryPullSourcesSyncRuns($http);
    }

    /**
     * `GET /v1/directory_pull_sources` - List HR / directory pull sources, optionally for one organization
     * (organization_id). Credentials are never returned.
     *
     * @param array{organization_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/directory_pull_sources', query: $params);
    }

    /**
     * `POST /v1/directory_pull_sources` - Create a pull source for an organization: provider (entra,
     * google_workspace, bamboohr, hibob, personio, csv, rest_json), config, write-only credentials and
     * settings (schedule, role mappings, disable on removal).
     *
     * @param array{organization_id:string,name:string,provider:'entra'|'google_workspace'|'bamboohr'|'hibob'|'personio'|'csv'|'rest_json',status?:'active'|'paused',config?:array<string,mixed>,credentials?:array<string,mixed>,default_role_id?:string|null,settings?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/directory_pull_sources',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/directory_pull_sources/:id` - Read one pull source.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/directory_pull_sources/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/directory_pull_sources/:id` - Change the source name, status (active or paused), config,
     * credentials, default role or settings. Settings are merged.
     *
     * @param array{organization_id?:string,name?:string,provider?:'entra'|'google_workspace'|'bamboohr'|'hibob'|'personio'|'csv'|'rest_json',status?:'active'|'paused',config?:array<string,mixed>,credentials?:array<string,mixed>,default_role_id?:string|null,settings?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/directory_pull_sources/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/directory_pull_sources/:id` - Delete a pull source and its run log. Users it created are
     * kept.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/directory_pull_sources/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/directory_pull_sources/:id/csv` - Upload the CSV a csv source without a URL reads on its
     * next run (up to 5 MiB).
     *
     * @param array{csv?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function setCsv(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'PUT',
            '/v1/directory_pull_sources/' . rawurlencode($id) . '/csv',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
