<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/scim_provisioning_presets` - 1 operation.
 */
final class ScimProvisioningPresets extends Resource
{
    /**
     * `GET /v1/scim_provisioning_presets` - List the pre-built outbound provisioning targets (Slack,
     * GitHub, Zoom, Salesforce, Atlassian) with their base-URL template and required parameters.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/scim_provisioning_presets', query: $params);
    }
}
