<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/notification_templates` — your notification email templates.
 */
final class NotificationTemplates extends Resource
{
    /**
     * `GET /v1/notification_templates` — your notification templates.
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/notification_templates');
    }

    /**
     * `POST /v1/notification_templates` — create a notification template (name, subject, body, category).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/notification_templates', body: $body);
    }

    /**
     * `GET /v1/notification_templates/:name` — one notification template by name.
     *
     * @return array<string,mixed>
     */
    public function get(string $name): array
    {
        return $this->http->request('GET', '/v1/notification_templates/' . rawurlencode($name));
    }

    /**
     * `PUT /v1/notification_templates/:name` — update a template's subject, body and category.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $name, array $body): array
    {
        return $this->http->request('PUT', '/v1/notification_templates/' . rawurlencode($name), body: $body);
    }

    /**
     * `DELETE /v1/notification_templates/:name` — delete a notification template.
     *
     * @return array<string,mixed>
     */
    public function delete(string $name): array
    {
        return $this->http->request('DELETE', '/v1/notification_templates/' . rawurlencode($name));
    }
}
