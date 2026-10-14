<?php

declare(strict_types=1);

namespace Atlas\Verify;

/**
 * A replay guard for logout token ids. Back it with something every process
 * shares — Redis `SET key 1 NX EX ttl`, a unique database column, or a PSR-16
 * cache `has()`/`set()` pair — so a replayed token is refused everywhere.
 */
interface JtiStore
{
    /**
     * Record `$jti` for `$ttlSeconds`. Return true the first time it is seen,
     * false when it was already recorded (a replay).
     */
    public function add(string $jti, int $ttlSeconds): bool;
}
