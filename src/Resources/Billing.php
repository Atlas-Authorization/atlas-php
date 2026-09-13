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
}
