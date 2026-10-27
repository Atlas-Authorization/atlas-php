<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/agent_registration` - 2 operations.
 */
final class AgentRegistration extends Resource
{
    /**
     * `GET /v1/agent_registration` - Read the agent registration settings (enabled, allowed identity
     * kinds, untrusted and trusted scopes, claim code lifetime).
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/agent_registration');
    }

    /**
     * `PUT /v1/agent_registration` - Update the agent registration settings. Partial: only the fields sent
     * change.
     *
     * @param array{enabled?:bool,allow_anonymous?:bool,allow_service_auth?:bool,allow_refresh?:bool,untrusted_scopes?:list<string>,trusted_scopes?:list<string>,claim_ttl_seconds?:int,auto_approve_rules?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function replace(array $body, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'PUT',
            '/v1/agent_registration',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
