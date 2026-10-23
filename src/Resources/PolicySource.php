<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance/policy_source` — the external policy source (Atlas policy source protocol v1):
 * serve your policies from a CMS or your own service. Atlas fetches `GET {endpoint_url}/policies`
 * signed with the source's signing secret ({@see \Atlas\PolicySource\PolicySourceProtocol::verifyFeedRequest()}
 * checks it) and your source tells Atlas about changes with a signed event
 * ({@see \Atlas\PolicySource\PolicySourceProtocol::signEvent()}). `signing_secret` is write-only.
 */
final class PolicySource extends Resource
{
    /**
     * `GET /v1/instance/policy_source` — endpoint, sync mode, the `webhook_url` to give the
     * source, the last sync outcome.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/instance/policy_source');
    }

    /**
     * `PUT /v1/instance/policy_source` — configure the source; only the keys sent change, null
     * clears. Enabling needs `endpoint_url` and `signing_secret`. `sync` is 'webhook' or 'poll'
     * (`poll_interval_minutes` 5-1440).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body): array
    {
        return $this->http->request('PUT', '/v1/instance/policy_source', body: $body);
    }

    /**
     * `DELETE /v1/instance/policy_source` — disconnect the source (its policies and history stay).
     *
     * @return array<string,mixed>
     */
    public function delete(): array
    {
        return $this->http->request('DELETE', '/v1/instance/policy_source');
    }

    /**
     * `POST /v1/instance/policy_source/test` — a signed fetch of the feed, validated but not stored.
     *
     * @return array<string,mixed>
     */
    public function test(): array
    {
        return $this->http->request('POST', '/v1/instance/policy_source/test');
    }

    /**
     * `POST /v1/instance/policy_source/sync` — fetch the feed now and store it.
     *
     * @return array<string,mixed>
     */
    public function sync(): array
    {
        return $this->http->request('POST', '/v1/instance/policy_source/sync');
    }

    /**
     * `GET /v1/instance/policy_source/deliveries` — the delivery log, newest first.
     * `$params`: `limit`, `starting_after`, `kind`, `status`.
     *
     * @param array<string,mixed> $params
     *
     * @return array<string,mixed>
     */
    public function listDeliveries(array $params = []): array
    {
        return $this->http->request('GET', '/v1/instance/policy_source/deliveries', query: $params);
    }
}
