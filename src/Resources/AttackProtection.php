<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/attack_protection` — brute-force, breached-password, IP and captcha config. */
final class AttackProtection extends Resource
{
    /** @return array<string,mixed> */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/attack_protection');
    }

    /**
     * Only the two flags are writable; the rest of the object is read-only.
     *
     * @param array{brute_force?:array{enabled?:bool},breached_password?:array{enabled?:bool}} $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/attack_protection', body: $body);
    }

    /**
     * `PUT /v1/attack_protection/app_attestation_secret` - Store the Play Integrity response-decryption
     * key (base64 AES-256) for app attestation. Write-only; the public verification key is
     * attackProtection.appAttestation.android.verificationKey.
     *
     * @param array{secret?:string,kind?:'play_integrity'|'devicecheck'} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setAppAttestationSecret(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/attack_protection/app_attestation_secret',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/attack_protection/app_attestation_secret` - Remove a stored attestation secret: the Play
     * Integrity decryption key (default) or, with kind=devicecheck, the Apple DeviceCheck key. That
     * platform then refuses every request.
     *
     * @param array{dry_run?:'true'|'false',kind?:'play_integrity'|'devicecheck'} $params
     *
     * @return array<string,mixed>
     */
    public function removeAppAttestationSecret(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/attack_protection/app_attestation_secret',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
