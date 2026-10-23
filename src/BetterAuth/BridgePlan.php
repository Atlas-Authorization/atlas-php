<?php

declare(strict_types=1);

namespace Atlas\BetterAuth;

/**
 * What a framework adapter does with one request, as {@see SessionBridge::plan()} decides it:
 * redirect (303) to `$redirectTo` with `$setCookies`, or (when `$redirectTo` is null) continue and
 * append `$setCookies` (only Better Auth clears) to the response.
 */
final class BridgePlan
{
    /** @param list<string> $setCookies `Set-Cookie` header values */
    public function __construct(
        public readonly ?string $redirectTo = null,
        public readonly array $setCookies = [],
    ) {
    }

    public function redirects(): bool
    {
        return $this->redirectTo !== null;
    }
}
