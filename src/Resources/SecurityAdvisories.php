<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * Security advisories: those that apply to the instance, acknowledgement, and the public catalogue.
 */
final class SecurityAdvisories extends Resource
{
    /**
     * Advisories that apply (adds `applying`).
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/security_advisories');
    }

    /**
     * Acknowledge an advisory.
     *
     * @return array<string,mixed>
     */
    public function acknowledge(string $id): array
    {
        return $this->http->request('POST', '/v1/security_advisories/' . rawurlencode($id) . '/acknowledge');
    }

    /**
     * The public catalogue of advisories (no key needed).
     *
     * @return array<string,mixed>
     */
    public function catalog(): array
    {
        return $this->http->request('GET', '/v1/public/security_advisories');
    }
}
