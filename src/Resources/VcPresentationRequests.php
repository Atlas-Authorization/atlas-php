<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/vc/presentation_requests` - 2 operations.
 */
final class VcPresentationRequests extends Resource
{
    /**
     * `POST /v1/vc/presentation_requests` - Start a verifier request for a credential type and claims.
     * Show authorization_request_uri to the wallet, then read the request for the outcome.
     *
     * @param array{vct:string,format?:'dc+sd-jwt'|'mso_mdoc',iaca_roots?:list<string>,claims?:list<string>,trusted_issuers?:list<array<string,mixed>>,expires_in_seconds?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/vc/presentation_requests',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/vc/presentation_requests/:id` - Read a presentation request and its outcome.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/vc/presentation_requests/' . rawurlencode($id));
    }
}
