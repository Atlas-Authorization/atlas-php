<?php

declare(strict_types=1);

namespace Atlas\Machine;

use Atlas\AtlasException;

/** A machine-client call failed. `$status` is the HTTP status (0 for a local error), `$errorCode` the API's error code when it sent one. */
final class MachineClientException extends AtlasException
{
    public function __construct(public readonly int $status, public readonly ?string $errorCode, string $message)
    {
        parent::__construct($message, $status);
    }
}
