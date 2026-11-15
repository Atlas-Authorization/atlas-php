<?php

declare(strict_types=1);

namespace Atlas\Rp;

/** A {@see SidStore} for one process (tests, CLI). Use a database-backed one when several servers run your app. */
final class MemorySidStore implements SidStore
{
    /** @var array<string,array<string,int>> */
    private array $rows = [];

    /** @param (callable():int)|null $now */
    public function __construct(private $now = null)
    {
    }

    public function bind(string $sid, string $appSessionId, int $expiresAt): void
    {
        $this->rows[$sid][$appSessionId] = $expiresAt;
    }

    public function take(string $sid): array
    {
        $now = $this->now === null ? time() : ($this->now)();
        $live = array_keys(array_filter($this->rows[$sid] ?? [], static fn (int $exp): bool => $exp > $now));
        unset($this->rows[$sid]);

        return $live;
    }
}
