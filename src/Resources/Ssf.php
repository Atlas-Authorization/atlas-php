<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * Groups the ssf sub-resources.
 */
final class Ssf extends Resource
{
    public readonly SsfStreams $streams;
    public readonly SsfReceivers $receivers;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->streams = new SsfStreams($http);
        $this->receivers = new SsfReceivers($http);
    }
}
