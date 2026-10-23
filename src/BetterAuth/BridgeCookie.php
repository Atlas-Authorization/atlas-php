<?php

declare(strict_types=1);

namespace Atlas\BetterAuth;

/** A cookie to set (or, with `maxAge` 0, clear) on the response. */
final class BridgeCookie
{
    /**
     * @param 'Lax'|'Strict'|'None'|null $sameSite
     */
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly string $path = '/',
        public readonly ?string $domain = null,
        public readonly bool $httpOnly = false,
        public readonly ?string $sameSite = null,
        public readonly bool $secure = false,
        public readonly ?int $maxAge = null,
    ) {
    }

    /** The cookie as a `Set-Cookie` header value. */
    public function toHeader(): string
    {
        $parts = [$this->name . '=' . $this->value, 'Path=' . ($this->path !== '' ? $this->path : '/')];
        if ($this->domain !== null && $this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }
        if ($this->maxAge !== null) {
            $parts[] = 'Max-Age=' . $this->maxAge;
            if ($this->maxAge === 0) {
                $parts[] = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT';
            }
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($this->sameSite !== null) {
            $parts[] = 'SameSite=' . $this->sameSite;
        }

        return implode('; ', $parts);
    }
}
