<?php

declare(strict_types=1);

namespace Atlas\Mirror;

use Atlas\Http;

/**
 * A local mirror of resource-role assignments (the resource tree, the role definitions, who holds what), so "can this subject do this
 * on this resource" is answered in-process. {@see sync()} loads the tree and role types, then follows the assignment change feed;
 * {@see applyWebhook()} applies `resource_role_assignment.created|deleted` events between syncs.
 *
 *     $mirror = new ResourceRoleMirror(new MemoryResourceRoleMirrorStore(), $atlas->http, maxStalenessMs: 120_000);
 *     $mirror->sync();
 *     $mirror->can(['type' => 'user', 'id' => 'user_1'], 'doc:edit', ['type' => 'document', 'external_id' => 'doc_9'], ['orgs' => ['org_1']]);
 */
final class ResourceRoleMirror
{
    private const MAX_DEPTH = 16;

    /** @var callable():int */
    private $now;
    private ?int $treeAt = null;

    /**
     * @param int|null              $maxStalenessMs  when set, `check` answers `code: stale` (never a guess) if never synced or last synced longer ago
     * @param int                   $serveStaleForMs keep answering from the last data this many extra ms
     * @param int                   $treeRefreshMs   reload the tree and role types at most this often
     * @param (callable():int)|null $now             epoch ms (tests)
     */
    public function __construct(
        private readonly ResourceRoleMirrorStore $store,
        private readonly Http $http,
        private readonly ?int $maxStalenessMs = null,
        private readonly int $serveStaleForMs = 0,
        private readonly int $treeRefreshMs = 0,
        ?callable $now = null,
    ) {
        $this->now = $now ?? static fn (): int => (int) (microtime(true) * 1000);
    }

    /**
     * @param array{type:string,id:string}                       $subject    `type` is user, group or org
     * @param array{id:string}|array{type:string,external_id:string} $resource
     * @param array{orgs?:list<string>,groups?:list<string>}      $options    extra subjects the caller holds roles through
     *
     * @return array{allowed:bool,resource_id:?string,grants:list<array<string,mixed>>,code?:string,stale?:bool}
     */
    public function check(array $subject, string $permission, array $resource, array $options = []): array
    {
        $stale = false;
        if ($this->maxStalenessMs !== null) {
            $at = $this->store->getLastSyncAt();
            $behind = $at === null ? PHP_INT_MAX : ($this->now)() - $at - $this->maxStalenessMs;
            if ($behind > 0) {
                if ($at === null || $behind > $this->serveStaleForMs) {
                    return ['allowed' => false, 'resource_id' => null, 'grants' => [], 'code' => 'stale'];
                }
                $stale = true;
            }
        }
        $row = $this->store->getResource($resource);
        if ($row === null || $permission === '') {
            return ['allowed' => false, 'resource_id' => $row['id'] ?? null, 'grants' => []] + ($row === null ? ['code' => 'unknown_resource'] : []);
        }
        $subjects = [$subject['type'] . ':' . $subject['id'] => true];
        foreach ($options['groups'] ?? [] as $g) {
            $subjects["group:$g"] = true;
        }
        foreach ($options['orgs'] ?? [] as $o) {
            $subjects["org:$o"] = true;
        }
        $grants = self::resolve($this->chain($row), array_keys($subjects), $permission);

        return ['allowed' => $grants !== [], 'resource_id' => $row['id'], 'grants' => $grants] + ($stale ? ['stale' => true] : []);
    }

    /**
     * @param array{type:string,id:string}                       $subject
     * @param array{id:string}|array{type:string,external_id:string} $resource
     * @param array{orgs?:list<string>,groups?:list<string>}      $options
     */
    public function can(array $subject, string $permission, array $resource, array $options = []): bool
    {
        return $this->check($subject, $permission, $resource, $options)['allowed'];
    }

    public function lastSyncAt(): ?int
    {
        return $this->store->getLastSyncAt();
    }

    /** @return int how many assignment changes were applied */
    public function sync(): int
    {
        if ($this->treeAt === null || ($this->now)() - $this->treeAt >= $this->treeRefreshMs) {
            $types = array_map(
                static fn (array $t): array => ['key' => $t['key'], 'inherit' => $t['inherit'] ?? true, 'roles' => $t['roles'] ?? []],
                $this->http->request('GET', '/v1/resources/types')['data'] ?? [],
            );
            $tree = [];
            $cursor = null;
            while (true) {
                $page = $this->http->request('GET', '/v1/resources', query: ['limit' => 1000] + ($cursor !== null ? ['starting_after' => $cursor] : []));
                foreach ($page['data'] ?? [] as $r) {
                    $tree[] = ['id' => $r['id'], 'type' => $r['type'], 'external_id' => $r['external_id'], 'parent_id' => $r['parent_id'] ?? null];
                }
                if (empty($page['has_more']) || !is_string($page['next_cursor'] ?? null)) {
                    break;
                }
                $cursor = $page['next_cursor'];
            }
            $this->store->replaceTypes($types);
            $this->store->replaceResources($tree);
            $this->treeAt = ($this->now)();
        }
        $after = $this->store->getCursor();
        $applied = 0;
        while (true) {
            $page = $this->http->request('GET', '/v1/resource_role_assignments/changes', query: ['limit' => 500] + ($after !== null ? ['after' => $after] : []));
            $changes = [];
            foreach ($page['data'] ?? [] as $c) {
                $changes[] = ['change' => (string) $c['change'], 'assignment' => [
                    'id' => $c['assignment_id'],
                    'resource_id' => $c['resource_id'],
                    'subject' => $c['subject']['type'] . ':' . $c['subject']['id'],
                    'role' => $c['role'],
                    'inherit' => $c['inherit'] ?? true,
                ]];
            }
            $this->store->applyChanges($changes);
            $applied += count($changes);
            if (is_string($page['next_cursor'] ?? null)) {
                $after = $page['next_cursor'];
                $this->store->setCursor($after);
            }
            if (empty($page['has_more'])) {
                $this->store->setLastSyncAt(($this->now)());

                return $applied;
            }
        }
    }

    /**
     * @param array{type?:mixed,data?:mixed} $event
     *
     * @return bool whether the event was a role-assignment change that was applied
     */
    public function applyWebhook(array $event): bool
    {
        $type = $event['type'] ?? null;
        $d = is_array($event['data'] ?? null) ? $event['data'] : [];
        if (($type !== 'resource_role_assignment.created' && $type !== 'resource_role_assignment.deleted')
            || !is_string($d['id'] ?? null) || !is_string($d['resource_id'] ?? null) || !is_string($d['role'] ?? null)
            || !is_string($d['subject']['type'] ?? null) || !is_string($d['subject']['id'] ?? null)) {
            return false;
        }
        $this->store->applyChanges([['change' => $type === 'resource_role_assignment.created' ? 'created' : 'deleted', 'assignment' => [
            'id' => $d['id'], 'resource_id' => $d['resource_id'], 'subject' => $d['subject']['type'] . ':' . $d['subject']['id'], 'role' => $d['role'], 'inherit' => ($d['inherit'] ?? true) !== false,
        ]]]);

        return true;
    }

    /**
     * The resource and its ancestors, nearest first, each with its type and assignments.
     *
     * @param array<string,mixed> $resource
     *
     * @return list<array{resourceId:string,type:array<string,mixed>,assignments:list<array<string,mixed>>}>
     */
    private function chain(array $resource): array
    {
        $rows = [$resource];
        $id = $resource['parent_id'] ?? null;
        for ($i = 0; $i < self::MAX_DEPTH && $id !== null; $i++) {
            $row = $this->store->getResource(['id' => $id]);
            if ($row === null || in_array($row['id'], array_column($rows, 'id'), true)) {
                break;
            }
            $rows[] = $row;
            $id = $row['parent_id'] ?? null;
        }
        $assignments = $this->store->assignmentsOn(array_column($rows, 'id'));

        return array_map(function (array $r) use ($assignments): array {
            $t = $this->store->getType($r['type']);

            return [
                'resourceId' => $r['id'],
                'type' => ['inherit' => $t['inherit'] ?? true, 'roles' => $t['roles'] ?? []],
                'assignments' => array_values(array_filter($assignments, static fn (array $a): bool => $a['resource_id'] === $r['id'])),
            ];
        }, $rows);
    }

    /**
     * @param list<array{resourceId:string,type:array<string,mixed>,assignments:list<array<string,mixed>>}> $chain
     * @param list<string>                                                                                   $subjects
     *
     * @return list<array{resource_id:string,role:string,subject:string,inherited:bool}>
     */
    private static function resolve(array $chain, array $subjects, string $permission): array
    {
        $grants = [];
        foreach ($chain as $i => $link) {
            foreach ($link['assignments'] as $a) {
                if (!in_array($a['subject'], $subjects, true) || ($i > 0 && empty($a['inherit']))) {
                    continue;
                }
                $perms = self::rolePermissions($link['type']['roles'], (string) $a['role']);
                if (isset($perms[$permission]) || isset($perms['*'])) {
                    $grants[] = ['resource_id' => $link['resourceId'], 'role' => (string) $a['role'], 'subject' => (string) $a['subject'], 'inherited' => $i > 0];
                }
            }
            if (!$link['type']['inherit']) {
                break;
            }
        }

        return $grants;
    }

    /**
     * @param list<array<string,mixed>> $roles
     * @param array<string,bool>        $seen
     *
     * @return array<string,bool>
     */
    private static function rolePermissions(array $roles, string $key, array &$seen = []): array
    {
        if (isset($seen[$key])) {
            return [];
        }
        $seen[$key] = true;
        foreach ($roles as $role) {
            if (($role['key'] ?? null) === $key) {
                $out = array_fill_keys($role['permissions'] ?? [], true);
                foreach ($role['includes'] ?? [] as $inc) {
                    $out += self::rolePermissions($roles, (string) $inc, $seen);
                }

                return $out;
            }
        }

        return [];
    }
}
