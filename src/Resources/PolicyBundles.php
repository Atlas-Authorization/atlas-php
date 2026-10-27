<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/policy_bundles` - 7 operations.
 */
final class PolicyBundles extends Resource
{
    /**
     * `GET /v1/policy_bundles/current` - The bundle revision the instance policy is at right now. Compare
     * it with a deployed bundle’s revision to detect drift.
     *
     * @return array<string,mixed>
     */
    public function current(): array
    {
        return $this->http->request('GET', '/v1/policy_bundles/current');
    }

    /**
     * `GET /v1/policy_bundles` - List the signed policy bundles exported or imported on this instance,
     * newest first (without the archives).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/policy_bundles', query: $params);
    }

    /**
     * `POST /v1/policy_bundles` - Export the instance policy — every ABAC policy (current version), the
     * ABAC entity schema, templates and template links, and every FGA store’s latest authorization model —
     * as a versioned tar.gz with a manifest of file digests, signed with the instance signing key
     * (detached JWS). The same policy always has the same revision.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function export(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/policy_bundles', query: $params, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/policy_bundles/:revision` - Read a stored bundle by revision, with the base64 archive and
     * its signature.
     *
     * @return array<string,mixed>
     */
    public function get(string $revision): array
    {
        return $this->http->request('GET', '/v1/policy_bundles/' . rawurlencode($revision));
    }

    /**
     * `GET /v1/policy_bundles/:revision/archive` - Download a stored bundle as the raw tar.gz
     * (`application/gzip`). The detached signature is in the `Atlas-Bundle-Signature` header, with
     * `Atlas-Bundle-Revision` and `Atlas-Bundle-Key-Id`.
     *
     * @return string the raw gzip bytes
     */
    public function archive(string $revision): string
    {
        return (string) $this->http->request(
            'GET',
            '/v1/policy_bundles/' . rawurlencode($revision) . '/archive',
            raw: true,
        );
    }

    /**
     * `POST /v1/policy_bundles/verify` - Check a bundle without importing it: the detached signature
     * against this instance's signing keys (plus any `trusted_jwks` you supply) and the archive's
     * integrity. The SDK can do the same check offline.
     *
     * @param array{bundle:string,signature:string,trusted_jwks?:array{keys?:list<mixed>}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function verify(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/policy_bundles/verify',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/policy_bundles/import` - Import a signed bundle. The signature and the archive integrity
     * must both check out or nothing is touched (422). With `dry_run: true` it returns the diff of what
     * would change and applies nothing (needs only policy_bundles:read). Otherwise it applies everything
     * in one transaction: stores and models first, then the ABAC schema, templates, links and policies; a
     * changed policy appends a version. `prune` also deletes ABAC policies, templates and links the bundle
     * does not name (and the schema when it has none); FGA stores are never deleted.
     *
     * @param array{bundle:string,signature:string,trusted_jwks?:array{keys?:list<mixed>},dry_run?:bool,prune?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function import(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/policy_bundles/import',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
