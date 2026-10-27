<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/oauth_login_requests` - 3 operations.
 */
final class OAuthClientsLoginRequests extends Resource
{
    /**
     * `GET /v1/oauth_login_requests` - Read a login challenge: which application is signing a user in,
     * with what scopes, and who (if anyone) is already signed in on this browser.
     *
     * @param array{login_challenge?:string} $params
     *
     * @return array<string,mixed>
     */
    public function get(array $params = []): array
    {
        return $this->http->request('GET', '/v1/oauth_login_requests', query: $params);
    }

    /**
     * `PUT /v1/oauth_login_requests/accept` - Accept a login challenge: the visitor authenticated as
     * `subject` (an Atlas user id). Returns redirect_to; when the browser follows it Atlas signs the user
     * in and continues the authorization. The returned link is single-use and valid for five minutes.
     *
     * @param array{subject:string,remember?:bool,amr?:list<string>,acr?:string} $body
     * @param array{dry_run?:'true'|'false',login_challenge?:string} $params
     *
     * @return array<string,mixed>
     */
    public function accept(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_login_requests/accept',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/oauth_login_requests/reject` - Reject a login challenge. Following redirect_to returns the
     * application to its redirect_uri with the OAuth error.
     *
     * @param array{error?:'access_denied'|'login_required'|'interaction_required'|'consent_required'|'account_selection_required'|'unauthorized_client'|'temporarily_unavailable'|'server_error',error_description?:string} $body
     * @param array{dry_run?:'true'|'false',login_challenge?:string} $params
     *
     * @return array<string,mixed>
     */
    public function reject(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_login_requests/reject',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
