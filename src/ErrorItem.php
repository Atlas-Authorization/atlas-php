<?php

declare(strict_types=1);

namespace Atlas;

/**
 * One entry of the Atlas Backend API error envelope (§9.1):
 *
 *     { "errors": [ { "code", "message", "param?", "meta?" } ] }
 *
 * `code` is the stable, machine-readable part of the contract — integrators
 * branch on it (`LAST_ADMIN`, `NOT_FOUND`, `SCOPE_MISSING`, …) — so it is
 * surfaced first-class. `param` and `meta` (e.g. a rate limit's `retry_after`)
 * are kept for callers that need them.
 */
final class ErrorItem
{
    /**
     * @param array<string,mixed>|null $meta
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $param = null,
        public readonly ?array $meta = null,
    ) {
    }

    /**
     * Build an item from a decoded envelope entry, tolerating missing fields.
     *
     * @param array<string,mixed> $item
     */
    public static function fromArray(array $item): self
    {
        $meta = $item['meta'] ?? null;

        return new self(
            code: isset($item['code']) ? (string) $item['code'] : 'UNKNOWN',
            message: isset($item['message']) ? (string) $item['message'] : '',
            param: isset($item['param']) ? (string) $item['param'] : null,
            meta: is_array($meta) ? $meta : null,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'message' => $this->message,
            'param' => $this->param,
            'meta' => $this->meta,
        ], static fn ($v) => $v !== null);
    }
}
