<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/branding` — the hosted-page / email appearance bag. */
final class Branding extends Resource
{
    /** @return array<string,mixed> the stored branding plus its sanitized `resolved` view */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/branding');
    }

    /**
     * A PARTIAL merge — one field changes, the rest is preserved.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PATCH', '/v1/branding', body: $body);
    }

    /**
     * Render a draft through the live sign-in renderer without saving it.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed> `{ object: 'hosted_page_preview', html }`
     */
    public function preview(array $body): array
    {
        return $this->http->request('POST', '/v1/branding/preview', body: $body);
    }
}
