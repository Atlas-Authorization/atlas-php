<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * The community Actions gallery: browse reusable Action templates (first-party and
 * community), install one as a DISABLED action, and keep installed actions up to date.
 */
final class ActionGallery extends Resource
{
    /**
     * The gallery, best first.
     *
     * @param array{q?:string,category?:string,trigger?:string,source?:'builtin'|'community'} $filter
     *
     * @return array<string,mixed> a ListPage
     */
    public function list(array $filter = []): array
    {
        return $this->http->request('GET', '/v1/action_gallery', query: $filter);
    }

    /**
     * One template with its code, secret names and versions.
     *
     * @return array<string,mixed>
     */
    public function get(string $slug): array
    {
        return $this->http->request('GET', '/v1/action_gallery/' . rawurlencode($slug));
    }

    /**
     * Install as a new, disabled action (`version`, `name`, `secrets`). Supply secrets,
     * bind it, then enable it.
     *
     * @param array{version?:string,name?:string,secrets?:array<string,string>} $body
     *
     * @return array<string,mixed> `action`, `template`, `install_id`, `missing_secrets`
     */
    public function install(string $slug, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/action_gallery/' . rawurlencode($slug) . '/install', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> the actions installed from the gallery, with update status */
    public function installs(): array
    {
        return $this->http->request('GET', '/v1/action_gallery_installs');
    }

    /**
     * Move an installed action to its template's latest version.
     *
     * @param array<string,string>|null $secrets values for secrets the new version requires
     *
     * @return array<string,mixed>
     */
    public function update(string $actionId, ?array $secrets = null): array
    {
        return $this->http->request('POST', '/v1/actions/' . rawurlencode($actionId) . '/gallery_update', body: $secrets === null ? [] : ['secrets' => $secrets]);
    }
}
