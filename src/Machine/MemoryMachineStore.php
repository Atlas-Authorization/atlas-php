<?php

declare(strict_types=1);

namespace Atlas\Machine;

/** An in-memory {@see MachineStore} (tests, short-lived workers). */
final class MemoryMachineStore implements MachineStore
{
    /** @param array<string,mixed>|null $identity */
    public function __construct(private ?array $identity = null)
    {
    }

    public function load(): ?array
    {
        return $this->identity;
    }

    public function save(array $identity): void
    {
        $this->identity = $identity;
    }

    public function clear(): void
    {
        $this->identity = null;
    }
}
