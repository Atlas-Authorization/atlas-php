<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\Exception\ConfigException;

/**
 * DPoP nonces derived from a secret and the clock: a nonce is good for the
 * window it was issued in and the next one, so every PHP process sharing the
 * secret agrees without shared state (it suits PHP-FPM, where nothing survives
 * a request).
 */
final class HmacDpopNonceSource implements DpopNonceSource
{
    /** @var (callable():int)|null */
    private $now;

    /**
     * @param string               $secret        at least 32 bytes
     * @param int                  $windowSeconds how long one nonce is issued for
     * @param (callable():int)|null $now          clock returning epoch seconds (tests)
     */
    public function __construct(
        private readonly string $secret,
        private readonly int $windowSeconds = 300,
        ?callable $now = null,
    ) {
        if (strlen($secret) < 32) {
            throw new ConfigException('The DPoP nonce secret must be at least 32 bytes.');
        }
        if ($windowSeconds <= 0) {
            throw new ConfigException('The DPoP nonce window must be positive.');
        }
        $this->now = $now;
    }

    public function current(): string
    {
        return $this->at($this->epoch());
    }

    public function valid(string $nonce): bool
    {
        $e = $this->epoch();

        return hash_equals($this->at($e), $nonce) || hash_equals($this->at($e - 1), $nonce);
    }

    private function epoch(): int
    {
        $now = $this->now !== null ? (int) ($this->now)() : time();

        return intdiv($now, $this->windowSeconds);
    }

    private function at(int $epoch): string
    {
        return Dpop::b64u(hash_hmac('sha256', 'atlas-dpop-nonce:' . pack('J', $epoch), $this->secret, true));
    }
}
