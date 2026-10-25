<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/partner_links` — credentials for a partner product that calls your instance (inbound), and the ones Atlas uses to call a partner (`outbound*`).
 */
final class PartnerLinks extends Resource
{
    /**
     * Create an inbound link by hand: `{partner, name?, scopes: [..], expires_at?, cert_fingerprints?}`; the `client_secret` is shown once. Prefer approving the partner's request ({@see approveRequest()}): there is no secret to hand over.
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/partner_links', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * List inbound links.
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/partner_links');
    }

    /**
     * One link.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/partner_links/' . rawurlencode($id));
    }

    /**
     * Rotate the link's secret (shown once).
     *
     * @return array<string,mixed>
     */
    public function rotateSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/partner_links/' . rawurlencode($id) . '/rotate_secret');
    }

    /**
     * Revoke a link.
     *
     * @return array<string,mixed>
     */
    public function revoke(string $id): array
    {
        return $this->http->request('DELETE', '/v1/partner_links/' . rawurlencode($id));
    }

    /**
     * List the outbound links (`source`: `manual`, `oauth_install` or `registration`, a partner platform's stored partner token).
     *
     * @return array<string,mixed>
     */
    public function listOutbound(): array
    {
        return $this->http->request('GET', '/v1/partner_links/outbound');
    }

    /**
     * Save the outbound link for partner `$id`.
     *
     * @return array<string,mixed>
     */
    public function saveOutbound(string $id, array $body): array
    {
        return $this->http->request('PUT', '/v1/partner_links/outbound/' . rawurlencode($id), body: $body);
    }

    /**
     * Test the outbound link for partner `$id` (`ok`, `error`).
     *
     * @return array<string,mixed>
     */
    public function testOutbound(string $id): array
    {
        return $this->http->request('POST', '/v1/partner_links/outbound/' . rawurlencode($id) . '/test');
    }

    /**
     * Remove the outbound link for partner `$id`.
     *
     * @return array<string,mixed>
     */
    public function removeOutbound(string $id): array
    {
        return $this->http->request('DELETE', '/v1/partner_links/outbound/' . rawurlencode($id));
    }

    /**
     * Lock a link to client certificates (mTLS): the SHA-256 fingerprints (base64url) every use must present. An empty list removes the lock.
     *
     * @param list<string> $certFingerprints
     *
     * @return array<string,mixed>
     */
    public function setCertificates(string $id, array $certFingerprints): array
    {
        return $this->http->request('PATCH', '/v1/partner_links/' . rawurlencode($id), body: ['cert_fingerprints' => array_values($certFingerprints)]);
    }

    /**
     * List partner link requests: a partner asking this instance (`inbound`) or Atlas asking a partner (`outbound`). Filters: `direction`, `status` (a string or a list).
     *
     * @param array{direction?:string,status?:string|list<string>} $params
     *
     * @return array<string,mixed>
     */
    public function listRequests(array $params = []): array
    {
        if (isset($params['status']) && is_array($params['status'])) {
            $params['status'] = implode(',', $params['status']);
        }

        return $this->http->request('GET', '/v1/partner_links/requests', query: $params === [] ? null : $params);
    }

    /**
     * One partner link request.
     *
     * @return array<string,mixed>
     */
    public function getRequest(string $id): array
    {
        return $this->http->request('GET', '/v1/partner_links/requests/' . rawurlencode($id));
    }

    /**
     * Approve a request: Atlas creates the link and delivers its credentials to the partner's callback, signed. You never see the secret. Body: `{scopes?, expires_at?}`; `scopes` may narrow the request, never widen it.
     *
     * @return array<string,mixed> the request plus `delivered`, `link` and `note`
     */
    public function approveRequest(string $id, array $body = []): array
    {
        return $this->http->request('POST', '/v1/partner_links/requests/' . rawurlencode($id) . '/approve', body: $body);
    }

    /**
     * Deny a request (the denial is delivered to the partner too).
     *
     * @return array<string,mixed>
     */
    public function denyRequest(string $id): array
    {
        return $this->http->request('POST', '/v1/partner_links/requests/' . rawurlencode($id) . '/deny');
    }

    /**
     * Ask a partner for a link; its credentials arrive by themselves once the partner approves. Body: `{scopes, resource?, base_url?, token_url?, name?}`.
     *
     * @return array<string,mixed>
     */
    public function requestOutbound(string $partner, array $body): array
    {
        return $this->http->request('POST', '/v1/partner_links/outbound/' . rawurlencode($partner) . '/request', body: $body);
    }
}
