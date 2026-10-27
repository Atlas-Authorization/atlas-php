<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/users/:id/guardians` - 3 operations.
 */
final class UsersGuardians extends Resource
{
    /**
     * `POST /v1/users/:id/guardians` - Ask a parent or guardian to approve a minor's account. Name them by
     * email or by guardian_user_id (their address is used). Returns the pending link with a one-time
     * consent_url that expires after compliance.minors.consentTtlHours. With send_email: true Atlas mails
     * it through the application's own email provider (422 when none is set up); otherwise deliver
     * consent_url yourself. → 201.
     *
     * @param array{email?:string,guardian_user_id?:string,relationship?:string,send_email?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function request(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/guardians',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/guardians/:linkId/approve` - Record consent obtained another way (a signed form,
     * a verified call): the link becomes active with consent_method admin and the account is consented.
     * 409 for a revoked link.
     *
     * @param array{note?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function approve(string $id, string $linkId, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/guardians/' . rawurlencode($linkId) . '/approve',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/:id/guardians/:linkId` - Withdraw a guardian link. When no active guardian is
     * left, the account needs consent again.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id, string $linkId, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/guardians/' . rawurlencode($linkId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
