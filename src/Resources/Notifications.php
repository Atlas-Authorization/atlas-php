<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/notifications` — templated notification emails, with their `categories` and `templates`.
 */
final class Notifications extends Resource
{
    public readonly NotificationCategories $categories;
    public readonly NotificationTemplates $templates;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->categories = new NotificationCategories($http);
        $this->templates = new NotificationTemplates($http);
    }

    /**
     * `POST /v1/notifications` — send a templated notification email to a user, honouring their
     * preferences. Returns sent or suppressed.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/notifications', body: $body);
    }
}
