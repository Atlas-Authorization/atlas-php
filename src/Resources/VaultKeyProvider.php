<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/vault/key_provider` - 3 operations.
 */
final class VaultKeyProvider extends Resource
{
    /**
     * `GET /v1/vault/key_provider` - The customer-managed key that wraps new vault data keys. 404 when
     * none is configured (the instance key is used).
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/vault/key_provider');
    }

    /**
     * `PUT /v1/vault/key_provider` - Bring your own key: wrap new vault data keys with your AWS KMS
     * (`config.region`, `config.key_id`; symmetric key), Google Cloud KMS (`config.crypto_key`; symmetric
     * key) or Azure Key Vault (`config.vault_url`, `config.key_name`; RSA key with wrap/unwrap) key. Atlas
     * proves access with a wrap/unwrap round trip before saving (422 or 502 on refusal). Credentials are
     * write-only. Existing versions stay on the key that wrapped them; switching provider type is a 409
     * while versions remain on the old one.
     *
     * @param array{provider?:string,config?:array<string,mixed>,credentials?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function set(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/vault/key_provider',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/vault/key_provider` - Stop using your own key for new versions. 409 while any version is
     * still wrapped by it.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function remove(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/vault/key_provider',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
