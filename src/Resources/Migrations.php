<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/migrations` - 5 operations.
 */
final class Migrations extends Resource
{
    public readonly MigrationsImports $imports;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->imports = new MigrationsImports($http);
    }

    /**
     * `POST /v1/migrations/:source/exchange` - Server-to-server exchange of an old `source` session for an
     * Atlas session, once (what migrationBridge({ source }) calls with the secret key). The token is
     * verified (vendor JWT against the bridge's issuer and keys, or sha256 matched against imported
     * sessions), mapped to the imported user, spent in the single-use ledger and then takes the normal
     * sign-in path (guards, MFA policy, post_login Actions, audit). Returns tokens in the body, or a
     * sign_in_attempt when a second factor is owed. Limited per instance (6000/min), per end-user IP
     * (60/min) and per user (10/min). Send the end user's IP / user agent in X-Atlas-Client-IP /
     * X-Atlas-Client-User-Agent.
     *
     * @param array{token:string,remember?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function exchange(string $source, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/migrations/' . rawurlencode($source) . '/exchange',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/migrations/sources` - Every migration source: what it exports, whether passwords move (or
     * need lazy migration), its bridge preset and variables, and its lazy-migration preset.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function sources(array $params = []): array
    {
        return $this->http->request('GET', '/v1/migrations/sources', query: $params);
    }

    /**
     * `GET /v1/migrations/:source/bridge` - Read the zero-logout bridge settings for `source` (window, JWT
     * preset / issuer / keys, claims, cookie names) and exchange counts. Never returns a shared secret.
     *
     * @return array<string,mixed>
     */
    public function getBridge(string $source): array
    {
        return $this->http->request('GET', '/v1/migrations/' . rawurlencode($source) . '/bridge');
    }

    /**
     * `PUT /v1/migrations/:source/bridge` - Create or update the bridge settings for `source`. Every field
     * is optional and merged onto the stored ones; a preset fills the JWT settings from its variables,
     * explicit fields override it. The keys URL must be public https on a production instance.
     * `better_auth` takes the Better Auth bridge body (see /v1/migrations/better_auth/bridge).
     *
     * @param array{enabled?:bool,ends_at?:string|int|null,mode?:'jwt'|'session_table'|'remote',preset?:string|null,preset_vars?:array<string,mixed>,issuer?:string|null,jwks_url?:string|null,audience?:list<string>|null,algorithms?:list<string>|null,claim_map?:array{subject?:string,email?:string,email_verified?:string,name?:string,session_key?:string,mfa?:string},expired_grace_seconds?:int,cookie_names?:list<string>,create_users?:bool,shared_secret?:string|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setBridge(string $source, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/migrations/' . rawurlencode($source) . '/bridge',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/migrations/:source/bridge` - Remove the bridge settings for `source` (the bridge is off
     * and any stored secret deleted).
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function deleteBridge(string $source, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/migrations/' . rawurlencode($source) . '/bridge',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
