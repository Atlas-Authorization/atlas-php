<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/platform/atlas_id_apps/:id/provisioned_accounts` — test and review accounts for a Sign in with Atlas app:
 * real Atlas accounts confined to the app (reviewer logins, CI, demos).
 *
 * Needs the platform scope `platform:atlas_id_apps:provision` or the per-app scope `atlas_id_apps:<app id>:provision`.
 *
 * The password is returned exactly once, on create and on rotation, as `password`. Pass a `$secretRecipient`
 * (`x25519:<base64 public key>`) to receive it sealed as `sealed_password` instead; open it with
 * {@see \Atlas\Auth\SealedBox}. Treat `password` like any secret: do not log the answer.
 *
 *     $acct = $atlas->platform->atlasIdApps->provisionedAccounts->create('aia_1', [
 *         'email' => 'reviewer@example.com',
 *         'label' => 'app-store reviewer',
 *     ]);
 *
 * @phpstan-import-type AtlasIdProvisionedAccount from PlatformAtlasIdApps
 */
final class PlatformProvisionedAccounts extends Resource
{
    /**
     * `GET /v1/platform/atlas_id_apps/:id/provisioned_accounts`.
     *
     * @return array{object:string,data:list<AtlasIdProvisionedAccount>,limit:int,total:int}
     */
    public function list(string $appId): array
    {
        return $this->http->request('GET', $this->base($appId));
    }

    /**
     * `POST /v1/platform/atlas_id_apps/:id/provisioned_accounts` — create a test or review account.
     *
     * Body: `email`, `label`, optional `password` (12+ characters; omit and Atlas generates one), `email_verified`
     * (default true), `two_factor_exempt`, `expires_at` (epoch ms or ISO 8601; null = never).
     *
     * @param array{email:string,label:string,password?:string,email_verified?:bool,two_factor_exempt?:bool,expires_at?:int|string|null} $body
     * @param string|null $secretRecipient `x25519:<base64 public key>`: receive the password sealed (`sealed_password`)
     *
     * @return AtlasIdProvisionedAccount
     */
    public function create(string $appId, array $body, ?string $idempotencyKey = null, ?string $secretRecipient = null): array
    {
        return $this->http->request('POST', $this->base($appId), body: $body, idempotencyKey: $idempotencyKey, headers: self::recipient($secretRecipient));
    }

    /**
     * `GET /v1/platform/atlas_id_apps/:id/provisioned_accounts/:acct`.
     *
     * @return AtlasIdProvisionedAccount
     */
    public function get(string $appId, string $accountId): array
    {
        return $this->http->request('GET', $this->base($appId) . '/' . rawurlencode($accountId));
    }

    /**
     * `DELETE /v1/platform/atlas_id_apps/:id/provisioned_accounts/:acct` — delete the account and end every session and grant it holds.
     *
     * @return array{object:string,id:string,deleted:bool,sessions_ended:int}
     */
    public function remove(string $appId, string $accountId): array
    {
        return $this->http->request('DELETE', $this->base($appId) . '/' . rawurlencode($accountId));
    }

    /**
     * `POST /v1/platform/atlas_id_apps/:id/provisioned_accounts/:acct/password` — a new password (yours, or one Atlas
     * generates), returned once.
     *
     * @param array{password?:string}|null $body
     * @param string|null $secretRecipient `x25519:<base64 public key>`: receive the password sealed (`sealed_password`)
     *
     * @return AtlasIdProvisionedAccount
     */
    public function rotatePassword(string $appId, string $accountId, ?array $body = null, ?string $secretRecipient = null): array
    {
        return $this->http->request('POST', $this->base($appId) . '/' . rawurlencode($accountId) . '/password', body: $body, headers: self::recipient($secretRecipient));
    }

    private function base(string $appId): string
    {
        return '/v1/platform/atlas_id_apps/' . rawurlencode($appId) . '/provisioned_accounts';
    }

    /** @return array<string,string>|null */
    private static function recipient(?string $secretRecipient): ?array
    {
        return $secretRecipient === null || $secretRecipient === '' ? null : ['X-Secret-Recipient' => $secretRecipient];
    }
}
