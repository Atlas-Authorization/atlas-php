<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/platform/domains` — domains your account verified by DNS. Verified domains gate test sign-in mode and the
 * test-account domain restriction of Sign in with Atlas apps.
 *
 *     $d = $atlas->platform->domains->claim('example.com');   // publish the TXT record it returns
 *     $atlas->platform->domains->check($d['id']);
 *
 * @phpstan-type AccountDomain array{object:'account_domain',id:string,domain:string,verified:bool,verified_at:int|null,last_checked_at:int|null,verification:array{type:'TXT',name:string,value:string},record_found?:bool,created_at:int}
 */
final class PlatformDomains extends Resource
{
    /**
     * `GET /v1/platform/domains`.
     *
     * @return array{object:string,data:list<AccountDomain>,verified_domains:list<string>}
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/platform/domains');
    }

    /**
     * `POST /v1/platform/domains` — claim a domain; the answer carries the TXT record to publish.
     *
     * @return AccountDomain
     */
    public function claim(string $domain): array
    {
        return $this->http->request('POST', '/v1/platform/domains', body: ['domain' => $domain]);
    }

    /**
     * `POST /v1/platform/domains/:id/verify` — check the DNS record now.
     *
     * @return AccountDomain
     */
    public function check(string $domainId): array
    {
        return $this->http->request('POST', '/v1/platform/domains/' . rawurlencode($domainId) . '/verify');
    }

    /**
     * Alias of {@see check()}.
     *
     * @return AccountDomain
     */
    public function verify(string $domainId): array
    {
        return $this->check($domainId);
    }

    /**
     * `DELETE /v1/platform/domains/:id`.
     *
     * @return array<string,mixed>
     */
    public function remove(string $domainId): array
    {
        return $this->http->request('DELETE', '/v1/platform/domains/' . rawurlencode($domainId));
    }
}
