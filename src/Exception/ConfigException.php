<?php

declare(strict_types=1);

namespace Atlas\Exception;

use Atlas\AtlasException;

/** Raised for a misconfigured client (missing secret key, no HTTP client, …). */
final class ConfigException extends AtlasException
{
}
