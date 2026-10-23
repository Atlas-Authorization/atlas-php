<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/billing` — the tenant's own billing plans and a read view of subscriptions. */
final class Billing extends Resource
{
    /** @return array<string,mixed> a ListPage of plans */
    public function listPlans(): array
    {
        return $this->http->request('GET', '/v1/billing/plans');
    }

    /**
     * @param array{name:string,slug:string,stripe_price_id:string,interval?:string,amount?:string,currency?:string,features?:list<string>,audience?:string,active?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function createPlan(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/billing/plans', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function getPlan(string $id): array
    {
        return $this->http->request('GET', '/v1/billing/plans/' . rawurlencode($id));
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function updatePlan(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/billing/plans/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function deletePlan(string $id): array
    {
        return $this->http->request('DELETE', '/v1/billing/plans/' . rawurlencode($id));
    }

    /**
     * @param array{subject_type?:string,subject_id?:string} $params
     *
     * @return array<string,mixed> a ListPage of subscriptions
     */
    public function listSubscriptions(array $params = []): array
    {
        return $this->http->request('GET', '/v1/billing/subscriptions', query: $params);
    }

    /**
     * `POST /v1/billing/subjects/:type/:id/sync_seats` — sync a per-seat subscription's quantity to the
     * organization's current member count.
     *
     * @return array<string,mixed>
     */
    public function syncSeats(string $subjectType, string $subjectId): array
    {
        return $this->http->request(
            'POST',
            '/v1/billing/subjects/' . rawurlencode($subjectType) . '/' . rawurlencode($subjectId) . '/sync_seats',
        );
    }

    /**
     * `POST /v1/billing/subscriptions/import` — adopt an existing Stripe subscription onto an Atlas user
     * or organization without re-charging.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function importSubscription(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/billing/subscriptions/import',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/billing/usage` — report metered usage for a subject (idempotent on idempotency_key in the
     * body).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function reportUsage(array $body): array
    {
        return $this->http->request('POST', '/v1/billing/usage', body: $body);
    }

    /**
     * Switch a subscription to another plan or interval on the billing
     * provider, now (prorated) or with `schedule_at_period_end`.
     *
     * @param array{plan_id:string,interval?:'month'|'year',schedule_at_period_end?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function changeSubscription(string $id, array $body): array
    {
        return $this->http->request('POST', '/v1/billing/subscriptions/' . rawurlencode($id) . '/change', body: $body);
    }

    /**
     * Cancel at the end of the current period, or now with `$immediately`.
     *
     * @return array<string,mixed>
     */
    public function cancelSubscription(string $id, bool $immediately = false): array
    {
        $body = $immediately ? ['immediately' => true] : (object) [];

        return $this->http->request('POST', '/v1/billing/subscriptions/' . rawurlencode($id) . '/cancel', body: $body);
    }

    /**
     * Undo a cancellation scheduled for the period end.
     *
     * @return array<string,mixed>
     */
    public function restoreSubscription(string $id): array
    {
        return $this->http->request('POST', '/v1/billing/subscriptions/' . rawurlencode($id) . '/restore');
    }

    /**
     * The billing providers (stripe, polar, dodo, creem) this instance has its
     * OWN credentials for. Secrets are never returned (`secrets_set` only);
     * each carries the `webhook_url` to register with the provider.
     *
     * @return array<string,mixed>
     */
    public function listProviders(): array
    {
        return $this->http->request('GET', '/v1/billing/providers');
    }

    /** @return array<string,mixed> */
    public function getProvider(string $provider): array
    {
        return $this->http->request('GET', '/v1/billing/providers/' . rawurlencode($provider));
    }

    /**
     * Store the instance's credentials for a provider (merged onto what is
     * stored). `config` keys: Stripe `secret_key`, `webhook_secret`,
     * `publishable_key`; Polar `access_token`, `webhook_secret`; Dodo and Creem
     * `api_key`, `webhook_secret`; Chargebee `api_key`, `site`, `webhook_username` +
     * `webhook_password`; Autumn `secret_key`, `webhook_secret`; Commet `api_key`,
     * `webhook_secret`, optional `seat_feature_code`.
     *
     * @param array{environment?:'live'|'test',config?:array<string,string>} $body
     *
     * @return array<string,mixed>
     */
    public function setProvider(string $provider, array $body): array
    {
        return $this->http->request('PUT', '/v1/billing/providers/' . rawurlencode($provider), body: $body);
    }

    /**
     * One read-only call with the stored credentials: `{ ok, error, account }`.
     *
     * @return array<string,mixed>
     */
    public function testProvider(string $provider): array
    {
        return $this->http->request('POST', '/v1/billing/providers/' . rawurlencode($provider) . '/test');
    }

    /**
     * Remove a provider's credentials; refused while subscriptions or plans
     * depend on it unless `$force`.
     *
     * @return array<string,mixed>
     */
    public function deleteProvider(string $provider, bool $force = false): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/billing/providers/' . rawurlencode($provider),
            query: $force ? ['force' => 'true'] : null,
        );
    }

    /**
     * `GET /v1/billing/subscriptions/:id` — one subscription (a subject may hold several:
     * a base plan plus add-ons). Entitlement providers (Autumn, Commet) report what it
     * grants in `entitlements`.
     *
     * @return array<string,mixed>
     */
    public function getSubscription(string $id): array
    {
        return $this->http->request('GET', '/v1/billing/subscriptions/' . rawurlencode($id));
    }

    /**
     * `POST /v1/billing/plans/:id/sync_entitlements` — mirror a plan's features and limits
     * from its provider (Autumn, Commet).
     *
     * @return array<string,mixed>
     */
    public function syncPlanEntitlements(string $id): array
    {
        return $this->http->request('POST', '/v1/billing/plans/' . rawurlencode($id) . '/sync_entitlements');
    }

    /**
     * `GET /v1/billing/settings` — how numeric limits merge when a subject holds several
     * entitling subscriptions: `default_limit_merge` and `limit_merge` (`max` | `sum`).
     *
     * @return array<string,mixed>
     */
    public function getSettings(): array
    {
        return $this->http->request('GET', '/v1/billing/settings');
    }

    /**
     * `PUT /v1/billing/settings` — `limit_merge` replaces the stored map.
     *
     * @param array{default_limit_merge?:'max'|'sum',limit_merge?:array<string,'max'|'sum'>} $body
     *
     * @return array<string,mixed>
     */
    public function updateSettings(array $body): array
    {
        return $this->http->request('PUT', '/v1/billing/settings', body: $body);
    }

    /**
     * `POST /v1/billing/check` — may a subject use a feature? Asked live of Autumn/Commet
     * when the subject is on one (`source: provider`), otherwise from the resolved
     * entitlement and this period's usage.
     *
     * @param array{subject_type:'user'|'org',subject_id:string,feature:string,required?:int|float} $body
     *
     * @return array<string,mixed>
     */
    public function checkFeature(array $body): array
    {
        return $this->http->request('POST', '/v1/billing/check', body: $body);
    }
}
