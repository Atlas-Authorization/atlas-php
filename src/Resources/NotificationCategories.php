<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/notification_categories` — your notification categories (each a user preference toggle).
 */
final class NotificationCategories extends Resource
{
    /**
     * `GET /v1/notification_categories` — your notification categories.
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/notification_categories');
    }

    /**
     * `POST /v1/notification_categories` — create a notification category (key, label).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/notification_categories', body: $body);
    }

    /**
     * `PUT /v1/notification_categories/:key` — change a category's label.
     *
     * @return array<string,mixed>
     */
    public function update(string $key, string $label): array
    {
        return $this->http->request(
            'PUT',
            '/v1/notification_categories/' . rawurlencode($key),
            body: ['label' => $label],
        );
    }

    /**
     * `DELETE /v1/notification_categories/:key` — delete a category (refused while a template uses it).
     *
     * @return array<string,mixed>
     */
    public function delete(string $key): array
    {
        return $this->http->request('DELETE', '/v1/notification_categories/' . rawurlencode($key));
    }
}
