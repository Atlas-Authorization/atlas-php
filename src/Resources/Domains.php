<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/domains` — custom FAPI/accounts domains: a CNAME to Atlas, or (`method: 'path'`) a path
 * mount on a host you already run, proven by an https file (no DNS).
 */
final class Domains extends Resource
{
    /** @return array<string,mixed> a list envelope with CNAME instructions */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/domains');
    }

    /**
     * A path mount: `['method' => 'path', 'hostname' => 'app.example.com', 'base_path' => '/__atlas']`
     * (`base_path` defaults to `/__atlas`; `hostname` is an alias of `host`). The answer carries
     * `method`, `base_path`, `frontend_api_url` and `verification` (`{method:'https_file', url, token,
     * content, instructions}`: publish `content` at `url`, then {@see verify()}). `cname_target` is
     * null for a path mount.
     *
     * @param array{host?:string,hostname?:string,role?:string,method?:'cname'|'path',base_path?:string,dns?:array{provider:string,api_token:string}} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/domains', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * A verified path mount also carries `routing` (`atlas_ingress` | `customer_proxy`), `instructions`
     * (the reverse-proxy rule, when yours routes it) and `issuer` (`https://<host><base_path>`, the
     * issuer tokens now carry: keep the old one in `previousIssuers` while sessions roll over).
     *
     * @return array<string,mixed>
     */
    public function verify(string $id): array
    {
        return $this->http->request('POST', '/v1/domains/' . rawurlencode($id) . '/verify');
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/domains/' . rawurlencode($id));
    }
}
