<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/tokens` — §7.7 authoritative token verification. Backend SDKs verify
 * session JWTs locally against JWKS (see {@see \Atlas\Verify\SessionVerifier}) —
 * fast, offline, and correct within the 60-second token lifetime, but blind to a
 * revocation. This endpoint is for the requests where "signed out a moment ago"
 * has to mean now: it checks the revocation set and, when the cache cannot
 * answer, falls through to the database.
 *
 * One failure is reported as `verified: false` with a coarse `reason`; telling a
 * caller which check failed would help a forger more than a developer.
 */
final class Tokens extends Resource
{
    /**
     * `POST /v1/tokens/verify`.
     *
     * @param array{token:string,authorized_parties?:list<string>} $body
     *
     * @return array<string,mixed> a token_verification object
     */
    public function verify(array $body): array
    {
        return $this->http->request('POST', '/v1/tokens/verify', body: $body);
    }
}
