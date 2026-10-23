<?php

declare(strict_types=1);

namespace Atlas\BetterAuth;

/**
 * The classified outcome of a Better Auth session exchange.
 *
 * - `session`: `jwt`, `refreshToken`, `sessionId`, `userId`, `expiresIn`.
 * - `needs_mfa`: `attemptId` and `status` (`needs_second_factor` / `needs_mfa_enrollment`). The Better
 *   Auth session is spent; the user finishes the second factor on your sign-in page.
 * - `refused`: `outcome` (`closed`, `invalid`, `signature_invalid`, `expired`, `reused`,
 *   `user_unavailable`, `rate_limited`, or `unavailable` for anything that is not a verdict on the
 *   session); `httpStatus` is 0 for a transport failure.
 */
final class ExchangeResult
{
    public const SESSION = 'session';
    public const NEEDS_MFA = 'needs_mfa';
    public const REFUSED = 'refused';

    public function __construct(
        public readonly string $kind,
        public readonly string $jwt = '',
        public readonly string $refreshToken = '',
        public readonly string $sessionId = '',
        public readonly string $userId = '',
        public readonly int $expiresIn = 0,
        public readonly string $attemptId = '',
        public readonly string $status = '',
        public readonly ?string $outcome = null,
        public readonly int $httpStatus = 200,
    ) {
    }

    public static function refused(string $outcome, int $httpStatus): self
    {
        return new self(self::REFUSED, outcome: $outcome, httpStatus: $httpStatus);
    }

    /** True when the refusal says the Better Auth session will never exchange (its cookies should go). */
    public function isFinalRefusal(): bool
    {
        return $this->kind === self::REFUSED && in_array($this->outcome, BetterAuth::FINAL_OUTCOMES, true);
    }
}
