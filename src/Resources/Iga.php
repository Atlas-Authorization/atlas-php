<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * Groups the iga sub-resources.
 */
final class Iga extends Resource
{
    public readonly IgaEntitlements $entitlements;
    public readonly IgaRequests $requests;
    public readonly IgaGrants $grants;
    public readonly IgaDelegations $delegations;
    public readonly IgaSod $sod;
    public readonly IgaLifecycle $lifecycle;
    public readonly IgaCampaigns $campaigns;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->entitlements = new IgaEntitlements($http);
        $this->requests = new IgaRequests($http);
        $this->grants = new IgaGrants($http);
        $this->delegations = new IgaDelegations($http);
        $this->sod = new IgaSod($http);
        $this->lifecycle = new IgaLifecycle($http);
        $this->campaigns = new IgaCampaigns($http);
    }
}
