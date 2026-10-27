<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/yubikeys` - 3 operations.
 */
final class MfaFactorsYubikeys extends Resource
{
    /**
     * `GET /v1/yubikeys` - List the registered self-held YubiKeys (public id, owner, replay counter). The
     * AES keys are never returned.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/yubikeys', query: $params);
    }

    /**
     * `POST /v1/yubikeys` - Register a YubiKey's AES key for self-held Yubico OTP (mfa.yubikey.mode
     * "self_held"). The key is stored encrypted and never returned. Give user_id to assign it; otherwise
     * the first user to enrol it with a valid OTP claims it. 409 when the public id is already registered.
     *
     * @param array{public_id:string,aes_key:string,private_id?:string,label?:string,user_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function register(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/yubikeys',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/yubikeys/:id` - Remove a registered YubiKey. A factor already enrolled with it stops
     * verifying.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function remove(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/yubikeys/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
