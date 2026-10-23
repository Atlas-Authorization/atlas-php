<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/instance/policy_settings` — the optional `pol` session claim. */
final class PolicySettings extends Resource
{
    /**
     * `GET /v1/instance/policy_settings` — `['session_claim' => bool]`.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance/policy_settings');
    }

    /**
     * `PATCH /v1/instance/policy_settings` — turn the `pol` session claim (the accepted version of
     * each required policy) on or off. Tokens minted after the change carry it; it can take 30
     * seconds to apply everywhere.
     *
     * @param array{session_claim?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/instance/policy_settings', body: $body);
    }
}
