<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/desktop_mfa` - 8 operations.
 */
final class DesktopMfa extends Resource
{
    /**
     * `GET /v1/desktop_mfa/policy` - The desktop-login MFA policy: who must pass a second factor at OS
     * login, the allowed factors and the offline grace window.
     *
     * @return array<string,mixed>
     */
    public function getPolicy(): array
    {
        return $this->http->request('GET', '/v1/desktop_mfa/policy');
    }

    /**
     * `PUT /v1/desktop_mfa/policy` - Change the desktop-login MFA policy. Send only the fields to change.
     * `applies_to: "selected"` needs at least one user or organization while enabled.
     *
     * @param array{enabled?:bool,applies_to?:string,user_ids?:list<string>,org_ids?:list<string>,allowed_factors?:list<string>,offline_enabled?:bool,offline_grace_hours?:int|float,offline_pin_required?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function updatePolicy(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/desktop_mfa/policy',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/desktop_mfa/enrolment_codes` - Issue a single-use code a user types into the workstation
     * agent to enrol it. Valid for `expires_in_minutes` (default 60, at most 7 days). Shown once.
     *
     * @param array{user_id?:string,expires_in_minutes?:int|float} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function createEnrolmentCode(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/desktop_mfa/enrolment_codes',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/desktop_mfa/devices` - List enrolled workstations, optionally for one `user_id`.
     *
     * @param array{user_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function listDevices(array $params = []): array
    {
        return $this->http->request('GET', '/v1/desktop_mfa/devices', query: $params);
    }

    /**
     * `GET /v1/desktop_mfa/devices/:id` - Read one workstation with the offline verifiers issued to it.
     *
     * @return array<string,mixed>
     */
    public function getDevice(string $id): array
    {
        return $this->http->request('GET', '/v1/desktop_mfa/devices/' . rawurlencode($id));
    }

    /**
     * `DELETE /v1/desktop_mfa/devices/:id` - Delete a workstation and its device key.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function deleteDevice(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/desktop_mfa/devices/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/desktop_mfa/devices/:id/revoke` - Revoke a workstation. Its next call is refused with
     * `device_status: revoked` and it wipes its offline cache; offline unlock stops working at the latest
     * when the cached verifier expires.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revokeDevice(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/desktop_mfa/devices/' . rawurlencode($id) . '/revoke',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/desktop_mfa/devices/:id/revoke_offline_cache` - Void every offline verifier issued to the
     * workstation without revoking it. It receives a fresh one on its next sync.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revokeOfflineCache(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/desktop_mfa/devices/' . rawurlencode($id) . '/revoke_offline_cache',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
