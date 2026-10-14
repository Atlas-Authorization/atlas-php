<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/machines` — machine (M2M) identities, the device registry and enrolment tokens.
 */
final class Machines extends Resource
{
    /**
     * `POST /v1/machines` — create a machine identity. The machine secret is returned once.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/machines', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/machines` — machine identities, filterable by status, hostname, owner_user_id,
     * organization_id and metadata.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/machines', query: $params);
    }

    /**
     * `GET /v1/machines/:id` — one machine identity.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/machines/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/machines/:id` — rename a machine.
     *
     * @return array<string,mixed>
     */
    public function rename(string $id, string $name): array
    {
        return $this->http->request('PATCH', '/v1/machines/' . rawurlencode($id), body: ['name' => $name]);
    }

    /**
     * `PATCH /v1/machines/:id/metadata` — merge device metadata (hostname, os, app_version, last_seen_at).
     *
     * @param array<string,mixed> $metadata
     *
     * @return array<string,mixed>
     */
    public function patchMetadata(string $id, array $metadata): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/machines/' . rawurlencode($id) . '/metadata',
            body: ['metadata' => $metadata === [] ? new \stdClass() : $metadata],
        );
    }

    /**
     * `DELETE /v1/machines/:id` — delete a machine identity.
     *
     * @return array<string,mixed>
     */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/machines/' . rawurlencode($id));
    }

    /**
     * `POST /v1/machines/:id/approve` — approve a device that enrolled under an approval-gated token.
     *
     * @return array<string,mixed>
     */
    public function approve(string $id): array
    {
        return $this->http->request('POST', '/v1/machines/' . rawurlencode($id) . '/approve');
    }

    /**
     * `POST /v1/machines/:id/revoke` — revoke a machine; it stops minting tokens immediately.
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id): array
    {
        return $this->http->request('POST', '/v1/machines/' . rawurlencode($id) . '/revoke');
    }

    /**
     * `POST /v1/machines/:id/rotate_key` — register a new device public key; the old key stops verifying.
     *
     * @return array<string,mixed>
     */
    public function rotateKey(string $id, string $publicKeyPem): array
    {
        return $this->http->request(
            'POST',
            '/v1/machines/' . rawurlencode($id) . '/rotate_key',
            body: ['public_key_pem' => $publicKeyPem],
        );
    }

    /**
     * `POST /v1/machines/:id/rotate_secret` — rotate the machine secret; the new one is shown once.
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/machines/' . rawurlencode($id) . '/rotate_secret');
    }

    /**
     * `GET /v1/machines/:id/organization_memberships` — the organization the machine belongs to (zero or
     * one entry).
     *
     * @return array<string,mixed>
     */
    public function organizationMemberships(string $id): array
    {
        return $this->http->request('GET', '/v1/machines/' . rawurlencode($id) . '/organization_memberships');
    }

    /**
     * `POST /v1/machine_enrolment_tokens` — mint a machine enrolment token. The token secret is returned
     * once.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function createEnrolmentToken(array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/machine_enrolment_tokens',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/machine_enrolment_tokens` — enrolment tokens (never the secret), newest first.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function listEnrolmentTokens(array $params = []): array
    {
        return $this->http->request('GET', '/v1/machine_enrolment_tokens', query: $params);
    }

    /**
     * `DELETE /v1/machine_enrolment_tokens/:id` — delete an enrolment token; it can no longer enrol
     * devices.
     *
     * @return array<string,mixed>
     */
    public function deleteEnrolmentToken(string $id): array
    {
        return $this->http->request('DELETE', '/v1/machine_enrolment_tokens/' . rawurlencode($id));
    }

    /**
     * `POST /v1/m2m_tokens/verify` — verify a presented machine secret: { valid, machine_id }.
     *
     * @return array<string,mixed>
     */
    public function verifyToken(string $token): array
    {
        return $this->http->request('POST', '/v1/m2m_tokens/verify', body: ['token' => $token]);
    }
}
