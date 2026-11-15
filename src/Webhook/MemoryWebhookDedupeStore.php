<?php

declare(strict_types=1);

namespace Atlas\Webhook;

/** A single-process {@see WebhookDedupeStore}. Use a shared one (Redis, a database) when you run more than one instance. */
final class MemoryWebhookDedupeStore implements WebhookDedupeStore
{
    /** @var array<string,int> */
    private array $seen = [];

    /** @param (callable():int)|null $now epoch seconds */
    public function __construct(private $now = null)
    {
    }

    public function setIfAbsent(string $key, int $ttlSeconds): bool
    {
        $now = $this->now === null ? time() : ($this->now)();
        $this->seen = array_filter($this->seen, static fn (int $exp): bool => $exp > $now);
        if (isset($this->seen[$key])) {
            return false;
        }
        $this->seen[$key] = $now + $ttlSeconds;

        return true;
    }

    public function delete(string $key): void
    {
        unset($this->seen[$key]);
    }
}
