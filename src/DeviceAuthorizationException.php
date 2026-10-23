<?php

declare(strict_types=1);

namespace Atlas;

/**
 * A refused device-approval request; `code` is the server's stable error code
 * (`NOT_FOUND`, `RATE_LIMITED`, `STEP_UP_REQUIRED`, …).
 */
final class DeviceAuthorizationException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?string $param = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }

    /** Approving needs a fresh re-authentication first. */
    public function needsReverification(): bool
    {
        return $this->errorCode === 'STEP_UP_REQUIRED';
    }

    /** The code is unknown, expired or already used. */
    public function isInvalidCode(): bool
    {
        return $this->status === 404 || $this->status === 409;
    }
}
