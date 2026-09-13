<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/user_imports`, `/v1/user_exports`, `/v1/jobs` — bulk user import/export jobs. */
final class ImportExport extends Resource
{
    /**
     * Import users through the same per-row path sign-up uses. Deduped by verified email.
     *
     * @param array{users:list<array<string,mixed>>,upsert?:bool} $body
     *
     * @return array<string,mixed> the job (finished or pending)
     */
    public function importUsers(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/user_imports', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** Export every non-deleted user, serialised without secrets. @return array<string,mixed> the job */
    public function exportUsers(?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/user_exports', idempotencyKey: $idempotencyKey);
    }

    /**
     * Poll the instance's import/export jobs, newest first.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed> a CursorPage of jobs
     */
    public function listJobs(array $params = []): array
    {
        return $this->http->request('GET', '/v1/jobs', query: $params);
    }

    /** @return array<string,mixed> */
    public function getJob(string $id): array
    {
        return $this->http->request('GET', '/v1/jobs/' . rawurlencode($id));
    }

    /** The per-row failures recorded against a job. @return array<string,mixed> */
    public function getJobErrors(string $id): array
    {
        return $this->http->request('GET', '/v1/jobs/' . rawurlencode($id) . '/errors');
    }
}
