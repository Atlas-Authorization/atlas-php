<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/better_auth_imports` — migrate from Better Auth: import a sealed bundle made
 * by the Better Auth exporter (`npx atlas-better-auth-export`).
 *
 * 1. {@see getPublicKey()} — the key the exporter seals the bundle to.
 * 2. {@see upload()} (or {@see create()}, {@see uploadPart()} per part and
 *    {@see finalize()}) — Atlas opens the bundle and runs the dry run.
 * 3. Read the import's `report`, then {@see apply()}. Applying again after a
 *    failure resumes where it stopped; every record is an idempotent upsert.
 *
 * Statuses: `uploading` → `analyzing` → `ready` | `invalid` → `queued` →
 * `applying` → `completed` | `failed`.
 */
final class BetterAuthImports extends Resource
{
    /** Bytes per uploaded bundle part (the API takes at most 10 MiB per part). */
    public const PART_BYTES = 8 * 1024 * 1024;

    /** Statuses an import stays in while Atlas is working on it ({@see waitFor()} polls through them). */
    public const IN_FLIGHT = ['analyzing', 'queued', 'applying'];

    /** @var callable(float):void */
    private $sleep;

    /**
     * @param callable(float):void|null $sleep how {@see waitFor()} waits between polls (seconds); for tests
     */
    public function __construct(Http $http, ?callable $sleep = null)
    {
        parent::__construct($http);
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    /**
     * `GET /v1/better_auth_imports/public_key` — the instance's import public key (created on first
     * read): `kid`, `public_key`, `alg`, `status`, `fingerprint`, `compact` (`kid:public_key`, what the
     * exporter's `--public-key` takes).
     *
     * @return array<string,mixed>
     */
    public function getPublicKey(): array
    {
        return $this->http->request('GET', '/v1/better_auth_imports/public_key');
    }

    /**
     * `POST /v1/better_auth_imports/public_key/rotate` — replace the import key. The old one is retired:
     * bundles sealed to it no longer finalize (`import_key_retired`).
     *
     * @return array<string,mixed>
     */
    public function rotatePublicKey(?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/better_auth_imports/public_key/rotate', idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/better_auth_imports` — start an import (status `uploading`).
     *
     * @param array<string,mixed> $body `{options?}`: `models`, `on_email_conflict` (skip|link), `default_role`,
     *                                  `create_app_roles`, `import_oauth_tokens`, `passkeys_on_rp_mismatch`
     *                                  (skip|import), `import_subscriptions`, `plan_map`, `import_jwks`,
     *                                  `jwks_cutover_days`, `import_sessions`, `retain_bundle`
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/better_auth_imports', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/better_auth_imports` — imports, newest first (`data`, `has_more`, `next_cursor`).
     *
     * @return array<string,mixed>
     */
    public function list(?int $limit = null, ?string $startingAfter = null): array
    {
        return $this->http->request('GET', '/v1/better_auth_imports', query: [
            'limit' => $limit,
            'starting_after' => $startingAfter,
        ]);
    }

    /**
     * `GET /v1/better_auth_imports/:id` — status, progress, the dry-run `report` and the `result`.
     *
     * @return array<string,mixed>
     */
    public function get(string $importId): array
    {
        return $this->http->request('GET', '/v1/better_auth_imports/' . rawurlencode($importId));
    }

    /**
     * `PUT /v1/better_auth_imports/:id/parts/:part` — upload part `$part` (1-based) of the sealed bundle:
     * whole lines, at most 10 MiB, sent as a `text/plain` body. Re-sending a part replaces it.
     *
     * @return array<string,mixed>
     */
    public function uploadPart(string $importId, int $part, string $data): array
    {
        return $this->http->request(
            'PUT',
            '/v1/better_auth_imports/' . rawurlencode($importId) . '/parts/' . $part,
            rawBody: $data,
            contentType: 'text/plain; charset=utf-8',
        );
    }

    /**
     * `POST /v1/better_auth_imports/:id/finalize` — close the upload: Atlas checks the parts and the key,
     * then runs the dry run (inline up to 4 MiB → `ready` / `invalid`; above that `analyzing` — see
     * {@see waitFor()}).
     *
     * @return array<string,mixed>
     */
    public function finalize(string $importId): array
    {
        return $this->http->request('POST', '/v1/better_auth_imports/' . rawurlencode($importId) . '/finalize');
    }

    /**
     * `POST /v1/better_auth_imports/:id/dry_run` — run the dry run again; new `options` replace the stored ones.
     *
     * @param array<string,mixed> $body `{options?}`
     *
     * @return array<string,mixed>
     */
    public function dryRun(string $importId, array $body = []): array
    {
        return $this->http->request('POST', '/v1/better_auth_imports/' . rawurlencode($importId) . '/dry_run', body: $body);
    }

    /**
     * `POST /v1/better_auth_imports/:id/apply` — import (or resume a `failed` import from its cursor).
     * Pass `['confirm_conflicts' => true]` when the dry run reported conflicts (else 409).
     *
     * @param array<string,mixed> $body `{confirm_conflicts?}`
     *
     * @return array<string,mixed>
     */
    public function apply(string $importId, array $body = []): array
    {
        return $this->http->request('POST', '/v1/better_auth_imports/' . rawurlencode($importId) . '/apply', body: $body);
    }

    /**
     * `DELETE /v1/better_auth_imports/:id` — remove the import and its stored bundle; data already imported
     * stays. 409 while it is analyzing, queued or applying.
     *
     * @return array<string,mixed>
     */
    public function delete(string $importId): array
    {
        return $this->http->request('DELETE', '/v1/better_auth_imports/' . rawurlencode($importId));
    }

    /**
     * `GET /v1/better_auth_imports/jwks` — the Better Auth JWT keys Atlas publishes in the instance JWKS for
     * the cutover (`imported_jwk`: `kid`, `jwk`, `expires_at`, …).
     *
     * @return array<string,mixed>
     */
    public function listJwks(): array
    {
        return $this->http->request('GET', '/v1/better_auth_imports/jwks');
    }

    /**
     * `DELETE /v1/better_auth_imports/jwks/:kid` — stop publishing one imported Better Auth JWT key.
     *
     * @return array<string,mixed>
     */
    public function deleteJwk(string $kid): array
    {
        return $this->http->request('DELETE', '/v1/better_auth_imports/jwks/' . rawurlencode($kid));
    }

    /**
     * Create an import, upload the sealed bundle in line-aligned parts of at most `$partBytes` and
     * finalize it. Returns the finalized import (`ready` / `invalid`, or `analyzing` for a large
     * bundle — then {@see waitFor()}).
     *
     * @param array<string,mixed>|null       $options import options (see {@see create()})
     * @param callable(int,int):void|null    $onPart  called after each part with (part, total)
     *
     * @return array<string,mixed>
     */
    public function upload(string $bundle, ?array $options = null, int $partBytes = self::PART_BYTES, ?callable $onPart = null): array
    {
        $parts = self::splitBundleParts($bundle, $partBytes);
        if ($parts === []) {
            throw new \InvalidArgumentException('The bundle is empty.');
        }
        $created = $this->create($options !== null && $options !== [] ? ['options' => $options] : []);
        $importId = $created['id'] ?? null;
        if (!is_string($importId) || $importId === '') {
            throw new \UnexpectedValueException('Atlas did not return an import id.');
        }
        $total = count($parts);
        foreach ($parts as $i => $part) {
            $this->uploadPart($importId, $i + 1, $part);
            if ($onPart !== null) {
                $onPart($i + 1, $total);
            }
        }

        return $this->finalize($importId);
    }

    /**
     * Poll until the import leaves `analyzing` / `queued` / `applying` and return it (`ready`,
     * `invalid`, `completed` or `failed`).
     *
     * @param callable(array<string,mixed>):void|null $onProgress called with every polled import
     *
     * @return array<string,mixed>
     *
     * @throws \RuntimeException after `$timeoutSeconds`
     */
    public function waitFor(string $importId, float $intervalSeconds = 2.0, float $timeoutSeconds = 6 * 3600.0, ?callable $onProgress = null): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $waited = 0.0;
        while (true) {
            $import = $this->get($importId);
            if ($onProgress !== null) {
                $onProgress($import);
            }
            $status = $import['status'] ?? null;
            if (!in_array($status, self::IN_FLIGHT, true)) {
                return $import;
            }
            if (microtime(true) > $deadline || $waited >= $timeoutSeconds) {
                throw new \RuntimeException(sprintf(
                    'Timed out waiting for Better Auth import %s (status %s).',
                    $importId,
                    is_string($status) ? $status : 'unknown',
                ));
            }
            ($this->sleep)($intervalSeconds);
            $waited += $intervalSeconds;
        }
    }

    /**
     * Split a sealed bundle into upload parts of at most `$maxBytes`, each ending on a line boundary
     * (`\n`). Empty lines are dropped; a single line longer than `$maxBytes` becomes a part of its own
     * (the API refuses parts over 10 MiB, so keep `$maxBytes` at or under the default).
     *
     * @return list<string>
     */
    public static function splitBundleParts(string $bundle, int $maxBytes = self::PART_BYTES): array
    {
        if ($maxBytes <= 0) {
            throw new \InvalidArgumentException('maxBytes must be positive.');
        }
        $parts = [];
        $current = '';
        foreach (explode("\n", $bundle) as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') {
                continue;
            }
            $n = strlen($line) + 1;
            if ($current !== '' && strlen($current) + $n > $maxBytes) {
                $parts[] = $current;
                $current = '';
            }
            $current .= $line . "\n";
        }
        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}
