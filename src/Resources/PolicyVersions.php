<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/policies/:id/versions` — the versions of a policy. */
final class PolicyVersions extends Resource
{
    /**
     * `GET /v1/policies/:id/versions` — newest first, each with `acceptance_count`.
     *
     * @return array<string,mixed>
     */
    public function list(string $idOrKey): array
    {
        return $this->http->request('GET', '/v1/policies/' . rawurlencode($idOrKey) . '/versions');
    }

    /**
     * `POST /v1/policies/:id/versions` — a draft, or published at once with `publish` => true (and
     * `effective_at`). `content_markdown` is rendered and sanitised, `content_html` is sanitised.
     * 400 for an external policy (its versions come from the policy source).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(string $idOrKey, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/policies/' . rawurlencode($idOrKey) . '/versions', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/policies/:id/versions/:versionId` — one version, by id or label.
     *
     * @return array<string,mixed>
     */
    public function get(string $idOrKey, string $versionIdOrLabel): array
    {
        return $this->http->request('GET', $this->path($idOrKey, $versionIdOrLabel));
    }

    /**
     * `PATCH /v1/policies/:id/versions/:versionId` — edit a draft (409 for a published version).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(string $idOrKey, string $versionIdOrLabel, array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PATCH', $this->path($idOrKey, $versionIdOrLabel), body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `DELETE /v1/policies/:id/versions/:versionId` — delete a draft (409 for a published version: retire it).
     *
     * @return array<string,mixed>
     */
    public function delete(string $idOrKey, string $versionIdOrLabel): array
    {
        return $this->http->request('DELETE', $this->path($idOrKey, $versionIdOrLabel));
    }

    /**
     * `POST /v1/policies/:id/versions/:versionId/publish` — publish a draft. `effective_at`
     * (epoch ms or ISO, default now) is when sign-ups must accept it and sign-ins re-ask per the
     * policy's `reaccept` rule.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function publish(string $idOrKey, string $versionIdOrLabel, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', $this->path($idOrKey, $versionIdOrLabel) . '/publish', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `POST /v1/policies/:id/versions/:versionId/retire` — retire a published version; its acceptances stay.
     *
     * @return array<string,mixed>
     */
    public function retire(string $idOrKey, string $versionIdOrLabel, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', $this->path($idOrKey, $versionIdOrLabel) . '/retire', idempotencyKey: $idempotencyKey);
    }

    private function path(string $idOrKey, string $versionIdOrLabel): string
    {
        return '/v1/policies/' . rawurlencode($idOrKey) . '/versions/' . rawurlencode($versionIdOrLabel);
    }
}
