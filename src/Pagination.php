<?php

declare(strict_types=1);

namespace Atlas;

/**
 * Cursor-pagination helpers.
 *
 * Walk every page of a cursor-paginated BAPI list, yielding items one at a
 * time. Works with any resource `list` that takes a params array carrying
 * `limit`/`starting_after` (plus any route-specific filters) and returns a
 * `{ data, has_more, next_cursor }` page — `users->list`, `organizations->list`,
 * `invitations->list`, `auditLogs->list`, `waitlist->list`.
 *
 *     foreach (Pagination::iterate([$atlas->users, 'list']) as $user) { ... }
 *
 * Kept as static helpers rather than bolted onto every return value: the page is
 * the primitive most callers want, and a caller who needs the whole set opts
 * into the extra round trips explicitly.
 */
final class Pagination
{
    /**
     * Yield every item across every cursor page of a `list` callable.
     *
     * @param callable(array<string,mixed>):array<string,mixed> $list
     * @param array<string,mixed>                               $params
     *
     * @return \Generator<int,mixed>
     */
    public static function iterate(callable $list, array $params = []): \Generator
    {
        $cursor = $params['starting_after'] ?? null;
        while (true) {
            $page = $list([...$params, 'starting_after' => $cursor]);
            foreach (($page['data'] ?? []) as $item) {
                yield $item;
            }
            $cursor = $page['next_cursor'] ?? null;
            if (empty($page['has_more']) || $cursor === null) {
                return;
            }
        }
    }

    /**
     * Collect every page of a cursor-paginated list into one array.
     *
     * @param callable(array<string,mixed>):array<string,mixed> $list
     * @param array<string,mixed>                               $params
     *
     * @return list<mixed>
     */
    public static function collect(callable $list, array $params = []): array
    {
        return iterator_to_array(self::iterate($list, $params), false);
    }
}
