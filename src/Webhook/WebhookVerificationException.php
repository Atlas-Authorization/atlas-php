<?php

declare(strict_types=1);

namespace Atlas\Webhook;

use Atlas\AtlasException;

/** A webhook delivery did not verify; `$reason` is the failure code (`missing_headers`, `stale_timestamp`, `signature_mismatch`, `invalid_body`, ...). */
final class WebhookVerificationException extends AtlasException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Atlas webhook verification failed: ' . $reason, 400);
    }
}
