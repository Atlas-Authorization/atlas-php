<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/platform/atlas_id_publisher` — the account's Sign in with Atlas publisher name and
 * DNS-verified domain. Once verified, consent screens of apps on that domain name the publisher.
 *
 * @phpstan-type AtlasIdPublisher array{object:'atlas_id_publisher',name:string,domain:string,verified:bool,verified_at?:int|null,skip_consent:bool,verification:array{record_name:string,record_type:string,record_value:string},last_checked_at?:int|null,created_at?:int,updated_at?:int,record_found?:bool}
 */
final class PlatformAtlasIdPublisher extends Resource
{
    /**
     * `GET /v1/platform/atlas_id_publisher` — the publisher (404 until claimed).
     *
     * @return AtlasIdPublisher
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/platform/atlas_id_publisher');
    }

    /**
     * `PUT /v1/platform/atlas_id_publisher` — claim a publisher name and domain; returns the TXT
     * `verification` record to publish.
     *
     * @param array{name:string,domain:string,skip_consent?:bool} $body
     *
     * @return AtlasIdPublisher
     */
    public function set(array $body): array
    {
        return $this->http->request('PUT', '/v1/platform/atlas_id_publisher', body: $body);
    }

    /**
     * `PATCH /v1/platform/atlas_id_publisher` — turn publisher-wide consent on or off.
     *
     * @return AtlasIdPublisher
     */
    public function setSkipConsent(bool $skipConsent): array
    {
        return $this->http->request('PATCH', '/v1/platform/atlas_id_publisher', body: ['skip_consent' => $skipConsent]);
    }

    /**
     * `DELETE /v1/platform/atlas_id_publisher` — remove the publisher claim.
     *
     * @return array{object:string,deleted:bool}
     */
    public function delete(): array
    {
        return $this->http->request('DELETE', '/v1/platform/atlas_id_publisher');
    }

    /**
     * `POST /v1/platform/atlas_id_publisher/verify` — look up the TXT record now (`record_found`).
     *
     * @return AtlasIdPublisher
     */
    public function verify(): array
    {
        return $this->http->request('POST', '/v1/platform/atlas_id_publisher/verify');
    }
}
