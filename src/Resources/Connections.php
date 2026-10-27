<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/connections` - 18 operations.
 */
final class Connections extends Resource
{
    public readonly ConnectionsOauthClients $oauthClients;
    public readonly ConnectionsWebhooks $webhooks;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->oauthClients = new ConnectionsOauthClients($http);
        $this->webhooks = new ConnectionsWebhooks($http);
    }

    /**
     * `POST /v1/connections` - Define a connection: an owner (an organization or a user) and a third-party
     * API. `kind` is oauth (rides a linked provider account; for an organization, the account of the
     * member who shared it), api_key or client_credentials (a credential the owner enters).
     * `allowed_hosts` lists the only hosts the relay may call. An owner may hold several connections of
     * one provider (one per provider account): create one per account, then call `POST
     * /v1/connections/{id}/connect`. A connection is never a sign-in method.
     *
     * @param array{owner:array{type:'organization'|'user',id:string},kind:mixed,provider:string,name?:string,allowed_hosts:list<string>,config?:array<string,mixed>,allowed_methods?:list<'GET'|'HEAD'|'POST'|'PUT'|'PATCH'|'DELETE'>,environment?:'production'|'sandbox',host_params?:array<string,mixed>,purpose?:'data'} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/connections` - List connections, newest first, with each one's live status. Filter by owner
     * or provider.
     *
     * @param array{owner_type?:'organization'|'user',owner_id?:string,provider?:string,environment?:'production'|'sandbox',status?:'connected'|'needs_reauth'|'revoked'|'error'|'not_connected',starting_after?:string,limit?:int} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/connections', query: $params);
    }

    /**
     * `GET /v1/connections/:id` - Read one connection with its current status.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/connections/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/connections/:id` - Rename a connection, change its allowed hosts or settings, transfer it
     * to another owner with `owner` (the stored tokens move with it, no reconnect), change `environment`
     * or `host_params` (an oauth connection that already holds a token becomes needs_reauth, because the
     * token belongs to the old host), or (organization-owned oauth) set `shared_by_user_id` to copy that
     * member's linked account into the connection.
     *
     * @param array{name?:string,allowed_hosts?:list<string>,config?:array<string,mixed>,shared_by_user_id?:string,owner?:array{type:'organization'|'user',id:string},environment?:'production'|'sandbox',host_params?:array<string,mixed>,allowed_methods?:list<'GET'|'HEAD'|'POST'|'PUT'|'PATCH'|'DELETE'>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/connections/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/connections/:id` - Delete a connection and its stored credential (204). With
     * `revoke=true` the token is first revoked at the provider and the answer is 200 with what the
     * provider said (provider_revoked true, false or "unsupported", provider_error, local_cleared); the
     * connection is deleted either way.
     *
     * @param array{dry_run?:'true'|'false',revoke?:bool} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/connections/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/connections/:id/credentials` - Enter the credential for a non-OAuth connection (api_key,
     * bearer, basic, custom_header, hmac_secret, client_credentials, jwt_bearer, oauth1,
     * github_app_installation). Write-only: it is sealed in the vault and never returned. Replaces the
     * earlier credential as a new vault version; use POST .../credentials/rotate to keep the old version
     * valid for an overlap window.
     *
     * @param array{api_key?:string,token?:string,username?:string,password?:string,value?:string,secret?:string,client_id?:string,client_secret?:string,token_url?:string,scopes?:list<string>,private_key?:string,generate_key?:bool,service_account_json?:string|array<string,mixed>,assertion?:string,consumer_key?:string,consumer_secret?:string,token_secret?:string,config?:array<string,mixed>,expires_at?:int|string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setCredentials(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/connections/' . rawurlencode($id) . '/credentials',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/connections/:id/connect` - Start the provider authorization for this connection and return
     * the URL to send the owner to. The tokens are stored on the connection itself, never on a user: no
     * external account is created, no sign-in method is added, no email is proven and no step-up is
     * needed. `host_params` fill the provider's host templates for this connection (validated against a
     * per-provider allowlist; 400 `host_params.<key>` otherwise). Reconnecting the same provider account
     * updates the same connection; a different account answers 409 unless `create_if_new` is true. An
     * owner OAuth client (`PUT /v1/connections/oauth_clients`) overrides the instance client.
     *
     * @param array{redirect_url:string,host_params?:array<string,mixed>,environment?:'production'|'sandbox',additional_scopes?:list<string>,login_hint?:string,display?:'redirect'|'popup',create_if_new?:bool,connect_user_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function connect(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/' . rawurlencode($id) . '/connect',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/connections/:id/revoke` - Revoke the stored token at the provider, delete it from the
     * vault and mark the connection revoked (connection.revoked is sent once). The response is the
     * connection plus what the provider said: provider_revoked is true only when it confirmed,
     * "unsupported" when it has no revoke endpoint, false when it refused or was unreachable
     * (provider_error says why). The local token is cleared in every case. Connect again to use it.
     *
     * @param array<string,mixed> $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/' . rawurlencode($id) . '/revoke',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/connections/:id/oauth1/sign` - Have Atlas sign a request with an oauth1 connection
     * (NetSuite token-based authentication: HMAC-SHA256 with the realm; X Ads and other three-legged
     * providers: HMAC-SHA1) and return the `Authorization: OAuth ...` header. The consumer and token
     * secrets never leave Atlas. The URL must be https on an allowed host; a form-encoded body is part of
     * the signature. Needs connections:sign.
     *
     * @param array{method?:string,url:string,body?:string,content_type?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function oauth1Sign(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/' . rawurlencode($id) . '/oauth1/sign',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/connections/:id/relay` - Make a provider API call server-side with this connection's
     * credential, so the credential never reaches your code: Atlas injects an api_key (header or query),
     * bearer, basic or custom header, signs with an hmac_secret or OAuth 1.0a, or sends a minted
     * client_credentials / jwt_bearer / GitHub App installation / admin consent token. Only https calls to
     * the connection's allowed_hosts are made, with the verbs in allowed_methods (default GET, HEAD,
     * POST). When the connection is not connected, or the provider rejects the credential, the answer is
     * 402 CONNECTION_REQUIRED with `meta.reason` (not_connected or needs_reauth) and, for a user-owned
     * OAuth connection when you pass `redirect_url`, `meta.authorize_url`. The audit log records the
     * method, host and status only.
     *
     * @param array{method?:'GET'|'HEAD'|'POST'|'PUT'|'PATCH'|'DELETE',url:string,headers?:array<string,mixed>,body?:mixed,redirect_url?:string,connect_user_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function relay(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/' . rawurlencode($id) . '/relay',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/connections/:id/token` - The connection's live token for your backend. An oauth
     * connection's token is refreshed first when it is expired or about to be (single-flight across
     * processes, using the client that issued it and the connection's host_params). Built for sync
     * workers: the response carries an `ETag` and `Cache-Control: private, max-age=<seconds until just
     * before expires_at>`, so cache it until then; send `If-None-Match` and an unchanged, still-valid
     * token answers 304 with no body and no vault read; `min_ttl_seconds` refreshes first when less than
     * that much validity remains. A grant connection (client_credentials, jwt_bearer,
     * github_app_installation, admin_consent) has its token minted from the credential Atlas holds
     * instead, answered with `Cache-Control: no-store` (no ETag); pass `subject` to a jwt_bearer
     * connection to act as that user (Google domain-wide delegation). Includes the captured handles
     * (`metadata`, `token_response`) and any further named credentials. 402 CONNECTION_REQUIRED with
     * `meta.reason` not_connected, needs_reauth, revoked or refresh_failed when there is no usable token.
     * When the provider answers 401 to the token, call `POST /v1/connections/{id}/token/refresh` instead
     * of re-reading. Rate limit: 6,000 reads per minute per secret key and 300 per minute per connection
     * (RateLimit-* headers on every response, 429 with Retry-After beyond them). Reads are audited as
     * connection.token_read once per connection per minute with the read count (a read that refreshed is
     * always audited); the token itself is never logged.
     *
     * @param array{min_ttl_seconds?:int,subject?:string,refresh?:string} $params
     *
     * @return array<string,mixed>
     */
    public function token(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/connections/' . rawurlencode($id) . '/token', query: $params);
    }

    /**
     * `POST /v1/connections/:id/token/refresh` - Force a refresh after the provider refused the access
     * token (401). Single-flight: however many callers saw the same 401, the provider is called once. Send
     * `stale_token_hash` (lowercase hex SHA-256 of the access token you were refused with); if the stored
     * token is already a different one, a peer has refreshed and the current token is returned without
     * calling the provider (`refreshed: false`). Same body, ETag and 402 behaviour as the token read; the
     * response is never cached. Counts against the same rate limit as the token read.
     *
     * @param array{reason?:'provider_401'|'expired'|'manual',stale_token_hash?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function refreshToken(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/' . rawurlencode($id) . '/token/refresh',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/connections/tokens/batch` - Read the tokens of up to 100 connections in one call
     * (min_ttl_seconds applies to each). Each id gets its own result: status ok with the token, or status
     * error with a code and reason (not_connected, needs_reauth, revoked, refresh_failed,
     * unknown_connection), so one dead connection never fails the batch. The response is never cached.
     * Counts once against the token-read rate limit.
     *
     * @param array{ids:list<string>,min_ttl_seconds?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function tokens(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/tokens/batch',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/connections/import` - Bulk-import existing provider refresh tokens as connections, up to
     * 500 rows, so nobody reconnects. Each row: provider, owner, external_id (the provider-side account:
     * how a re-import finds the same connection), refresh_token (or access_token), and optionally scopes,
     * metadata, environment, host_params, name, allowed_hosts. By default (validate true) each refresh
     * token is spent once against the provider with the OAuth client that will own the connection, and the
     * tokens the provider issues are stored, so the imported copy stops being current on a rotating
     * provider. A failing row creates and changes nothing. dry_run checks shapes only and never calls the
     * provider. Idempotent on Idempotency-Key. Tokens go straight to the vault and never appear in the
     * answer or the audit log.
     *
     * @param array{connections:list<array<string,mixed>>,validate?:bool,dry_run?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function importConnections(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/import',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/connections/export` - Export connections. Without include_secrets: metadata only
     * (provider, owner, account, environment, scopes, status), scope connections:export. With
     * include_secrets: needs scope connections:export_secrets, the header X-Atlas-Confirm: export-secrets
     * and recipient_public_key (a public JWK: RSA of 3072 bits or more, or EC P-256/384/521). The tokens
     * come back as one compact JWE sealed to that key that only its holder can open; Atlas keeps no
     * plaintext. Pages hold up to 200 connections with secrets (500 without); follow next_cursor with
     * starting_after. Every export is audited with its count, never the secrets. Opened, each connection
     * is a valid import row.
     *
     * @param array{owner_type?:'organization'|'user',owner_id?:string,provider?:string,environment?:'production'|'sandbox',status?:'connected'|'needs_reauth'|'revoked'|'error'|'not_connected',limit?:int,starting_after?:string,include_secrets?:bool,recipient_public_key?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function exportConnections(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/export',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/connections/:id/secret` - Read the credential an api_key, bearer, basic, custom_header or
     * hmac_secret connection stores, for your backend. Needs the dedicated connections:secret_read scope;
     * every read is audited as connection.secret.read with the caller, connection and version (never the
     * value) and the response is `Cache-Control: no-store`. `version` is current (default), previous (only
     * while a rotation's overlap window is open) or a version number. A signing grant's private key is
     * never readable: 409 `not_readable`. There is no browser or session route that reads a credential.
     *
     * @param array{version?:string} $params
     *
     * @return array<string,mixed>
     */
    public function secret(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/connections/' . rawurlencode($id) . '/secret', query: $params);
    }

    /**
     * `GET /v1/connections/:id/credentials/versions` - The vault versions of a connection's entered
     * credential (numbers and times only, never values) and the open rotation window.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function secretVersions(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/connections/' . rawurlencode($id) . '/credentials/versions',
            query: $params,
        );
    }

    /**
     * `POST /v1/connections/:id/credentials/rotate` - Rotate the credential: the body is the new
     * credential (the same fields as PUT credentials). It becomes the current vault version; the replaced
     * one stays valid for `overlap_seconds` (default 3600, up to 30 days): the relay falls back to it when
     * the provider refuses the new one, and GET secret?version=previous returns it. `expires_at` sets the
     * new credential's expiry. The secret never reaches the audit log.
     *
     * @param array{overlap_seconds?:int,expires_at?:int|string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function rotateCredentials(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/connections/' . rawurlencode($id) . '/credentials/rotate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
