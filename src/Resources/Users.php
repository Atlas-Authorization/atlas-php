<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/users` — user records, their emails, identities, OAuth tokens and grants.
 *
 * Every method returns the decoded JSON as an associative array (a page for
 * list, a user object otherwise). `private_metadata` is never returned.
 */
final class Users extends Resource
{
    /**
     * `GET /v1/users` — cursor-paginated.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed> a CursorPage: `{ data, has_more, next_cursor }`
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/users', query: $params);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id));
    }

    /**
     * `notify: false` (the default) guarantees no email, SMS or push is sent to the user as a consequence
     * of the create.
     *
     * @param array<string,mixed> $body {email_address, password?, first_name?, ..., notify?, referral?, referral_code?}
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/users', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body {first_name?, last_name?, external_id?, public_metadata?, private_metadata?, referral_code?}
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/users/' . rawurlencode($id), body: $body);
    }

    /**
     * Set (or clear with null) the user's `external_id`. With referral attribution on, Atlas
     * forwards the change to the referral platform as an alias (old id, or the Atlas user id,
     * to the new one).
     *
     * @return array<string,mixed>
     */
    public function setExternalId(string $id, ?string $externalId): array
    {
        return $this->http->request('PATCH', '/v1/users/' . rawurlencode($id), body: ['external_id' => $externalId]);
    }

    /**
     * `PUT /v1/users/:id/metadata` — replaces the named bags wholesale.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function replaceMetadata(string $id, array $body): array
    {
        return $this->http->request('PUT', '/v1/users/' . rawurlencode($id) . '/metadata', body: $body);
    }

    /** @return array<string,mixed> */
    public function ban(string $id): array
    {
        return $this->http->request('POST', '/v1/users/' . rawurlencode($id) . '/ban');
    }

    /** @return array<string,mixed> */
    public function unban(string $id): array
    {
        return $this->http->request('POST', '/v1/users/' . rawurlencode($id) . '/unban');
    }

    /**
     * @param array{duration_in_seconds?:int} $body
     *
     * @return array<string,mixed>
     */
    public function lock(string $id, array $body = []): array
    {
        return $this->http->request('POST', '/v1/users/' . rawurlencode($id) . '/lock', body: $body);
    }

    /** @return array<string,mixed> */
    public function unlock(string $id): array
    {
        return $this->http->request('POST', '/v1/users/' . rawurlencode($id) . '/unlock');
    }

    /**
     * `POST /v1/users/:id/referral/flag` — mark the user's referral fraudulent. The user's
     * `referral` reads `flagged: true`, the referral platform is sent a `fraud_confirmed`
     * signal (when referrals are on) and the action is audited; the account is not changed.
     * 404 when the user has no referral. `$reason` (at most 500 characters) is sent to the
     * platform.
     *
     * Users carry `referral` (`status`: `pending`, `attributed`, `none`, `failed`, `held` or
     * `flagged`; `partner_id`, `partner_external_ref`, `referrer_external_id`,
     * `referrer_user_id`, `matched_via`, `referral_status` (the platform's own status), `notes`,
     * `risk_level`, `flagged`; null when never reported), `referral_code` and
     * `referral_link`; `create` accepts `referral` (`code`, `click_id`, `anon_id`) and
     * `referral_code`, `update` accepts `referral_code` (null clears; 409
     * `REFERRAL_CODE_TAKEN` when held).
     *
     * @return array<string,mixed> {object: user_referral, user_id, status, flagged, flag_reason, flagged_at, risk_level}
     */
    public function flagReferral(string $id, ?string $reason = null, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/referral/flag',
            body: $reason === null ? new \stdClass() : ['reason' => $reason],
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return array<string,mixed> a DeletedObject */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/users/' . rawurlencode($id));
    }

    /** @return array<string,mixed> */
    public function resetMfa(string $id): array
    {
        return $this->http->request('POST', '/v1/users/' . rawurlencode($id) . '/reset_mfa');
    }

    /** @return array<string,mixed> */
    public function deleteMfaFactor(string $id, string $factorId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/mfa/' . rawurlencode($factorId),
        );
    }

    /**
     * `GET /v1/users/:id/sessions` — the user's sessions. Each item carries `idle_expires_at` (epoch ms),
     * `last_active_organization_id` and `impersonated_by` (string or null), like {@see Sessions::get()}.
     *
     * @return array<string,mixed> a ListPage of sessions
     */
    public function listSessions(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/sessions');
    }

    /** @return array<string,mixed> */
    public function revokeSessions(string $id): array
    {
        return $this->http->request('POST', '/v1/users/' . rawurlencode($id) . '/sessions/revoke');
    }

    /** @return array<string,mixed> */
    public function addEmail(string $id, string $emailAddress): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/email_addresses',
            body: ['email_address' => $emailAddress],
        );
    }

    /** @return array<string,mixed> */
    public function verifyEmail(string $id, string $emailId): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/email_addresses/' . rawurlencode($emailId) . '/verify',
        );
    }

    /** @return array<string,mixed> */
    public function setPrimaryEmail(string $id, string $emailId): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/email_addresses/' . rawurlencode($emailId) . '/primary',
        );
    }

    /**
     * `GET /v1/users/:id/oauth_access_tokens/:provider` — a live provider credential.
     *
     * @return array<string,mixed>
     */
    public function getOAuthAccessToken(string $id, string $provider): array
    {
        return $this->http->request(
            'GET',
            '/v1/users/' . rawurlencode($id) . '/oauth_access_tokens/' . rawurlencode($provider),
        );
    }

    /** `GET /v1/users/:id/identities` — base identity plus linked provider accounts. @return array<string,mixed> */
    public function listIdentities(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/identities');
    }

    /**
     * `POST /v1/users/:id/identities` — merge a secondary user INTO this one.
     *
     * @param array{secondary_user_id:string} $body
     *
     * @return array<string,mixed>
     */
    public function linkIdentity(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/identities',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/external_accounts/connect` — start a backend-initiated
     * OAuth flow linking a NEW provider identity to this user.
     *
     * @param array{provider:string,redirect_url:string,additional_scopes?:list<string>} $body
     *
     * @return array<string,mixed>
     */
    public function connectExternalAccount(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/external_accounts/connect',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/:id/identities/:identityId` — extract a linked provider
     * identity into a new standalone user.
     *
     * @return array<string,mixed>
     */
    public function unlinkIdentity(string $id, string $identityId): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/identities/' . rawurlencode($identityId),
        );
    }

    /** `GET /v1/users/:id/grants` — the OAuth clients this user authorized. @return array<string,mixed> */
    public function listGrants(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/grants');
    }

    /** `DELETE /v1/users/:id/grants` — revoke every consent grant the user holds. @return array<string,mixed> */
    public function revokeAllGrants(string $id): array
    {
        return $this->http->request('DELETE', '/v1/users/' . rawurlencode($id) . '/grants');
    }

    /** `DELETE /v1/grants/:id` — revoke ONE consent grant (and its live tokens). @return array<string,mixed> */
    public function revokeGrant(string $grantId): array
    {
        return $this->http->request('DELETE', '/v1/grants/' . rawurlencode($grantId));
    }

    /**
     * `POST /v1/users/by_emails` — resolve a batch of email addresses to user ids. Pass
     * include_unverified: true for rights requests (DSAR), so an unconfirmed address is still found.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function byEmails(array $body): array
    {
        return $this->http->request('POST', '/v1/users/by_emails', body: $body);
    }

    /**
     * `POST /v1/users/:id/approval_requests` — push a number-matching "approve this action" prompt to the
     * user's registered devices. Show the returned match_number to the user.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function requestApproval(string $id, array $body): array
    {
        return $this->http->request('POST', '/v1/users/' . rawurlencode($id) . '/approval_requests', body: $body);
    }

    /**
     * `GET /v1/users/:id/approval_requests/:approvalId` — poll a transaction-approval request: pending,
     * approved, denied or expired.
     *
     * @return array<string,mixed>
     */
    public function getApprovalRequest(string $id, string $approvalId): array
    {
        return $this->http->request(
            'GET',
            '/v1/users/' . rawurlencode($id) . '/approval_requests/' . rawurlencode($approvalId),
        );
    }

    /**
     * `POST /v1/users/:id/consents` — record a versioned consent grant (or a withdrawal with status:
     * "withdrawn").
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function recordConsent(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/consents',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/users/:id/consents` — the user's recorded consents with their versions and timestamps.
     *
     * @return array<string,mixed>
     */
    public function listConsents(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/consents');
    }

    /**
     * `POST /v1/users/:id/deletion` — schedule a graceful deletion with a cancellation window
     * (grace_seconds).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function scheduleDeletion(string $id, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/deletion',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/deletion/cancel` — cancel a scheduled deletion inside its grace window.
     *
     * @return array<string,mixed>
     */
    public function cancelDeletion(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/deletion/cancel',
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/deletion/finalize` — finalize a scheduled deletion whose grace window has
     * lapsed.
     *
     * @return array<string,mixed>
     */
    public function finalizeDeletion(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/deletion/finalize',
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/directives` — impose a directive on a user (suspended_until, read_only,
     * forced_logout, reason).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function imposeDirective(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/directives',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/:id/directives` — lift a previously imposed directive.
     *
     * @return array<string,mixed>
     */
    public function liftDirective(string $id): array
    {
        return $this->http->request('DELETE', '/v1/users/' . rawurlencode($id) . '/directives');
    }

    /**
     * `POST /v1/users/:id/erasure_acks` — acknowledge that your app purged its copy of the user's data
     * during a scheduled deletion (optionally contributing a payload to the erasure export).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function acknowledgeErasure(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/erasure_acks',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/users/:id/erasure_export` — the erasure export: Atlas's data about the user plus every
     * app's contributed payload.
     *
     * @return array<string,mixed>
     */
    public function erasureExport(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/erasure_export');
    }

    /**
     * `DELETE /v1/users/:id/identities/by-provider/:provider` — hard-remove a provider identity from a
     * user (undo a mis-import). The account's only sign-in identity is refused.
     *
     * @return array<string,mixed>
     */
    public function removeIdentityByProvider(string $id, string $provider, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/identities/by-provider/' . rawurlencode($provider),
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/identities/import` — link a known provider identity without an interactive OAuth
     * flow (migrations).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function importIdentity(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/identities/import',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/users/:id/notification_preferences` — the user's notification preferences, per category.
     *
     * @return array<string,mixed>
     */
    public function getNotificationPreferences(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/notification_preferences');
    }

    /**
     * `PATCH /v1/users/:id/notification_preferences` — change notification preferences: ['category' =>
     * enabled].
     *
     * @param array<string,mixed> $preferences
     *
     * @return array<string,mixed>
     */
    public function updateNotificationPreferences(string $id, array $preferences): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/users/' . rawurlencode($id) . '/notification_preferences',
            body: ['preferences' => $preferences === [] ? new \stdClass() : $preferences],
        );
    }

    /**
     * `GET /v1/users/:id/organization_memberships` — the organizations the user belongs to, with role,
     * plan and join time. Cursor-paginated.
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function listOrganizationMemberships(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/users/' . rawurlencode($id) . '/organization_memberships',
            query: $params,
        );
    }

    /**
     * `DELETE /v1/users/:id/passkeys/:passkeyId` — remove one of the user's passkeys.
     *
     * @return array<string,mixed>
     */
    public function deletePasskey(string $id, string $passkeyId, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/passkeys/' . rawurlencode($passkeyId),
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/password` — set (reset) the user's password. Pass sign_out_of_other_sessions:
     * true to end their other sessions.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function setPassword(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/password',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/phone_numbers` — add a phone number (starts unverified).
     *
     * @return array<string,mixed>
     */
    public function addPhoneNumber(string $id, string $phoneNumber, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/phone_numbers',
            body: ['phone_number' => $phoneNumber],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/:id/phone_numbers/:pid` — remove a phone number from the user.
     *
     * @return array<string,mixed>
     */
    public function deletePhoneNumber(string $id, string $phoneNumberId, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/phone_numbers/' . rawurlencode($phoneNumberId),
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/phone_numbers/:pid/verify` — mark a phone number verified.
     *
     * @return array<string,mixed>
     */
    public function verifyPhoneNumber(string $id, string $phoneNumberId, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/phone_numbers/' . rawurlencode($phoneNumberId) . '/verify',
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/:id/profile_image` — clear the user's profile image.
     *
     * @return array<string,mixed>
     */
    public function deleteProfileImage(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/profile_image',
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/:id/progression/events` — append an idempotent progression event (XP, level or
     * state) for the user.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function recordProgressionEvent(string $id, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/progression/events',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/users/:id/resolved` — the user as a flat identity: primary email, email_verified, name,
     * image_url and linked provider identities.
     *
     * @return array<string,mixed>
     */
    public function resolved(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/resolved');
    }

    /**
     * `GET /v1/users/:id/sign_in_history` — the user's successful sign-ins, newest first. Cursor-paginated
     * (limit, starting_after).
     *
     * @param array<string,mixed> $params query parameters
     *
     * @return array<string,mixed>
     */
    public function signInHistory(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/sign_in_history', query: $params);
    }

    /**
     * `GET /v1/users/:id/state` — the user's portable state: progression, tier, flair, entitlements,
     * directives and roles.
     *
     * @return array<string,mixed>
     */
    public function state(string $id): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/state');
    }
}
