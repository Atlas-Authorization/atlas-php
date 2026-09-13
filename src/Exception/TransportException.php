<?php

declare(strict_types=1);

namespace Atlas\Exception;

use Atlas\AtlasException;

/**
 * Raised when the underlying PSR-18 client fails to complete a request at all
 * (DNS, TCP, TLS, timeout) — i.e. no HTTP response was received. An HTTP
 * response with a non-2xx status is an {@see AtlasApiException}, not this.
 */
final class TransportException extends AtlasException
{
}
