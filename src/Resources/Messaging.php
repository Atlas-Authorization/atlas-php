<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/messaging_providers` — BYOK email/SMS provider config (secrets write-only). */
final class Messaging extends Resource
{
    /** The instance's configured email and SMS providers, secrets omitted. @return array<string,mixed> */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/messaging_providers');
    }

    /**
     * @param array{transport:string,from_address?:string,config?:array<string,mixed>} $body
     *
     * @return array<string,mixed>
     */
    public function setEmail(array $body): array
    {
        return $this->http->request('PUT', '/v1/messaging_providers/email', body: $body);
    }

    /**
     * @param array{transport:string,from_address?:string,config?:array<string,mixed>} $body
     *
     * @return array<string,mixed>
     */
    public function setSms(array $body): array
    {
        return $this->http->request('PUT', '/v1/messaging_providers/sms', body: $body);
    }

    /** @return array<string,mixed> */
    public function deleteChannel(string $channel): array
    {
        return $this->http->request('DELETE', '/v1/messaging_providers/' . rawurlencode($channel));
    }

    /**
     * Send a fixed, clearly-marked test message through the channel's provider.
     *
     * @param array{to:string} $body
     *
     * @return array<string,mixed>
     */
    public function test(string $channel, array $body): array
    {
        return $this->http->request('POST', '/v1/messaging_providers/' . rawurlencode($channel) . '/test', body: $body);
    }
}
