<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/email_templates` — transactional email template overrides, keyed by name. */
final class EmailTemplates extends Resource
{
    /** @return array<string,mixed> a ListPage of templates (default + override) */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/email_templates');
    }

    /**
     * Render an unsaved draft (if a body is given) or the stored template.
     *
     * @param array{subject?:string,text?:string} $body
     *
     * @return array<string,mixed>
     */
    public function preview(string $name, array $body = []): array
    {
        return $this->http->request('POST', '/v1/email_templates/' . rawurlencode($name) . '/preview', body: $body);
    }

    /**
     * Save an override. Keyed by template name, not an id.
     *
     * @param array{subject?:string,text?:string} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $name, array $body): array
    {
        return $this->http->request('PUT', '/v1/email_templates/' . rawurlencode($name), body: $body);
    }

    /** Revert to the built-in. @return array<string,mixed> */
    public function delete(string $name): array
    {
        return $this->http->request('DELETE', '/v1/email_templates/' . rawurlencode($name));
    }
}
