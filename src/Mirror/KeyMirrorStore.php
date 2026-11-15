<?php

declare(strict_types=1);

namespace Atlas\Mirror;

/**
 * Where a {@see KeyMirror} keeps its rows. A mirrored key is `{id, hash, prefix, subject_type, subject_id, name, scopes, allowed_ips,
 * expires_at (epoch ms or null), revoked_at (epoch ms or null), organization_id, revoked_reason}`; `hash` is the sha256 (hex) of the
 * secret, never the secret.
 */
interface KeyMirrorStore
{
    /** @param array<string,mixed> $key */
    public function put(array $key): void;

    /** @return array<string,mixed>|null */
    public function get(string $id): ?array;

    /** @return array<string,mixed>|null */
    public function getByHash(string $hash): ?array;

    public function markRevoked(string $id, int $revokedAt, ?string $reason = null): void;

    /** Revoke every live key of `$userId` bound to `$organizationId` (reason `membership_ended`). */
    public function markMembershipEnded(string $userId, string $organizationId, int $revokedAt): void;

    public function getCursor(): ?string;

    public function setCursor(string $cursor): void;

    /** Epoch ms of the last COMPLETED sync, or null. */
    public function getLastSyncAt(): ?int;

    public function setLastSyncAt(int $at): void;
}
