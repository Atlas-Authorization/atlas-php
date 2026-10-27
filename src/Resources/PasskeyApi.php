<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/passkey_api` - 7 operations.
 */
final class PasskeyApi extends Resource
{
    /**
     * `POST /v1/passkey_api/registration/init` - Start registering a passkey for one of your users.
     * Returns WebAuthn creation options for the browser.
     *
     * @param array{user_id:string,username?:string,display_name?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function registrationInit(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/passkey_api/registration/init',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/passkey_api/registration/finalize` - Verify the browser’s attestation and store the
     * passkey for the user. 409 if the credential is already registered; 403 if the authenticator is
     * blocked by the passkey policy.
     *
     * @param array{user_id:string,challenge:string,attestation_object:string,client_data_json:string,name?:string,transports?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function registrationFinalize(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/passkey_api/registration/finalize',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/passkey_api/login/init` - Start a passkey login. With user_id the user’s credentials are
     * listed; without it the login is discoverable and the credential names the user.
     *
     * @param array{user_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function loginInit(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/passkey_api/login/init',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/passkey_api/login/finalize` - Verify the browser’s assertion. Returns the user_id the
     * passkey belongs to; your backend then starts its own session. Pass user_id to require that exact
     * user. Challenges are single use.
     *
     * @param array{handle:string,challenge:string,credential_id:string,authenticator_data:string,client_data_json:string,signature:string,user_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function loginFinalize(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/passkey_api/login/finalize',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/passkey_api/users/:userId/passkeys` - List the passkeys registered for one of your users.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $userId, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/passkey_api/users/' . rawurlencode($userId) . '/passkeys',
            query: $params,
        );
    }

    /**
     * `DELETE /v1/passkey_api/users/:userId/passkeys/:id` - Delete one of your user’s passkeys.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $userId, string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/passkey_api/users/' . rawurlencode($userId) . '/passkeys/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/passkey_api/usage` - Passkeys stored through the Passkey API on this instance (the metered
     * quantity).
     *
     * @return array<string,mixed>
     */
    public function usage(): array
    {
        return $this->http->request('GET', '/v1/passkey_api/usage');
    }
}
