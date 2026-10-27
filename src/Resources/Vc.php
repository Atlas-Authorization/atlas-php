<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/vc/offers` - 1 operation.
 */
final class Vc extends Resource
{
    public readonly VcCredentialTypes $credentialTypes;
    public readonly VcCredentials $credentials;
    public readonly VcPresentationRequests $presentationRequests;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->credentialTypes = new VcCredentialTypes($http);
        $this->credentials = new VcCredentials($http);
        $this->presentationRequests = new VcPresentationRequests($http);
    }

    /**
     * `POST /v1/vc/offers` - Create a wallet offer for a user (pre-authorized code flow). The response
     * carries the code once, as an openid-credential-offer:// link.
     *
     * @param array{credential_type_id:string,user_id:string,tx_code?:string,expires_in_seconds?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function createOffer(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/vc/offers',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
