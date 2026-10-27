<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/access_policies` - 6 operations.
 */
final class AccessPolicies extends Resource
{
    /**
     * `GET /v1/access_policies` - List conditional-access policies, in evaluation order.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/access_policies', query: $params);
    }

    /**
     * `POST /v1/access_policies` - Create a conditional-access policy. `conditions` (applications,
     * userIds, groups, orgs, roles, networkZones {include, exclude}, countries, riskLevels, deviceTrust,
     * authMethods, timeWindow) are ANDed; an empty condition matches anything. `grants` are allow, block,
     * require_mfa, require_acr, require_policy_acceptance or require_action. `stages` limits it to
     * sign_in, authorize and/or token (default all). A matching block wins; step-up grants are unioned.
     *
     * @param array{name?:string,description?:string|null,enabled?:bool,priority?:int,stages?:list<'sign_in'|'authorize'|'token'>,conditions?:array<string,mixed>,grants?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/access_policies',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/access_policies/simulate` - What-if: describe a request (stage, ip, application, orgs,
     * roles, country, risk level, device trust, auth methods, whether MFA was verified) and see the
     * decision, the policies that matched and the unmet step-up requirements. Pass `policy_id` to test one
     * policy even if it is disabled.
     *
     * @param array{policy_id?:string,stage?:string,ip?:string,application_id?:string,user_id?:string,groups?:list<string>,orgs?:list<string>,roles?:list<string>,country?:string,risk_level?:'low'|'medium'|'high',device_trust?:'trusted'|'untrusted'|'unknown',auth_methods?:list<string>,mfa_verified?:bool,acr?:list<string>,now?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function simulate(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/access_policies/simulate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/access_policies/:id` - Read one access policy.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/access_policies/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/access_policies/:id` - Update an access policy. Sent fields replace the stored value.
     *
     * @param array{name?:string,description?:string|null,enabled?:bool,priority?:int,stages?:list<'sign_in'|'authorize'|'token'>,conditions?:array<string,mixed>,grants?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/access_policies/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/access_policies/:id` - Delete an access policy.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/access_policies/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
