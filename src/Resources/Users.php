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
     * @param array<string,mixed> $body {email_address, password?, first_name?, ...}
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/users', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body {first_name?, last_name?, public_metadata?, private_metadata?}
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/users/' . rawurlencode($id), body: $body);
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

    /** @return array<string,mixed> a ListPage of sessions */
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
}
