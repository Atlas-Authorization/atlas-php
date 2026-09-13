<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/scim_provisioning_targets` — OUTBOUND SCIM provisioning to downstream apps. */
final class ScimProvisioning extends Resource
{
    /** @return array<string,mixed> a ListPage of targets */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/scim_provisioning_targets');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/scim_provisioning_targets/' . rawurlencode($id));
    }

    /**
     * @param array{name:string,base_url:string,bearer_token:string,attribute_mapping?:array<string,mixed>,deprovision_action?:string,enabled?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/scim_provisioning_targets', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body a write-only bearer omitted leaves the stored one
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/scim_provisioning_targets/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/scim_provisioning_targets/' . rawurlencode($id));
    }

    /** Probe connectivity + auth to the downstream with the real bearer. @return array<string,mixed> */
    public function test(string $id): array
    {
        return $this->http->request('POST', '/v1/scim_provisioning_targets/' . rawurlencode($id) . '/test');
    }

    /** Force one user's sync now. @return array<string,mixed> */
    public function syncUser(string $id, string $userId): array
    {
        return $this->http->request(
            'POST',
            '/v1/scim_provisioning_targets/' . rawurlencode($id) . '/sync_user',
            body: ['user_id' => $userId],
        );
    }

    /** Force one org's (group) sync now. @return array<string,mixed> */
    public function syncGroup(string $id, string $organizationId): array
    {
        return $this->http->request(
            'POST',
            '/v1/scim_provisioning_targets/' . rawurlencode($id) . '/sync_group',
            body: ['organization_id' => $organizationId],
        );
    }
}
