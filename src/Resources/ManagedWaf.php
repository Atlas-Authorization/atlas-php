<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/managed_waf` — the managed AWS WAF captcha gate control plane. */
final class ManagedWaf extends Resource
{
    /** Read config + provisioning state, with secrets reduced to `has_*` markers. @return array<string,mixed> */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/managed_waf');
    }

    /** Read just the provisioning state. @return array<string,mixed> */
    public function status(): array
    {
        return $this->http->request('GET', '/v1/managed_waf/status');
    }

    /**
     * Set config and write-only credentials; omitted fields are left unchanged.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PUT', '/v1/managed_waf', body: $body);
    }

    /** Apply now — idempotent create/update + associate the WebACL. @return array<string,mixed> */
    public function provision(): array
    {
        return $this->http->request('POST', '/v1/managed_waf/provision');
    }

    /** Disassociate and delete the WebACL. @return array<string,mixed> */
    public function deprovision(): array
    {
        return $this->http->request('POST', '/v1/managed_waf/deprovision');
    }
}
