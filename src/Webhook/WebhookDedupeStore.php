<?php

declare(strict_types=1);

namespace Atlas\Webhook;

/**
 * Where seen delivery ids live. `setIfAbsent` must be ATOMIC: it stores `$key` for `$ttlSeconds` and returns true when the key was NOT
 * there, false when it already was (Redis: `SET key 1 EX ttl NX`). `delete` lets a failed handler release the id so Atlas's retry runs.
 */
interface WebhookDedupeStore
{
    public function setIfAbsent(string $key, int $ttlSeconds): bool;

    public function delete(string $key): void;
}
