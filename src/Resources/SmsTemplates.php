<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/sms_templates` — transactional SMS template overrides, keyed by name. */
final class SmsTemplates extends Resource
{
    /** @return array<string,mixed> a ListPage of templates */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/sms_templates');
    }

    /**
     * Upsert an override by name.
     *
     * @param array{name:string,body:string,enabled?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/sms_templates', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $name): array
    {
        return $this->http->request('GET', '/v1/sms_templates/' . rawurlencode($name));
    }

    /**
     * @param array{body?:string,enabled?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $name, array $body): array
    {
        return $this->http->request('PATCH', '/v1/sms_templates/' . rawurlencode($name), body: $body);
    }

    /** Revert to the built-in. @return array<string,mixed> */
    public function delete(string $name): array
    {
        return $this->http->request('DELETE', '/v1/sms_templates/' . rawurlencode($name));
    }

    /**
     * Render an unsaved draft (if a body is given) or the stored/built-in.
     *
     * @param array{body?:string} $body
     *
     * @return array<string,mixed>
     */
    public function preview(string $name, array $body = []): array
    {
        return $this->http->request('POST', '/v1/sms_templates/' . rawurlencode($name) . '/preview', body: $body);
    }
}
