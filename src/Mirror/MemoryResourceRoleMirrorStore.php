<?php

declare(strict_types=1);

namespace Atlas\Mirror;

/** An in-memory {@see ResourceRoleMirrorStore} for tests and single-process apps. */
final class MemoryResourceRoleMirrorStore implements ResourceRoleMirrorStore
{
    /** @var array<string,array<string,mixed>> */
    public array $assignments = [];
    /** @var array<string,array<string,mixed>> */
    public array $resources = [];
    /** @var array<string,array<string,mixed>> */
    public array $types = [];
    private ?string $cursor = null;
    private ?int $lastSyncAt = null;

    public function getCursor(): ?string
    {
        return $this->cursor;
    }

    public function setCursor(string $cursor): void
    {
        $this->cursor = $cursor;
    }

    public function getType(string $key): ?array
    {
        return $this->types[$key] ?? null;
    }

    public function getResource(array $ref): ?array
    {
        if (isset($ref['id'])) {
            return $this->resources[$ref['id']] ?? null;
        }
        foreach ($this->resources as $r) {
            if ($r['type'] === ($ref['type'] ?? null) && $r['external_id'] === ($ref['external_id'] ?? null)) {
                return $r;
            }
        }

        return null;
    }

    public function assignmentsOn(array $resourceIds): array
    {
        return array_values(array_filter($this->assignments, static fn (array $a): bool => in_array($a['resource_id'], $resourceIds, true)));
    }

    public function replaceTypes(array $types): void
    {
        $this->types = array_column($types, null, 'key');
    }

    public function replaceResources(array $resources): void
    {
        $this->resources = array_column($resources, null, 'id');
    }

    public function applyChanges(array $changes): void
    {
        foreach ($changes as $c) {
            if ($c['change'] === 'deleted') {
                unset($this->assignments[$c['assignment']['id']]);
            } else {
                $this->assignments[$c['assignment']['id']] = $c['assignment'];
            }
        }
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
