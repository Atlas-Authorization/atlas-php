<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/oauth_client_profiles` - 3 operations.
 */
final class OauthClientProfiles extends Resource
{
    /**
     * `GET /v1/oauth_client_profiles` - List the OAuth client policy profiles: the built-ins
     * (fapi2_security, fapi2_message_signing, pkce_required, par_required, dpop_required) and your custom
     * ones.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/oauth_client_profiles', query: $params);
    }

    /**
     * `PUT /v1/oauth_client_profiles/:name` - Create or replace a custom client policy profile (rules:
     * PKCE, PAR, sender constraint, signed request object, allowed auth methods and signing algorithms,
     * https-only redirects, allowed claims). Built-in names are reserved.
     *
     * @param array{description?:string|null,require_pkce?:bool,require_par?:bool,sender_constraint?:'none'|'dpop'|'mtls'|'any',require_signed_request?:bool,token_endpoint_auth_methods?:list<string>|null,signing_algs?:list<string>|null,https_redirects_only?:bool,allowed_claims?:list<string>|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function set(string $name, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/oauth_client_profiles/' . rawurlencode($name),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/oauth_client_profiles/:name` - Delete a custom client policy profile. 409 while any
     * client is assigned it.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $name, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/oauth_client_profiles/' . rawurlencode($name),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
