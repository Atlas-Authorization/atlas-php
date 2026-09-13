<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\AtlasException;

/**
 * Thrown by {@see VerifyResult::protect()} when verified claims do not satisfy
 * the required authorization condition. Map it to a 403 in your handler.
 *
 * `$reason` is a coarse machine code (`missing_permission`, `missing_role`,
 * `not_signed_in`) and `$condition` is the condition that failed, for logging.
 */
final class ForbiddenException extends AtlasException
{
    /**
     * @param array<string,mixed> $condition
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $condition = [],
    ) {
        parent::__construct("Forbidden: {$reason}", 403);
    }
}
