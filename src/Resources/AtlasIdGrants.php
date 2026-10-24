<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * Sign in with Atlas grants an instance manages: its apps, who granted what, and revoking a grant.
 */
final class AtlasIdGrants extends Resource
{
    /**
     * Sign in with Atlas apps this instance manages.
     *
     * @return array<string,mixed>
     */
    public function listApps(): array
    {
        return $this->http->request('GET', '/v1/atlas_id_apps');
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
