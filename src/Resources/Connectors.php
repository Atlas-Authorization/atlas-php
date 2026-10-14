<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/connectors` — community connector profiles you publish to the directory.
 */
final class Connectors extends Resource
{
    /**
     * `GET /v1/connectors` — your own community connector profiles (draft and published).
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/connectors');
    }

    /**
     * `POST /v1/connectors` — create a community connector profile.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/connectors', body: $body);
    }

    /**
     * `GET /v1/connectors/:id` — one of your connector profiles.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/connectors/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/connectors/:id` — edit a connector profile.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/connectors/' . rawurlencode($id), body: $body);
    }

    /**
     * `DELETE /v1/connectors/:id` — delete a connector profile with its installs and reviews.
     *
     * @return array<string,mixed>
     */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/connectors/' . rawurlencode($id));
    }

    /**
     * `POST /v1/connectors/:id/publish` — publish a connector to the public directory.
     *
     * @return array<string,mixed>
     */
    public function publish(string $id): array
    {
        return $this->http->request('POST', '/v1/connectors/' . rawurlencode($id) . '/publish');
    }

    /**
     * `POST /v1/connectors/:id/unlist` — remove a published connector from the directory without deleting
     * it.
     *
     * @return array<string,mixed>
     */
    public function unlist(string $id): array
    {
        return $this->http->request('POST', '/v1/connectors/' . rawurlencode($id) . '/unlist');
    }

    /**
     * `POST /v1/connectors/:id/shared_credentials` — set, rotate or revoke (revoke: true) the shared OAuth
     * credentials.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function setSharedCredentials(string $id, array $body): array
    {
        return $this->http->request('POST', '/v1/connectors/' . rawurlencode($id) . '/shared_credentials', body: $body);
    }

    /**
     * `POST /v1/connectors/:slug/install` — enable a published connector on your instance.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function install(string $slug, array $body = []): array
    {
        return $this->http->request('POST', '/v1/connectors/' . rawurlencode($slug) . '/install', body: $body);
    }

    /**
     * `POST /v1/connectors/:slug/reviews` — rate a connector you installed (rating 1-5, optional body).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function review(string $slug, array $body): array
    {
        return $this->http->request('POST', '/v1/connectors/' . rawurlencode($slug) . '/reviews', body: $body);
    }
}
