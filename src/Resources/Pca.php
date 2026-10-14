<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/pca` — proof-carrying authority controls: attestors, kill switch, revocations, step-ups.
 */
final class Pca extends Resource
{
    /**
     * `GET /v1/pca/attestors` — the trusted software-attestor public keys.
     *
     * @return array<string,mixed>
     */
    public function getAttestors(): array
    {
        return $this->http->request('GET', '/v1/pca/attestors');
    }

    /**
     * `PUT /v1/pca/attestors` — replace the trusted software-attestor key list.
     *
     * @param list<mixed> $trustedAttestorKeys
     *
     * @return array<string,mixed>
     */
    public function setAttestors(array $trustedAttestorKeys): array
    {
        return $this->http->request(
            'PUT',
            '/v1/pca/attestors',
            body: ['trusted_attestor_keys' => $trustedAttestorKeys],
        );
    }

    /**
     * `POST /v1/pca/freeze` — engage the instance-wide kill switch: every proof-carrying action is denied
     * until cleared.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function engageKillSwitch(array $body = []): array
    {
        return $this->http->request('POST', '/v1/pca/freeze', body: $body);
    }

    /**
     * `DELETE /v1/pca/freeze` — clear the instance-wide kill switch.
     *
     * @return array<string,mixed>
     */
    public function clearKillSwitch(): array
    {
        return $this->http->request('DELETE', '/v1/pca/freeze');
    }

    /**
     * `POST /v1/pca/revocations` — revoke a capability id under a grant (grant_ref, cap_id).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function revoke(array $body): array
    {
        return $this->http->request('POST', '/v1/pca/revocations', body: $body);
    }

    /**
     * `POST /v1/pca/stepups/:id/deny` — deny a pending step-up.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function denyStepUp(string $id, array $body = []): array
    {
        return $this->http->request('POST', '/v1/pca/stepups/' . rawurlencode($id) . '/deny', body: $body);
    }
}
