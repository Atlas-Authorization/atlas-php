<?php

declare(strict_types=1);

namespace Atlas;

/**
 * Base type for every exception this SDK raises. Catch this to catch anything
 * the SDK can throw — configuration errors, transport failures, and API errors
 * alike.
 */
class AtlasException extends \RuntimeException
{
}
