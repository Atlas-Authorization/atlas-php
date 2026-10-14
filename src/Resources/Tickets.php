<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/tickets` — one-time capability tickets.
 */
final class Tickets extends Resource
{
    /**
     * `POST /v1/tickets` — mint a one-time capability ticket. The tkt_ secret is returned once.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/tickets', body: $body);
    }

    /**
     * `POST /v1/tickets/redeem` — redeem a ticket server-to-server and return its payload.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function redeem(array $body): array
    {
        return $this->http->request('POST', '/v1/tickets/redeem', body: $body);
    }
}
