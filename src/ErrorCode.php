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
    /** A flow would send an email but the instance has no way to deliver it. */
    public const EMAIL_DELIVERY_NOT_CONFIGURED = 'EMAIL_DELIVERY_NOT_CONFIGURED';
    /** An email-based sign-in method cannot be turned on without an email provider. */
    public const EMAIL_PROVIDER_REQUIRED = 'EMAIL_PROVIDER_REQUIRED';
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
    public const IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_CONFLICT';

    private function __construct()
    {
    }
}
