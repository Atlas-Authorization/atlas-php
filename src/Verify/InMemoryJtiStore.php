<?php

declare(strict_types=1);

namespace Atlas\Verify;

/**
 * An in-process {@see JtiStore}. It only protects a single long-running
 * process (a worker, a test); a PHP-FPM request starts empty, so in production
 * implement {@see JtiStore} over a shared store.
 */
final class InMemoryJtiStore implements JtiStore
{
    /** @var array<string,int> jti => expires-at (epoch seconds) */
    private array $seen = [];

    /** @var (callable():int)|null */
    private $now;

    /** @param (callable():int)|null $now clock returning epoch seconds (tests) */
    public function __construct(?callable $now = null)
    {
        $this->now = $now;
    }

    public function add(string $jti, int $ttlSeconds): bool
    {
        $now = $this->now !== null ? (int) ($this->now)() : time();
        if (count($this->seen) > 1000) {
            $this->seen = array_filter($this->seen, static fn (int $expires): bool => $expires > $now);
        }
        if (isset($this->seen[$jti]) && $this->seen[$jti] > $now) {
            return false;
        }
        $this->seen[$jti] = $now + $ttlSeconds;

        return true;
    }
}
