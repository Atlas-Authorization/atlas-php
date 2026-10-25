<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * Platform-key (`psk_…`) surface: apps you provision (`apps`), Sign in with Atlas apps
 * (`atlasIdApps`) and their design images (`designAssets`).
 *
 * Construct the client with your platform key to use it:
 *
 *     $platform = (new \Atlas\Client('psk_…'))->platform;
 */
final class Platform extends Resource
{
    public readonly PlatformApps $apps;
    public readonly PlatformAtlasIdApps $atlasIdApps;
    public readonly PlatformDesignAssets $designAssets;
    public readonly PlatformAtlasIdPublisher $atlasIdPublisher;
    public readonly PlatformDomains $domains;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->apps = new PlatformApps($http);
        $this->atlasIdApps = new PlatformAtlasIdApps($http);
        $this->designAssets = new PlatformDesignAssets($http);
        $this->atlasIdPublisher = new PlatformAtlasIdPublisher($http);
        $this->domains = new PlatformDomains($http);
    }

    /**
     * `GET /v1/platform/config_changelog` — config change history for the account's Sign in with
     * Atlas apps (`action`, `limit`, `starting_after`, `since`).
     *
     * @param array{action?:string,limit?:int,starting_after?:string,since?:string} $params
     *
     * @return array{object:string,data:list<array<string,mixed>>,has_more?:bool,next_cursor?:string|null}
     */
    public function configChangelog(array $params = []): array
    {
        return $this->http->request('GET', '/v1/platform/config_changelog', query: $params);
    }

    /**
     * `GET /v1/platform/whoami` — who the platform key is (kind, scopes, owner, expiry, the account).
     *
     * @return array<string,mixed>
     */
    public function whoami(): array
    {
        return $this->http->request('GET', '/v1/platform/whoami');
    }

    /**
     * `GET /v1/platform/keys` — the account's platform keys.
     *
     * @return array<string,mixed>
     */
    public function listKeys(array $params = []): array
    {
        return $this->http->request('GET', '/v1/platform/keys', query: $params);
    }

    /**
     * `POST /v1/platform/keys` — create a platform key (the secret is shown once).
     *
     * @return array<string,mixed>
     */
    public function createKey(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/platform/keys', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `PATCH /v1/platform/keys/:id`.
     *
     * @return array<string,mixed>
     */
    public function updateKey(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/platform/keys/' . rawurlencode($id), body: $body);
    }

    /**
     * `POST /v1/platform/keys/:id/revoke`.
     *
     * @return array<string,mixed>
     */
    public function revokeKey(string $id): array
    {
        return $this->http->request('POST', '/v1/platform/keys/' . rawurlencode($id) . '/revoke');
    }

    /**
     * `GET /v1/platform/audit_logs` — the account's audit log (cursor-paginated; entries carry an `actor` object).
     *
     * @return array<string,mixed>
     */
    public function auditLogs(array $params = []): array
    {
        return $this->http->request('GET', '/v1/platform/audit_logs', query: $params);
    }

    /**
     * `GET /v1/platform/audit_streams`.
     *
     * @return array<string,mixed>
     */
    public function listAuditStreams(): array
    {
        return $this->http->request('GET', '/v1/platform/audit_streams');
    }

    /**
     * `POST /v1/platform/audit_streams`.
     *
     * @return array<string,mixed>
     */
    public function createAuditStream(array $body): array
    {
        return $this->http->request('POST', '/v1/platform/audit_streams', body: $body);
    }

    /**
     * `PATCH /v1/platform/audit_streams/:id`.
     *
     * @return array<string,mixed>
     */
    public function updateAuditStream(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/platform/audit_streams/' . rawurlencode($id), body: $body);
    }

    /**
     * `DELETE /v1/platform/audit_streams/:id`.
     *
     * @return array<string,mixed>
     */
    public function deleteAuditStream(string $id): array
    {
        return $this->http->request('DELETE', '/v1/platform/audit_streams/' . rawurlencode($id));
    }

    /**
     * `POST /v1/platform/audit_streams/:id/test`.
     *
     * @return array<string,mixed>
     */
    public function testAuditStream(string $id): array
    {
        return $this->http->request('POST', '/v1/platform/audit_streams/' . rawurlencode($id) . '/test');
    }
}
