<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/email_addresses` — act on an email address by its own id.
 */
final class EmailAddresses extends Resource
{
    /**
     * `POST /v1/email_addresses/:eid/verify` — mark an email address verified (the owning user is derived
     * from the address).
     *
     * @return array<string,mixed>
     */
    public function verify(string $emailAddressId, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/email_addresses/' . rawurlencode($emailAddressId) . '/verify',
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/email_addresses/:eid/primary` — make a verified email address its user's primary address.
     *
     * @return array<string,mixed>
     */
    public function setPrimary(string $emailAddressId, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/email_addresses/' . rawurlencode($emailAddressId) . '/primary',
            idempotencyKey: $idempotencyKey,
        );
    }
}
