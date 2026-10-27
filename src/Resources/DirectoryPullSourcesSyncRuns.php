<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/directory_pull_sources/:id/sync_runs` - 3 operations.
 */
final class DirectoryPullSourcesSyncRuns extends Resource
{
    /**
     * `POST /v1/directory_pull_sources/:id/sync_runs` - Queue a pull now. One run at a time per source
     * (409 otherwise); the worker executes it.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/directory_pull_sources/' . rawurlencode($id) . '/sync_runs',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/directory_pull_sources/:id/sync_runs` - List the runs of a source with counts of people
     * seen, created, re-roled, disabled and re-enabled.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/directory_pull_sources/' . rawurlencode($id) . '/sync_runs',
            query: $params,
        );
    }

    /**
     * `GET /v1/directory_pull_sources/:id/sync_runs/:runId` - Read one directory pull run with its counts
     * and error.
     *
     * @return array<string,mixed>
     */
    public function get(string $id, string $runId): array
    {
        return $this->http->request(
            'GET',
            '/v1/directory_pull_sources/' . rawurlencode($id) . '/sync_runs/' . rawurlencode($runId),
        );
    }
}
