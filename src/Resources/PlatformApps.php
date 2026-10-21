<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/platform/apps` — apps (instances) your platform provisions and owns.
 */
final class PlatformApps extends Resource
{
    /**
     * `GET /v1/platform/apps` — the apps this platform owns (include_archived, archived, tag).
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/platform/apps', query: $params);
    }

    /**
     * `POST /v1/platform/apps` — provision a new app. Its secret key is returned once.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/platform/apps', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/platform/apps/:id` — one owned app.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/platform/apps/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/platform/apps/:id` — update an owned app's name, allowed_origins or tags. An
     * `allowed_origins` entry is an origin string or `['origin' => …, 'integration' => …]`
     * (`redirect` | `embedded` | `auto`), as on `instance->update()`. App objects carry
     * `frontend_api_host`, `issuer`, `discovery_url`, `jwks_uri` and `allowed_origin_integrations`.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/platform/apps/' . rawurlencode($id), body: $body);
    }

    /**
     * `DELETE /v1/platform/apps/:id` — disable an owned app (soft and reversible).
     *
     * @return array<string,mixed>
     */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/platform/apps/' . rawurlencode($id));
    }

    /**
     * `POST /v1/platform/apps/:id/archive` — hide an owned app from the default listing (it keeps
     * working).
     *
     * @return array<string,mixed>
     */
    public function archive(string $id): array
    {
        return $this->http->request('POST', '/v1/platform/apps/' . rawurlencode($id) . '/archive');
    }

    /**
     * `POST /v1/platform/apps/:id/unarchive` — return an archived app to the default listing.
     *
     * @return array<string,mixed>
     */
    public function unarchive(string $id): array
    {
        return $this->http->request('POST', '/v1/platform/apps/' . rawurlencode($id) . '/unarchive');
    }

    /**
     * `POST /v1/platform/apps/bulk_delete` — disable up to 100 owned apps in one call.
     *
     * @param list<mixed> $ids
     *
     * @return array<string,mixed>
     */
    public function bulkDelete(array $ids): array
    {
        return $this->http->request('POST', '/v1/platform/apps/bulk_delete', body: ['ids' => $ids]);
    }

    /**
     * `POST /v1/platform/apps/:id/instances` — provision an additional instance under an owned app. Its
     * secret key is returned once.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function createInstance(string $id, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/platform/apps/' . rawurlencode($id) . '/instances',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/platform/apps/:id/instances` — every instance under an owned app.
     *
     * @return array<string,mixed>
     */
    public function listInstances(string $id): array
    {
        return $this->http->request('GET', '/v1/platform/apps/' . rawurlencode($id) . '/instances');
    }

    /**
     * `POST /v1/platform/apps/:id/instances/:instanceId/rotate_secret` — rotate one instance's secret key
     * (no overlap). The new secret is returned once.
     *
     * @return array<string,mixed>
     */
    public function rotateInstanceSecret(string $id, string $instanceId, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/platform/apps/' . rawurlencode($id) . '/instances/' . rawurlencode($instanceId) . '/rotate_secret',
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/platform/apps/:id/rotate_secret` — rotate the app's secret key with an overlap window
     * (overlap_seconds). The new secret is returned once.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/platform/apps/' . rawurlencode($id) . '/rotate_secret',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/platform/apps/:id/secret_keys` — the app's currently valid secret keys (metadata only).
     *
     * @return array<string,mixed>
     */
    public function listSecretKeys(string $id): array
    {
        return $this->http->request('GET', '/v1/platform/apps/' . rawurlencode($id) . '/secret_keys');
    }

    /**
     * `POST /v1/platform/apps/:id/transfer` — start a transfer of an owned app; returns a one-time
     * transfer_token.
     *
     * @return array<string,mixed>
     */
    public function transfer(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/platform/apps/' . rawurlencode($id) . '/transfer',
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/platform/apps/accept_transfer` — accept an app transfer by its transfer_token.
     *
     * @return array<string,mixed>
     */
    public function acceptTransfer(string $transferToken, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/platform/apps/accept_transfer',
            body: ['transfer_token' => $transferToken],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/platform/accountless_apps` — mint an app owned by no platform and return a one-time
     * claim_token.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function createAccountless(array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/platform/accountless_apps',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/platform/apps/claim` — claim an accountless app by its claim_token. Its secret key is
     * returned once.
     *
     * @return array<string,mixed>
     */
    public function claim(string $claimToken, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/platform/apps/claim',
            body: ['claim_token' => $claimToken],
            idempotencyKey: $idempotencyKey,
        );
    }
}
