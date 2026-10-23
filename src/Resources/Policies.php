<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/policies` — policy acceptance: the policies (Terms of Service, privacy policy, any key)
 * your users accept at sign-up and, per policy, again at sign-in when a new version takes
 * effect. A policy's versions go draft, published (with an `effective_at`), retired.
 * Acceptances are append-only evidence (version, content hash, how it was shown). Wherever
 * `$idOrKey` is taken, the policy id or its key works.
 *
 *     $atlas->policies->create(['key' => 'tos', 'title' => 'Terms of Service']);
 *     $atlas->policies->versions->create('tos', ['version' => '2026-10-24', 'content_markdown' => '# Terms']);
 *     $atlas->policies->versions->publish('tos', '2026-10-24', ['effective_at' => '2026-11-01T00:00:00Z']);
 */
final class Policies extends Resource
{
    public readonly PolicyVersions $versions;

    public readonly PolicySettings $settings;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->versions = new PolicyVersions($http);
        $this->settings = new PolicySettings($http);
    }

    /**
     * `GET /v1/policies` — active policies (`include_retired` => true for all), each with its
     * current, upcoming and latest version.
     *
     * @param array<string,mixed> $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/policies', query: $params);
    }

    /**
     * `POST /v1/policies` — create a policy (`key`, `title`; optional `display`
     * checkbox|scroll|link, `required`, `applies_to` sign_up|sign_in_and_sign_up, `reaccept`
     * none|next_sign_in|grace with `grace_days`, `position`). It asks nobody anything until a
     * version is published.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/policies', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/policies/:id` — one policy, by id or key.
     *
     * @return array<string,mixed>
     */
    public function get(string $idOrKey): array
    {
        return $this->http->request('GET', '/v1/policies/' . rawurlencode($idOrKey));
    }

    /**
     * `PATCH /v1/policies/:id` — update a policy (the key cannot change). `status` => 'retired'
     * stops it gating (history is kept); 'active' brings it back.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $idOrKey, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PATCH', '/v1/policies/' . rawurlencode($idOrKey), body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `DELETE /v1/policies/:id` — delete a never-published policy; one with a published version
     * is retired instead (check `deleted` / `retired`).
     *
     * @return array<string,mixed>
     */
    public function delete(string $idOrKey): array
    {
        return $this->http->request('DELETE', '/v1/policies/' . rawurlencode($idOrKey));
    }

    /**
     * `GET /v1/policies/:id/acceptances` — who accepted the policy, newest first, with the
     * evidence. `$params`: `version` (id or label), `status` (accepted|withdrawn|all), `limit`,
     * `starting_after`.
     *
     * @param array<string,mixed> $params
     *
     * @return array<string,mixed>
     */
    public function listAcceptances(string $idOrKey, array $params = []): array
    {
        return $this->http->request('GET', '/v1/policies/' . rawurlencode($idOrKey) . '/acceptances', query: $params);
    }

    /**
     * The same as CSV text (`format=csv`, up to 100,000 rows); `limit` and `starting_after` are ignored.
     *
     * @param array<string,mixed> $params
     */
    public function exportAcceptancesCsv(string $idOrKey, array $params = []): string
    {
        unset($params['limit'], $params['starting_after']);
        $params['format'] = 'csv';

        return $this->http->request('GET', '/v1/policies/' . rawurlencode($idOrKey) . '/acceptances', query: $params, raw: true);
    }

    /**
     * `GET /v1/users/:id/policies` — a user's standing on every policy (accepted and current
     * version, `needs_reaccept`, `blocking`, `grace_ends_at`) and `needs_acceptance`.
     *
     * @return array<string,mixed>
     */
    public function forUser(string $userId): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($userId) . '/policies');
    }
}
