<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1` - 8 operations.
 */
final class MfaFactors extends Resource
{
    public readonly MfaFactorsYubikeys $yubikeys;
    public readonly MfaFactorsSmartCardMappings $smartCardMappings;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->yubikeys = new MfaFactorsYubikeys($http);
        $this->smartCardMappings = new MfaFactorsSmartCardMappings($http);
    }

    /**
     * `PUT /v1/instance/yubico_secret` - Store (write-only, encrypted) the Yubico cloud API secret key
     * (base64, from upgrade.yubico.com/getapikey). The client id is mfa.yubikey.clientId. Used when
     * mfa.yubikey.mode is "cloud".
     *
     * @param array{secret:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setYubicoSecret(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/instance/yubico_secret',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/instance/yubico_secret` - Remove the stored Yubico cloud secret. Cloud-mode YubiKey
     * sign-ins then fail until a new one is stored.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function removeYubicoSecret(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/instance/yubico_secret',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/instance/duo_secret` - Store (write-only, encrypted) the Duo Web SDK / Universal Prompt
     * client secret. The client id (integration key) and API host are mfa.duo.clientId and
     * mfa.duo.apiHost.
     *
     * @param array{secret:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setDuoSecret(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/instance/duo_secret',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/instance/duo_secret` - Remove the stored Duo client secret.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function removeDuoSecret(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/instance/duo_secret',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PUT /v1/instance/smart_card_secret` - Store (write-only, encrypted) the shared secret your TLS
     * proxy sends in the mfa.smartCard.secretHeader header, so Atlas believes the certificate headers only
     * from your proxy. Use a random value of at least 24 characters.
     *
     * @param array{secret:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function setSmartCardSecret(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/instance/smart_card_secret',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/instance/smart_card_secret` - Remove the stored proxy secret. Smart card sign-in then
     * refuses every request.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function removeSmartCardSecret(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/instance/smart_card_secret',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/instance/duo_health_check` - Ask Duo whether the configured integration key, stored secret
     * and API host are accepted (Duo's health check). Reports ok and a message; nothing is changed.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function duoHealthCheck(array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/instance/duo_health_check',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/:id/security_questions` - Clear a user's security-question answers (and any
     * lockout). They set new ones from their account.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function resetSecurityQuestions(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/security_questions',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
