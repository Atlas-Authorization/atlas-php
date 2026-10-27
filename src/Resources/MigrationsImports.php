<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/migrations/:id/imports` - 10 operations.
 */
final class MigrationsImports extends Resource
{
    /**
     * `GET /v1/migrations/:source/imports/public_key` - The instance's import public key (created on first
     * read). Seal the export to it; only this instance can open the bundle. One key serves every source. A
     * key with jobs:write may read it too.
     *
     * @return array<string,mixed>
     */
    public function getPublicKey(string $source): array
    {
        return $this->http->request('GET', '/v1/migrations/' . rawurlencode($source) . '/imports/public_key');
    }

    /**
     * `POST /v1/migrations/:source/imports/public_key/rotate` - Replace the import key. A bundle sealed to
     * the old key no longer finalizes; export again with the new key.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function rotatePublicKey(string $source, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/migrations/' . rawurlencode($source) . '/imports/public_key/rotate',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/migrations/:source/imports` - Start an import from `source` (status `uploading`). Then
     * upload the sealed bundle with PUT …/parts/{part} and call …/finalize. Nothing is written to users
     * until apply.
     *
     * @param array{options?:array{models?:list<mixed>,on_email_conflict?:'skip'|'link',create_app_roles?:bool,import_oauth_tokens?:bool,passkeys_on_rp_mismatch?:'skip'|'import',import_sessions?:bool,import_jwks?:bool,jwks_cutover_days?:int,provider_map?:array<string,mixed>,password_hash_params?:array<string,mixed>,retain_bundle?:bool}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(string $source, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/migrations/' . rawurlencode($source) . '/imports',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/migrations/:source/imports` - List the imports from `source`, newest first (cursor
     * pagination).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $source, array $params = []): array
    {
        return $this->http->request('GET', '/v1/migrations/' . rawurlencode($source) . '/imports', query: $params);
    }

    /**
     * `GET /v1/migrations/:source/imports/:id` - Fetch one import: status, the dry-run report, live
     * progress and the apply result.
     *
     * @return array<string,mixed>
     */
    public function get(string $source, string $id): array
    {
        return $this->http->request('GET', '/v1/migrations/' . rawurlencode($source) . '/imports/' . rawurlencode($id));
    }

    /**
     * `DELETE /v1/migrations/:source/imports/:id` - Delete an import and its stored bundle. Data it
     * already imported stays. Refused (409) while analyzing or applying.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $source, string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/migrations/' . rawurlencode($source) . '/imports/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/migrations/:source/imports/:id/parts/:part` - Upload part `part` (1, 2, …) of the sealed
     * bundle: whole lines, at most 10 MiB, as `{ "data": "…" }` or a text/plain body. Re-sending a part
     * replaces it. Only while `uploading`.
     *
     * @param array{data:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function putPart(string $source, string $id, string $part, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/migrations/' . rawurlencode($source) . '/imports/' . rawurlencode($id) . '/parts/' . rawurlencode($part),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/migrations/:source/imports/:id/finalize` - Close the upload: every part 1..n present, the
     * bundle sealed to the active import key and in a format `source` reads (else 400 or status
     * `invalid`). The dry run follows: inline up to 4 MiB (200, `ready`), else in the background (202,
     * `analyzing`; poll GET).
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function finalize(string $source, string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/migrations/' . rawurlencode($source) . '/imports/' . rawurlencode($id) . '/finalize',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/migrations/:source/imports/:id/dry_run` - Run the dry run again, optionally with new
     * options (they replace the stored ones). Reads only.
     *
     * @param array{options?:array{models?:list<mixed>,on_email_conflict?:'skip'|'link',create_app_roles?:bool,import_oauth_tokens?:bool,passkeys_on_rp_mismatch?:'skip'|'import',import_sessions?:bool,import_jwks?:bool,jwks_cutover_days?:int,provider_map?:array<string,mixed>,password_hash_params?:array<string,mixed>,retain_bundle?:bool}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function dryRun(string $source, string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/migrations/' . rawurlencode($source) . '/imports/' . rawurlencode($id) . '/dry_run',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/migrations/:source/imports/:id/apply` - Import the bundle. Every record is an idempotent
     * upsert keyed by its source id, so applying a failed import again resumes where it stopped. Up to
     * 1,000 remaining records run inline (200), more are queued (202). With conflicts in the dry run, send
     * confirm_conflicts: true.
     *
     * @param array{confirm_conflicts?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function apply(string $source, string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/migrations/' . rawurlencode($source) . '/imports/' . rawurlencode($id) . '/apply',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
