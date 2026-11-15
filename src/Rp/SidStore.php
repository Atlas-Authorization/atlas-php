<?php

declare(strict_types=1);

namespace Atlas\Rp;

/**
 * Which of your sessions each Atlas `sid` backs, so a back-channel logout can end exactly those. `take` returns AND removes every
 * unexpired binding of the sid (an empty list when none).
 */
interface SidStore
{
    public function bind(string $sid, string $appSessionId, int $expiresAt): void;

    /** @return list<string> */
    public function take(string $sid): array;
}
