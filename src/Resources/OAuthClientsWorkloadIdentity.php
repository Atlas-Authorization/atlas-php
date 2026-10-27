<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/workload_identity` - 2 operations.
 */
final class OAuthClientsWorkloadIdentity extends Resource
{
    /**
     * `GET /v1/workload_identity/presets` - List the workload platforms Atlas knows defaults for (SPIFFE,
     * Kubernetes, GitHub Actions, GCP, Azure): issuer, JWKS URL and the shape of the token subject.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function presets(array $params = []): array
    {
        return $this->http->request('GET', '/v1/workload_identity/presets', query: $params);
    }

    /**
     * `GET /v1/workload_identity/cloud_trust` - Trust-policy snippets that make AWS
     * (AssumeRoleWithWebIdentity), GCP (workload identity federation) or Azure (federated credentials)
     * accept an access token issued by this instance. Nothing is changed in the cloud.
     *
     * @param array{provider?:'aws'|'gcp'|'azure',audience?:string,subject?:string,tenant_id?:string} $params
     *
     * @return array<string,mixed>
     */
    public function cloudTrust(array $params = []): array
    {
        return $this->http->request('GET', '/v1/workload_identity/cloud_trust', query: $params);
    }
}
