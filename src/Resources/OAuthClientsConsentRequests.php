<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/oauth_consent_requests` - 3 operations.
 */
final class OAuthClientsConsentRequests extends Resource
{
    /**
     * `GET /v1/oauth_consent_requests` - Read a consent challenge: the signed-in user, the application and
     * the scopes, audience, authorization details and organization it asks for.
     *
     * @param array{consent_challenge?:string} $params
     *
     * @return array<string,mixed>
     */
    public function get(array $params = []): array
    {
        return $this->http->request('GET', '/v1/oauth_consent_requests', query: $params);
    }

    /**
     * `PUT /v1/oauth_consent_requests/accept` - Accept a consent challenge, optionally narrowing the
     * granted scopes. Following redirect_to (in the same browser that started the flow) records the
     * consent unless remember is false, and returns the application to its redirect_uri with a code.
     *
     * @param array{grant_scope?:list<string>,remember?:bool} $body
     * @param array{dry_run?:'true'|'false',consent_challenge?:string} $params
     *
     * @return array<string,mixed>
     */
    public function accept(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_consent_requests/accept',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/oauth_consent_requests/reject` - Reject a consent challenge. Following redirect_to returns
     * the application to its redirect_uri with the OAuth error.
     *
     * @param array{error?:'access_denied'|'login_required'|'interaction_required'|'consent_required'|'account_selection_required'|'unauthorized_client'|'temporarily_unavailable'|'server_error',error_description?:string} $body
     * @param array{dry_run?:'true'|'false',consent_challenge?:string} $params
     *
     * @return array<string,mixed>
     */
    public function reject(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_consent_requests/reject',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
