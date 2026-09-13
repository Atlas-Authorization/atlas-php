<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * Base for every resource namespace: it holds the shared, config-bound
 * {@see Http} transport and turns a call into a single request naming a method,
 * a path, and its shapes. The surface mirrors the official TypeScript SDK
 * (`@atlas/backend`) namespace-for-namespace.
 */
abstract class Resource
{
    public function __construct(protected readonly Http $http)
    {
    }
}
