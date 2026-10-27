<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/oauth_clients` — first/third-party OAuth clients and their grants.
 *
 * `skip_logout_confirmation` (first-party clients only; the API answers 422
 * `VALIDATION_FAILED` with param `skip_logout_confirmation` on a third-party
 * client): when true, RP-initiated logout (`/oauth2/logout`) with this
 * client_id and an exactly registered post_logout_redirect_uri ends the session
 * and redirects straight back with `state`, without the confirmation page.
 * Client arrays carry it as a bool (false by default).
 */
final class OAuthClients extends Resource
{
    public readonly OAuthClientsAppSettings $appSettings;
    public readonly OAuthClientsWorkloadIdentity $workloadIdentity;
    public readonly OAuthClientsTrustedIssuers $trustedIssuers;
    public readonly OAuthClientsLoginRequests $loginRequests;
    public readonly OAuthClientsConsentRequests $consentRequests;
    public readonly OAuthClientGrants $grants;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->appSettings = new OAuthClientsAppSettings($http);
        $this->workloadIdentity = new OAuthClientsWorkloadIdentity($http);
        $this->trustedIssuers = new OAuthClientsTrustedIssuers($http);
        $this->loginRequests = new OAuthClientsLoginRequests($http);
        $this->consentRequests = new OAuthClientsConsentRequests($http);
        $this->grants = new OAuthClientGrants($http);
    }

    /** @return array<string,mixed> a ListPage of clients */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/oauth_clients');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/oauth_clients/' . rawurlencode($id));
    }

    /**
     * Create a client; the secret is revealed exactly once, here.
     *
     * @param array{name:string,redirect_uris:list<string>,allowed_scopes?:list<string>,grant_types?:list<string>,token_endpoint_auth_method?:string,logo_url?:string,first_party?:bool,skip_logout_confirmation?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/oauth_clients', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * Partially update a client. Send `skip_logout_confirmation` (bool) to turn the logout
     * confirmation page off or back on for a first-party client.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/oauth_clients/' . rawurlencode($id), body: $body);
    }

    /**
     * Mint a new secret, revealed exactly once. The previous secret keeps working for
     * `overlap_seconds` (default 86400 = 24 hours, at most 604800 = 7 days; `0` revokes it now,
     * e.g. after a leak). The response carries `client_secret`, `overlap_seconds` and
     * `previous_secret_expires_at` (epoch ms, or null when the old secret already stopped).
     *
     * @param array{overlap_seconds?:int}|null $body
     *
     * @return array<string,mixed> the client with a freshly revealed secret
     */
    public function rotateSecret(string $id, ?array $body = null, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/oauth_clients/' . rawurlencode($id) . '/rotate_secret',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/oauth_clients/' . rawurlencode($id));
    }

    /**
     * `GET /v1/oauth_clients/:id/backchannel_deliveries` — the client's back-channel logout
     * delivery log, newest first (`limit`, `starting_after`). Each delivery has `status`,
     * `http_status`, `latency_ms`, `error`, `location` (a 3xx is never followed) and `test`.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array{object:string,data:list<array<string,mixed>>,has_more:bool,next_cursor:string|null}
     */
    public function listBackchannelDeliveries(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/oauth_clients/' . rawurlencode($id) . '/backchannel_deliveries', query: $params);
    }

    /**
     * `POST /v1/oauth_clients/:id/backchannel_logout/test` — send a test back-channel logout now
     * (`ok`, `status`, `latency_ms`, `error`, `location`, `blocked_reason`, `body_excerpt`).
     *
     * @return array<string,mixed>
     */
    public function testBackchannelLogout(string $id): array
    {
        return $this->http->request('POST', '/v1/oauth_clients/' . rawurlencode($id) . '/backchannel_logout/test');
    }

    /**
     * `POST /v1/oauth_clients/import` — bulk-import OAuth clients for a migration (idempotent per
     * client_id).
     *
     * @param list<mixed> $clients
     *
     * @return array<string,mixed>
     */
    public function import(array $clients, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/oauth_clients/import',
            body: ['clients' => $clients],
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/oauth_clients/:id/exchange_policy` - Read the token-exchange policy of an OAuth client (the
     * defaults, with configured=false, when none is set).
     *
     * @return array<string,mixed>
     */
    public function getExchangePolicy(string $id): array
    {
        return $this->http->request('GET', '/v1/oauth_clients/' . rawurlencode($id) . '/exchange_policy');
    }

    /**
     * `PUT /v1/oauth_clients/:id/exchange_policy` - Set the token-exchange policy of an OAuth client:
     * allowed audiences and scopes, whether it may impersonate (no actor_token), whether delegation needs
     * may_act, and the maximum act-chain depth. Audited as oauth_exchange_policy.updated.
     *
     * @param array{allowed_audiences?:list<string>|null,allowed_scopes?:list<string>|null,id_jag_audiences?:list<string>|null,allow_impersonation?:bool,require_may_act?:bool,max_delegation_depth?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setExchangePolicy(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_clients/' . rawurlencode($id) . '/exchange_policy',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/oauth_clients/:id/exchange_policy` - Remove the token-exchange policy of an OAuth
     * client, restoring the defaults.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function deleteExchangePolicy(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/oauth_clients/' . rawurlencode($id) . '/exchange_policy',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/oauth_clients/:id/profile` - Assign a policy profile to an OAuth client (profile null
     * clears it). The client must already conform (auth method, redirect URIs); the profile is then
     * enforced at /oauth2/authorize, /oauth2/par and /oauth2/token.
     *
     * @param array{profile:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setProfile(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_clients/' . rawurlencode($id) . '/profile',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/oauth_clients/:id/delegated_ui` - Set (or clear, with null) the URLs of your own sign-in
     * and consent pages for an OAuth client. /oauth2/authorize then sends the browser there with a
     * login_challenge / consent_challenge instead of Atlas’s pages. A client URL overrides the
     * instance-wide oauthProvider.loginUrl / consentUrl.
     *
     * @param array{login_url?:string|null,consent_url?:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setDelegatedUi(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_clients/' . rawurlencode($id) . '/delegated_ui',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
