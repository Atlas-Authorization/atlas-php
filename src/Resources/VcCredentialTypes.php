<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/vc/credential_types` - 4 operations.
 */
final class VcCredentialTypes extends Resource
{
    /**
     * `GET /v1/vc/credential_types` - List the credential types you issue.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/vc/credential_types', query: $params);
    }

    /**
     * `POST /v1/vc/credential_types` - Define a credential type: its vct, and how each claim is filled
     * from a user’s attributes. Issued as SD-JWT VC.
     *
     * @param array{vct:string,format?:'dc+sd-jwt'|'mso_mdoc',namespace?:string,iaca_roots?:list<string>,signer_chain?:list<string>,name:string,claims:list<array<string,mixed>>,lifetime_seconds?:int,require_wallet_attestation?:bool,wallet_attestation_jwks?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/vc/credential_types',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/vc/credential_types/:id` - Read one credential type.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/vc/credential_types/' . rawurlencode($id));
    }

    /**
     * `DELETE /v1/vc/credential_types/:id` - Delete a credential type and the records of what was issued
     * under it.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/vc/credential_types/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
