<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/entitlement_overrides` — sales-led plan, feature and limit grants without Stripe.
 * A non-expired override wins over the Stripe-derived entitlement and reaches
 * the session claims on the next session. Read an organization's effective
 * entitlements with {@see Organizations::entitlements()}.
 *
 * @phpstan-type EntitlementOverrideInput array{subject_type:'user'|'org',subject_id:string,plan?:string|null,features?:list<string>|null,limits?:array<string,mixed>|null,expires_at?:int|null}
 * @phpstan-type EntitlementOverride array{object:string,id:string,subject_type:'user'|'org',subject_id:string,plan:string|null,features:list<string>|null,limits:array<string,mixed>|null,expires_at:int|null,created_by:string|null,created_at:int}
 */
final class EntitlementOverrides extends Resource
{
    /**
     * `POST /v1/entitlement_overrides` — pin a plan, features or limits onto a user or organization
     * without Stripe. At least one of plan, features or limits is required; expires_at is epoch ms.
     *
     * @param EntitlementOverrideInput $body
     *
     * @return EntitlementOverride
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/entitlement_overrides', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/entitlement_overrides` — entitlement overrides, optionally filtered by subject_type and
     * subject_id.
     *
     * @param array{subject_type?:'user'|'org',subject_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array{object?:string,data:list<EntitlementOverride>,has_more?:bool,next_cursor?:string|null}
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/entitlement_overrides', query: $params);
    }

    /**
     * `DELETE /v1/entitlement_overrides/:id` — revoke an entitlement override.
     *
     * @return array<string,mixed>
     */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/entitlement_overrides/' . rawurlencode($id));
    }
}
