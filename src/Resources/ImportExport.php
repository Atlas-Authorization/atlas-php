<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/user_imports`, `/v1/user_exports`, `/v1/jobs` — bulk user import/export jobs. */
final class ImportExport extends Resource
{
    /**
     * Import users through the same per-row path sign-up uses. Deduped by verified email.
     * `notify: false` (the default) guarantees the imported users are sent nothing.
     * A row takes `image_url` (https; alias `profile_image_url`) like users->create, and a hash
     * that does not describe itself is named per row with `password_hash_algorithm` and
     * `password_hash_params`. A Node `scrypt$N$r$p$salt$key` string with an all-digit salt must be
     * named `scrypt_node` or `scrypt_django` (`salt_encoding` in `password_hash_params`).
     *
     * @param array{users:list<array<string,mixed>>,upsert?:bool,notify?:bool} $body
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
