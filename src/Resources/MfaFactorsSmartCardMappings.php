<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/users/:id/smartcard_mappings` - 3 operations.
 */
final class MfaFactorsSmartCardMappings extends Resource
{
    /**
     * `GET /v1/users/:id/smartcard_mappings` - List the certificate attributes registered for a user.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/smartcard_mappings', query: $params);
    }

    /**
     * `POST /v1/users/:id/smartcard_mappings` - Pre-register the certificate attribute that signs this
     * user in with a smart card: kind fingerprint (SHA-256, 64 hex), serial (hex, plus the issuer DN),
     * san_email, san_upn or subject_cn. A registered mapping only applies while its kind is listed in
     * mfa.smartCard.mappingRules. 409 when the value is already mapped.
     *
     * @param array{kind:'san_email'|'san_upn'|'subject_cn'|'serial'|'fingerprint',value:string,issuer?:string,label?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function add(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/smartcard_mappings',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/:id/smartcard_mappings/:mappingId` - Remove a registered certificate mapping.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function remove(string $id, string $mappingId, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/smartcard_mappings/' . rawurlencode($mappingId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
