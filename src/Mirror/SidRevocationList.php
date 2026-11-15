<?php

declare(strict_types=1);

namespace Atlas\Mirror;

use Atlas\Http;

/**
 * Reject a session token whose session Atlas has revoked, without a call per request: poll `GET /v1/sessions/revoked` (needs a secret key
 * with `sessions:read`) at most every `$intervalMs` and keep each revoked `sid` until its token cannot be live any more. The interval plus
 * the token lifetime is the worst-case window.
 *
 *     $revoked = new SidRevocationList($atlas->http, onStale: 'reject');
 *     if ($revoked->isRevoked($claims['sid']) || $revoked->unavailable()) { deny(); }
 *
 * A failed poll never rejects or accepts more than before: the last list stays in force (see `onStale`).
 */
final class SidRevocationList
{
    /** @var array<string,int> sid => epoch ms until which it is remembered */
    private array $revoked = [];
    private ?int $since = null;
    private int $nextPollAt = 0;
    private ?int $lastSuccessAt = null;
    private readonly int $createdAt;
    /** @var callable():int */
    private $now;
    /** @var (callable(\Throwable):void)|null */
    private $onError;
    /** @var (callable(array{lastSuccessAt:?int,staleForMs:int}):void)|null */
    private $onServeStale;

    /**
     * @param int                                            $intervalMs              how often to poll (default 15 s)
     * @param int                                            $maxTokenLifetimeSeconds your longest-lived session token; a listed sid is remembered this long (default 3600)
     * @param 'allow'|'reject'                               $onStale                 when the list cannot be refreshed for `$staleAfterMs`: keep trusting the last list (`allow`, default) or fail closed (`reject`: {@see unavailable()} turns true)
     * @param int                                            $serveStaleForMs         with `reject`: keep answering from the last list this many extra ms before failing closed
     * @param (callable():int)|null                          $now                     epoch ms (tests)
     */
    public function __construct(
        private readonly Http $http,
        private readonly int $intervalMs = 15_000,
        private readonly int $maxTokenLifetimeSeconds = 3600,
        private readonly string $onStale = 'allow',
        private readonly int $staleAfterMs = 120_000,
        private readonly int $serveStaleForMs = 0,
        ?callable $now = null,
        ?callable $onError = null,
        ?callable $onServeStale = null,
    ) {
        $this->now = $now ?? static fn (): int => (int) (microtime(true) * 1000);
        $this->onError = $onError;
        $this->onServeStale = $onServeStale;
        $this->createdAt = ($this->now)();
    }

    public function isRevoked(string $sid): bool
    {
        $now = ($this->now)();
        if ($now >= $this->nextPollAt) {
            $this->poll();
        }

        return ($this->revoked[$sid] ?? 0) > $now;
    }

    /** True when the list could not be refreshed for `staleAfterMs` and you asked to fail closed. Call after {@see isRevoked()}. */
    public function unavailable(): bool
    {
        if ($this->onStale !== 'reject') {
            return false;
        }
        $stale = ($this->now)() - ($this->lastSuccessAt ?? $this->createdAt) - $this->staleAfterMs;
        if ($stale <= 0) {
            return false;
        }
        // A list fetched at least once may be served a little longer; one never fetched may not.
        if ($this->lastSuccessAt !== null && $stale <= $this->serveStaleForMs) {
            if ($this->onServeStale !== null) {
                ($this->onServeStale)(['lastSuccessAt' => $this->lastSuccessAt, 'staleForMs' => $stale]);
            }

            return false;
        }

        return true;
    }

    private function poll(): void
    {
        $started = ($this->now)();
        $this->nextPollAt = $started + $this->intervalMs;
        $lifetimeMs = $this->maxTokenLifetimeSeconds * 1000;
        try {
            $cursor = null;
            $serverTime = $started;
            do {
                $query = ['since' => $this->since ?? $started - $lifetimeMs, 'limit' => 5000] + ($cursor !== null ? ['cursor' => $cursor] : []);
                $page = $this->http->request('GET', '/v1/sessions/revoked', query: $query);
                foreach (($page['data'] ?? []) as $row) {
                    $this->revoked[(string) $row['sid']] = (int) $row['revoked_at'] + $lifetimeMs;
                }
                $serverTime = (int) ($page['server_time'] ?? $serverTime);
                $cursor = !empty($page['has_more']) && is_string($page['next_cursor'] ?? null) ? $page['next_cursor'] : null;
            } while ($cursor !== null);
            // Re-ask from a little before the answer's own clock, so a row committed late is not missed.
            $this->since = $serverTime - 10_000;
            $this->lastSuccessAt = $started;
            $this->revoked = array_filter($this->revoked, static fn (int $expiry): bool => $expiry > $started);
        } catch (\Throwable $e) {
            if ($this->onError !== null) {
                ($this->onError)($e);
            }
        }
    }
}
