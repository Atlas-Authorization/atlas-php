<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/platform/design/assets` — design images for Sign in with Atlas app designs.
 */
final class PlatformDesignAssets extends Resource
{
    /**
     * `GET /v1/platform/design/assets` — design images owned by your platform account, newest first.
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/platform/design/assets');
    }

    /**
     * `DELETE /v1/platform/design/assets/:id` — delete a platform design image. Refused with 409 while a
     * saved design uses it.
     *
     * @return array<string,mixed>
     */
    public function delete(string $assetId): array
    {
        return $this->http->request('DELETE', '/v1/platform/design/assets/' . rawurlencode($assetId));
    }

    /**
     * `POST /v1/platform/design/assets` — upload a design image owned by your
     * platform account: raw PNG, JPEG, GIF or WebP bytes, up to 8 MB. The content
     * type is detected from the bytes unless given.
     *
     * @param 'background'|'logo'|null $kind
     *
     * @return array<string,mixed> a design asset (`asset_id`, `kind`, `url`, `width`, `height`, …)
     */
    public function upload(string $bytes, ?string $kind = null, ?string $contentType = null): array
    {
        return $this->uploadImage('/v1/platform/design/assets', $bytes, $kind, $contentType);
    }
}
