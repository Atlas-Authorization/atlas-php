<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/attack_protection` — brute-force, breached-password, IP and captcha config. */
final class AttackProtection extends Resource
{
    /** @return array<string,mixed> */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/attack_protection');
    }

    /**
     * Only the two flags are writable; the rest of the object is read-only.
     *
     * @param array{brute_force?:array{enabled?:bool},breached_password?:array{enabled?:bool}} $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/attack_protection', body: $body);
    }
}
