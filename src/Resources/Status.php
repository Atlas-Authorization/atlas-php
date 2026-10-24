<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * Atlas's status for your instance: the public service status, your status subscription (feed), open incidents, and upstream status dependencies.
 */
final class Status extends Resource
{
    /**
     * Service status (`regions`, `groups`, component `group`/`regions`, `incidents`).
     *
     * @return array<string,mixed>
     */
    public function service(): array
    {
        return $this->http->request('GET', '/v1/status');
    }

    /**
     * The status subscription.
     *
     * @return array<string,mixed>
     */
    public function getSubscription(): array
    {
        return $this->http->request('GET', '/v1/instance/status_subscription');
    }

    /**
     * Set the subscription (channels, components).
     *
     * @return array<string,mixed>
     */
    public function updateSubscription(array $body): array
    {
        return $this->http->request('PUT', '/v1/instance/status_subscription', body: $body);
    }

    /**
     * Turn the subscription off.
     *
     * @return array<string,mixed>
     */
    public function disableSubscription(): array
    {
        return $this->http->request('DELETE', '/v1/instance/status_subscription');
    }

    /**
     * Rotate the private feed URL.
     *
     * @return array<string,mixed>
     */
    public function rotateFeed(): array
    {
        return $this->http->request('POST', '/v1/instance/status_subscription/rotate_feed');
    }

    /**
     * Incidents; `['open' => true, 'limit' => 20]`.
     *
     * @return array<string,mixed>
     */
    public function listIncidents(array $query = []): array
    {
        return $this->http->request('GET', '/v1/instance/status_incidents', query: $query);
    }

    /**
     * Upstream status dependencies (adds `state`).
     *
     * @return array<string,mixed>
     */
    public function listDependencies(): array
    {
        return $this->http->request('GET', '/v1/instance/status_dependencies');
    }

    /**
     * Watch an upstream status page.
     *
     * @return array<string,mixed>
     */
    public function createDependency(array $body): array
    {
        return $this->http->request('POST', '/v1/instance/status_dependencies', body: $body);
    }

    /**
     * One dependency.
     *
     * @return array<string,mixed>
     */
    public function getDependency(string $id): array
    {
        return $this->http->request('GET', '/v1/instance/status_dependencies/' . rawurlencode($id));
    }

    /**
     * Update a dependency.
     *
     * @return array<string,mixed>
     */
    public function updateDependency(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/instance/status_dependencies/' . rawurlencode($id), body: $body);
    }

    /**
     * Delete a dependency.
     *
     * @return array<string,mixed>
     */
    public function deleteDependency(string $id): array
    {
        return $this->http->request('DELETE', '/v1/instance/status_dependencies/' . rawurlencode($id));
    }

    /**
     * Re-check a dependency now.
     *
     * @return array<string,mixed>
     */
    public function refreshDependency(string $id): array
    {
        return $this->http->request('POST', '/v1/instance/status_dependencies/' . rawurlencode($id) . '/refresh');
    }
}
