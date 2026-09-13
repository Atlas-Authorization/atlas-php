<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/lti_platforms` — LTI 1.3 platform (LMS) registrations for a core launch.
 *
 * Every field is a PUBLIC trust anchor — a launch is verified against the
 * platform's own published JWKS, not a shared secret — so a registration is
 * fully readable and editable with no reveal-once.
 */
final class LtiPlatforms extends Resource
{
    /** @return array<string,mixed> a ListPage of lti_platform objects */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/lti_platforms');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/lti_platforms/' . rawurlencode($id));
    }

    /**
     * @param array{issuer:string,client_id:string,auth_login_url:string,jwks_uri:string,deployment_ids:list<string>,organization_id?:string} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/lti_platforms', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array{auth_login_url?:string,jwks_uri?:string,deployment_ids?:list<string>,organization_id?:string|null} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/lti_platforms/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> a DeletedObject */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/lti_platforms/' . rawurlencode($id));
    }
}
