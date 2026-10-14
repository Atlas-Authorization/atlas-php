<?php

declare(strict_types=1);

namespace Atlas;

/**
 * What the `onUnknownFields` callback receives: the body fields a successful
 * write sent that the endpoint does not accept (and therefore ignored), read
 * from the `Atlas-Unknown-Fields` response header.
 */
final class UnknownFields
{
    /**
     * @param list<string> $fields dotted paths, e.g. `nmae` or `profile.colour`
     */
    public function __construct(
        public readonly array $fields,
        public readonly string $method,
        public readonly string $path,
    ) {
    }
}
