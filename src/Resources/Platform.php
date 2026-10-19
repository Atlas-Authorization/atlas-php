<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * Platform-key (`psk_…`) surface: apps you provision (`apps`), Sign in with Atlas apps
 * (`atlasIdApps`) and their design images (`designAssets`).
 *
 * Construct the client with your platform key to use it:
 *
 *     $platform = (new \Atlas\Client('psk_…'))->platform;
 */
final class Platform extends Resource
{
    public readonly PlatformApps $apps;
    public readonly PlatformAtlasIdApps $atlasIdApps;
    public readonly PlatformDesignAssets $designAssets;
    public readonly PlatformAtlasIdPublisher $atlasIdPublisher;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->apps = new PlatformApps($http);
        $this->atlasIdApps = new PlatformAtlasIdApps($http);
        $this->designAssets = new PlatformDesignAssets($http);
        $this->atlasIdPublisher = new PlatformAtlasIdPublisher($http);
    }

    /**
     * `GET /v1/platform/config_changelog` — config change history for the account's Sign in with
     * Atlas apps (`action`, `limit`, `starting_after`, `since`).
     *
     * @param array{action?:string,limit?:int,starting_after?:string,since?:string} $params
     *
     * @return array{object:string,data:list<array<string,mixed>>,has_more?:bool,next_cursor?:string|null}
     */
    public function configChangelog(array $params = []): array
    {
        return $this->http->request('GET', '/v1/platform/config_changelog', query: $params);
    }
}
