<?php

declare(strict_types=1);

namespace Atlas\Mirror;

/** An in-memory {@see KeyMirrorStore} for tests and single-process apps; back production with a table. */
final class MemoryKeyMirrorStore implements KeyMirrorStore
{
    /** @var array<string,array<string,mixed>> */
    public array $keys = [];
    private ?string $cursor = null;
    private ?int $lastSyncAt = null;

    public function put(array $key): void
    {
        $this->keys[(string) $key['id']] = $key;
    }

    public function get(string $id): ?array
    {
        return $this->keys[$id] ?? null;
    }

    public function getByHash(string $hash): ?array
    {
        foreach ($this->keys as $key) {
            if (($key['hash'] ?? null) === $hash) {
                return $key;
            }
        }

        return null;
    }

    public function markRevoked(string $id, int $revokedAt, ?string $reason = null): void
    {
        if (isset($this->keys[$id]) && ($this->keys[$id]['revoked_at'] ?? null) === null) {
            $this->keys[$id]['revoked_at'] = $revokedAt;
            if ($reason !== null) {
                $this->keys[$id]['revoked_reason'] = $reason;
            }
        }
    }

    public function markMembershipEnded(string $userId, string $organizationId, int $revokedAt): void
    {
        foreach ($this->keys as $id => $key) {
            if (($key['revoked_at'] ?? null) === null && ($key['subject_type'] ?? null) === 'user' && ($key['subject_id'] ?? null) === $userId && ($key['organization_id'] ?? null) === $organizationId) {
                $this->keys[$id]['revoked_at'] = $revokedAt;
                $this->keys[$id]['revoked_reason'] = 'membership_ended';
            }
        }
    }

    public function getCursor(): ?string
    {
        return $this->cursor;
    }

    public function setCursor(string $cursor): void
    {
        $this->cursor = $cursor;
    }

    public function getLastSyncAt(): ?int
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(int $at): void
    {
        $this->lastSyncAt = $at;
    }
}
