<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * Sign in with Atlas grants an instance manages: its apps, who granted what, and revoking a grant.
 */
final class AtlasIdGrants extends Resource
{
    /**
     * Sign in with Atlas apps this instance manages, each with `managed_via` (`instance_toggle` | `adopted`) and `button_rendered_origins`.
     *
     * @return array<string,mixed>
     */
    public function listApps(): array
    {
        return $this->http->request('GET', '/v1/atlas_id_apps');
    }

    /**
     * The setup check of an app this instance manages (`diagnostics:read` is enough): `ok`, `findings`, `button_rendered_origins`,
     * `authorize_requests_7d`, `fallback_origins`, `homepage_includes_script`.
     *
     * @return array<string,mixed>
     */
    public function preflight(string $id): array
    {
        return $this->http->request('GET', '/v1/atlas_id_apps/' . rawurlencode($id) . '/preflight');
    }

    /**
     * The grants of the user with this email on an app.
     *
     * @return array<string,mixed>
     */
    public function lookup(string $id, string $email): array
    {
        return $this->http->request('GET', '/v1/atlas_id_apps/' . rawurlencode($id) . '/users', query: ['email' => $email]);
    }

    /**
     * Revoke one grant (`$id` = app, `$id2` = grant).
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id, string $id2): array
    {
        return $this->http->request('DELETE', '/v1/atlas_id_apps/' . rawurlencode($id) . '/grants/' . rawurlencode($id2));
    }
}
