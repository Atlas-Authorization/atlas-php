<?php

declare(strict_types=1);

namespace Atlas;

/**
 * Stable error codes worth branching on, for
 * {@see Exception\AtlasApiException::getErrorCode()} and `hasCode()`:
 *
 *     catch (AtlasApiException $e) {
 *         if ($e->hasCode(ErrorCode::EMAIL_DELIVERY_NOT_CONFIGURED)) { … }
 *     }
 */
final class ErrorCode
{
    /** A request field the endpoint does not accept (strict bodies); `param` names it. */
    public const UNKNOWN_FIELD = 'UNKNOWN_FIELD';
    /** The body set a field that is not writable on this endpoint (`param` names it; the message names the call that changes it). */
    public const READ_ONLY_FIELD = 'READ_ONLY_FIELD';
    /** A flow would send an email but the instance has no way to deliver it. */
    public const EMAIL_DELIVERY_NOT_CONFIGURED = 'EMAIL_DELIVERY_NOT_CONFIGURED';
    /** An email-based sign-in method cannot be turned on without an email provider. */
    public const EMAIL_PROVIDER_REQUIRED = 'EMAIL_PROVIDER_REQUIRED';
    /** The email provider cannot be removed while email-dependent features are on (`meta.features` lists them); delete with `force: true` to remove it anyway. */
    public const EMAIL_PROVIDER_IN_USE = 'EMAIL_PROVIDER_IN_USE';
    /** Captcha is bring-your-own-keys: turning it on (or switching provider) needs that provider's site key and secret stored first. */
    public const CAPTCHA_KEYS_REQUIRED = 'CAPTCHA_KEYS_REQUIRED';
    /** A captcha key a live flow uses cannot be removed (`meta.flows`); `deleteCaptchaSecret(force: true)` removes it anyway. */
    public const CAPTCHA_IN_USE = 'CAPTCHA_IN_USE';
    /** The development email sender is not available on a production instance (409). */
    public const DEVELOPMENT_EMAIL_NOT_ALLOWED = 'DEVELOPMENT_EMAIL_NOT_ALLOWED';
    /** The development email sender is not available on this deployment (503). */
    public const DEVELOPMENT_EMAIL_UNAVAILABLE = 'DEVELOPMENT_EMAIL_UNAVAILABLE';
    /** The development sender's daily or per-recipient hourly cap is spent (429; `meta.window`, `meta.retry_after_seconds`). */
    public const DEVELOPMENT_EMAIL_LIMIT_REACHED = 'DEVELOPMENT_EMAIL_LIMIT_REACHED';
    /** The development sender sends only Atlas's own sign-in emails (422). */
    public const DEVELOPMENT_EMAIL_TEMPLATE_NOT_ALLOWED = 'DEVELOPMENT_EMAIL_TEMPLATE_NOT_ALLOWED';
    /** Referrals cannot be enabled until the provider's needs are set (422; `meta` names what is missing). */
    public const REFERRALS_NOT_CONFIGURED = 'REFERRALS_NOT_CONFIGURED';
    /** Another user on the instance already holds that referral code (409). */
    public const REFERRAL_CODE_TAKEN = 'REFERRAL_CODE_TAKEN';
    /** A billing plan that subscriptions still use cannot be deleted. */
    public const PLAN_IN_USE = 'PLAN_IN_USE';
    /** The key is valid but for a different API surface (e.g. a platform key on an instance route). */
    public const WRONG_KEY_SURFACE = 'WRONG_KEY_SURFACE';
    /** The key is missing, malformed, revoked or unknown. */
    public const INVALID_KEY = 'INVALID_KEY';
    public const NOT_FOUND = 'NOT_FOUND';
    public const VALIDATION_FAILED = 'VALIDATION_FAILED';
    public const RATE_LIMITED = 'RATE_LIMITED';
    public const SCOPE_MISSING = 'SCOPE_MISSING';
    /** An idempotency key was reused with a different request (e.g. another unit or amount). */
    public const IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';
    /** A usage debit would exceed one of the API key's spend caps (the `code` of a refused debit). */
    public const LIMIT_EXCEEDED = 'LIMIT_EXCEEDED';
    /** The session was revoked. */
    public const SESSION_REVOKED = 'SESSION_REVOKED';
    /** The session timed out from inactivity. */
    public const SESSION_IDLE_EXPIRED = 'SESSION_IDLE_EXPIRED';
    /** The session passed its absolute expiry. */
    public const SESSION_EXPIRED = 'SESSION_EXPIRED';

    private function __construct()
    {
    }
}
