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

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->apps = new PlatformApps($http);
        $this->atlasIdApps = new PlatformAtlasIdApps($http);
        $this->designAssets = new PlatformDesignAssets($http);
    }
}
