<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/sso_connections` — enterprise OIDC/SAML/Discourse SSO connections. */
final class SsoConnections extends Resource
{
    /** @return array<string,mixed> a ListPage of connections */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/sso_connections');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/sso_connections/' . rawurlencode($id));
    }

    /**
     * @param array<string,mixed> $body write-only secrets never come back
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/sso_connections', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * PATCH accepts everything create does except `type`, which is immutable.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/sso_connections/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/sso_connections/' . rawurlencode($id));
    }

    /** SP SAML metadata for a connection (JSON envelope carrying the XML). @return array<string,mixed> */
    public function samlMetadata(string $id): array
    {
        return $this->http->request('GET', '/v1/sso_connections/' . rawurlencode($id) . '/saml_metadata');
    }

    /**
     * OIDC: run the connection's profile mapping (or the body's) against a pasted sample:
     * `profile_mapping`, `userinfo`, `id_token_claims`, `id_token`.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed> `profile`, `trace`, `warnings`
     */
    public function previewProfileMapping(string $id, array $body): array
    {
        return $this->http->request('POST', '/v1/sso_connections/' . rawurlencode($id) . '/profile_mapping/preview', body: $body);
    }

    /**
     * OIDC: the connection's private_key_jwt signing keys.
     *
     * @return array<string,mixed> a ListPage
     */
    public function listSigningKeys(string $id): array
    {
        return $this->http->request('GET', '/v1/sso_connections/' . rawurlencode($id) . '/signing_keys');
    }

    /**
     * Generate a new active key (`RS256`, `PS256` or `ES256`); the previous one turns
     * `retiring` (still published).
     *
     * @return array<string,mixed>
     */
    public function rotateSigningKey(string $id, ?string $alg = null): array
    {
        return $this->http->request('POST', '/v1/sso_connections/' . rawurlencode($id) . '/signing_keys', body: $alg === null ? [] : ['alg' => $alg]);
    }

    /**
     * Retire a non-active key (it leaves the JWKS).
     *
     * @return array<string,mixed>
     */
    public function retireSigningKey(string $id, string $kid): array
    {
        return $this->http->request('POST', '/v1/sso_connections/' . rawurlencode($id) . '/signing_keys/' . rawurlencode($kid) . '/retire');
    }

    /**
     * The SAML connection's SP encryption certificate(s).
     *
     * @return array<string,mixed>
     */
    public function getSamlEncryptionKey(string $id): array
    {
        return $this->http->request('GET', '/v1/sso_connections/' . rawurlencode($id) . '/saml_encryption_key');
    }

    /**
     * Generate the SP encryption key pair, or ROTATE it when one exists: the current pair
     * becomes the previous one (still advertised and accepted until
     * {@see retirePreviousSamlEncryptionKey()}).
     *
     * @return array<string,mixed>
     */
    public function createSamlEncryptionKey(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/sso_connections/' . rawurlencode($id) . '/saml_encryption_key', idempotencyKey: $idempotencyKey);
    }

    /**
     * Finish a rotation: stop accepting the previous encryption key.
     *
     * @return array<string,mixed>
     */
    public function retirePreviousSamlEncryptionKey(string $id): array
    {
        return $this->http->request('DELETE', '/v1/sso_connections/' . rawurlencode($id) . '/saml_encryption_key/previous');
    }

    /**
     * Turn assertion encryption off (refused while encrypted assertions are required).
     *
     * @return array<string,mixed>
     */
    public function deleteSamlEncryptionKey(string $id): array
    {
        return $this->http->request('DELETE', '/v1/sso_connections/' . rawurlencode($id) . '/saml_encryption_key');
    }
}
