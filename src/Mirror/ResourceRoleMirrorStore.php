<?php

declare(strict_types=1);

namespace Atlas\Mirror;

/**
 * Where a {@see ResourceRoleMirror} keeps its rows: types `{key, inherit, roles: [{key, permissions, includes?}]}`, resources
 * `{id, type, external_id, parent_id}` and assignments `{id, resource_id, subject ("user:id"), role, inherit}`.
 */
interface ResourceRoleMirrorStore
{
    public function getCursor(): ?string;

    public function setCursor(string $cursor): void;

    /** @return array<string,mixed>|null */
    public function getType(string $key): ?array;

    /**
     * @param array{id:string}|array{type:string,external_id:string} $ref
     *
     * @return array<string,mixed>|null
     */
    public function getResource(array $ref): ?array;

    /**
     * @param list<string> $resourceIds
     *
     * @return list<array<string,mixed>>
     */
    public function assignmentsOn(array $resourceIds): array;

    /** @param list<array<string,mixed>> $types */
    public function replaceTypes(array $types): void;

    /** @param list<array<string,mixed>> $resources */
    public function replaceResources(array $resources): void;

    /** @param list<array{change:string,assignment:array<string,mixed>}> $changes `created` or `deleted` */
    public function applyChanges(array $changes): void;

    public function getLastSyncAt(): ?int;

    public function setLastSyncAt(int $at): void;
}
