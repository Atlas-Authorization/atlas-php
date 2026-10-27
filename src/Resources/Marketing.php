<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1` - 4 operations.
 */
final class Marketing extends Resource
{
    /**
     * `GET /v1/marketing_settings` - The marketing settings: which UTM / ad-click-id parameters the SDK
     * captures onto new users, and the GA4, GTM and Hotjar ids hosted pages load after consent.
     *
     * @return array<string,mixed>
     */
    public function getSettings(): array
    {
        return $this->http->request('GET', '/v1/marketing_settings');
    }

    /**
     * `PUT /v1/marketing_settings` - Change the marketing settings. `capture.params` empty means the
     * standard set (utm_source, utm_medium, utm_campaign, utm_term, utm_content, utm_id and the common ad
     * click ids). Hosted pages load a configured script only when the visitor's consent manager grants
     * `scripts.consent_category` (default analytics); with no consent signal nothing loads.
     *
     * @param array{capture?:array{enabled?:bool,params?:list<mixed>,consent_category?:string},scripts?:array{ga4_measurement_id?:string|null,gtm_container_id?:string|null,hotjar_site_id?:int|null,consent_category?:string}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function updateSettings(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/marketing_settings',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/leads` - The lead / subscribe form's submissions, newest first, with the first-touch
     * attribution captured with each. `format=csv` exports up to 50,000.
     *
     * @param array{limit?:int,starting_after?:string,form?:string,format?:'json'|'csv'} $params
     *
     * @return array<string,mixed>
     */
    public function listLeads(array $params = []): array
    {
        return $this->http->request('GET', '/v1/leads', query: $params);
    }

    /**
     * `DELETE /v1/leads/:id` - Delete one captured lead.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function deleteLead(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/leads/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
