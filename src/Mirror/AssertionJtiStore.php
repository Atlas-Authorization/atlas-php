<?php

declare(strict_types=1);

namespace Atlas\Mirror;

/** Optional on a {@see KeyMirrorStore}: share used assertion ids across processes. Without it {@see KeyMirror} keeps them in memory. */
interface AssertionJtiStore
{
    /** Record the jti as used until `$expiresAtMs`; false when it was already used (a replay). */
    public function claimAssertionJti(string $keyId, string $jti, int $expiresAtMs): bool;
}
