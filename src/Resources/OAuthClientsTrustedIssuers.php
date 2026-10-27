<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/oauth_clients/:id/trusted_issuers` - 3 operations.
 */
final class OAuthClientsTrustedIssuers extends Resource
{
    /**
     * `GET /v1/oauth_clients/:id/trusted_issuers` - List the external issuers this OAuth client trusts to
     * assert users through the RFC 7523 jwt-bearer grant.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/oauth_clients/' . rawurlencode($id) . '/trusted_issuers',
            query: $params,
        );
    }

    /**
     * `POST /v1/oauth_clients/:id/trusted_issuers` - Trust an external issuer for this client. With
     * purpose user_assertion (default) its signed JWTs (verified against the inline jwks or the https
     * jwks_uri) can then be presented to POST /oauth2/token with
     * grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer. subject_mapping says how the JWT sub names
     * an Atlas user. With purpose client_auth or both (PRO-06 workload identity) a client registered with
     * token_endpoint_auth_method workload_jwt authenticates by presenting a token from this issuer as its
     * client_assertion; subject_pattern is then required and must name the workload. workload_kind
     * github_actions, gcp and azure fill in the issuer and JWKS URL. Audited as
     * oauth_trusted_issuer.created.
     *
     * @param array{issuer:string,jwks?:array<string,mixed>,jwks_uri?:string,subject_mapping?:'user_id'|'external_id'|'email',workload_kind?:'generic'|'spiffe'|'kubernetes'|'github_actions'|'gcp'|'azure',tenant_id?:string,purpose?:'user_assertion'|'client_auth'|'both',subject_pattern?:string,required_claims?:array<string,mixed>,audiences?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/oauth_clients/' . rawurlencode($id) . '/trusted_issuers',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/oauth_clients/:id/trusted_issuers/:issuerId` - Stop trusting an issuer: its assertions
     * are refused at once. Audited as oauth_trusted_issuer.deleted.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, string $issuerId, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/oauth_clients/' . rawurlencode($id) . '/trusted_issuers/' . rawurlencode($issuerId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
