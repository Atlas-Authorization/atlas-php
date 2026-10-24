<?php

declare(strict_types=1);

namespace Atlas;

/**
 * One entry of the Atlas Backend API error envelope (§9.1):
 *
 *     { "errors": [ { "code", "message", "param?", "meta?" } ] }
 *
 * `code` is the stable, machine-readable part of the contract — integrators
 * branch on it (`LAST_ADMIN`, `NOT_FOUND`, `INSUFFICIENT_SCOPE`, …) — so it is
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
        /** @var list<string>|null routes Atlas thinks you meant (`ROUTE_NOT_FOUND`) */
        public readonly ?array $didYouMean = null,
        /** @var list<string>|null methods the route accepts (`ROUTE_NOT_FOUND` on a wrong method) */
        public readonly ?array $allowedMethods = null,
        /** the page that explains the code, `https://atlasauth.net/errors/<code>` */
        public readonly ?string $docsUrl = null,
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
            didYouMean: self::strings($item['did_you_mean'] ?? null),
            allowedMethods: self::strings($item['allowed_methods'] ?? null),
            docsUrl: isset($item['docs_url']) && is_string($item['docs_url']) ? $item['docs_url'] : null,
        );
    }

    /**
     * @return list<string>|null
     */
    private static function strings(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return array_values(array_map('strval', array_filter($value, 'is_scalar')));
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
            'did_you_mean' => $this->didYouMean,
            'allowed_methods' => $this->allowedMethods,
            'docs_url' => $this->docsUrl,
        ], static fn ($v) => $v !== null);
    }
}
