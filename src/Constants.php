<?php

declare(strict_types=1);

namespace Atlas;

/** Names the Atlas API accepts in configuration and sends in webhooks. */
final class Constants
{
    /**
     * Captcha providers (`auth_config.captcha.provider`). Captcha is bring-your-own-keys:
     * store the secret first. `vercel_botid` is secret-only: set `attestationUrl` instead
     * of a site key.
     */
    public const CAPTCHA_PROVIDERS = [
        'none', 'atlas', 'hcaptcha', 'turnstile', 'recaptcha', 'recaptcha_v3', 'recaptcha_enterprise',
        'funcaptcha', 'friendly', 'yandex', 'geetest', 'mcaptcha', 'capy', 'seon', 'awswaf', 'perimeterx',
        'akamai', 'kasada', 'incapsula', 'captchafox', 'vercel_botid',
    ];

    /** `auth_config.accountLinkingTrust` values. */
    public const LINKING_TRUST_LEVELS = ['always', 'verified', 'never'];

    /** Billing providers an instance can sell end-user plans through (bring your own key). */
    public const BILLING_PROVIDERS = ['stripe', 'polar', 'dodo', 'creem', 'chargebee', 'autumn', 'commet'];

    /** Where the token-signing key can live. */
    public const SIGNING_KEY_PROVIDERS = ['local', 'aws_kms', 'gcp_kms', 'azure_key_vault'];

    /** A session's custom data changed. */
    public const WEBHOOK_EVENT_SESSION_UPDATED = 'session.updated';

    /** A dormant account signed in again. */
    public const WEBHOOK_EVENT_USER_REACTIVATED = 'user.reactivated';

    /** The security notice sent when a dormant account signs in again. */
    public const EMAIL_TEMPLATE_ACCOUNT_REACTIVATED = 'account_reactivated';
}
