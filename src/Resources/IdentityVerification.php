<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1` - 7 operations.
 */
final class IdentityVerification extends Resource
{
    /**
     * `GET /v1/identity_verification/settings` - Whether identity verification is on, and which connector
     * does the checking.
     *
     * @return array<string,mixed>
     */
    public function settings(): array
    {
        return $this->http->request('GET', '/v1/identity_verification/settings');
    }

    /**
     * `PUT /v1/identity_verification/settings` - Turn identity verification on or off and choose the
     * connector (a Persona, Onfido, Veriff or Stripe Identity service connector; its credentials stay in
     * the vault). `as_challenge` offers a verified identity as the custom second step of sign-in (the
     * custom challenge factor must be enabled).
     *
     * @param array{enabled?:bool,connector_id?:string|null,as_challenge?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function updateSettings(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/identity_verification/settings',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/identity_verifications` - List checks by status (default `needs_review`, the review queue),
     * newest first.
     *
     * @param array{status?:'pending'|'approved'|'declined'|'needs_review',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/identity_verifications', query: $params);
    }

    /**
     * `GET /v1/identity_verifications/:id` - Read one check. A pending check is refreshed from the
     * verification service first.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/identity_verifications/' . rawurlencode($id));
    }

    /**
     * `POST /v1/identity_verifications/:id/decide` - Decide a check that is waiting for review (or
     * override a pending one) as `approved` or `declined`. The outcome is written to the user as
     * `verification.identity`. 409 when the check is already approved or declined.
     *
     * @param array{decision?:'approved'|'declined',reason?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function decide(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/identity_verifications/' . rawurlencode($id) . '/decide',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/users/:id/identity_verifications` - A user's identity checks, newest first.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function forUser(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/users/' . rawurlencode($id) . '/identity_verifications',
            query: $params,
        );
    }

    /**
     * `POST /v1/users/:id/identity_verifications` - Start an identity check for a user and get the
     * verification service's hosted page (`url`) to send them to. A check started in the last 30 minutes
     * and still pending is returned instead of a new one; at most 5 checks per user per hour. 409 when
     * identity verification is not turned on.
     *
     * @param array{redirect_url?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function start(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/identity_verifications',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
