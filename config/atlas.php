<?php

declare(strict_types=1);

/**
 * Atlas configuration, published to the host application's `config/atlas.php`.
 *
 * Everything comes from the environment so secrets never live in the repo. Only
 * `jwks_url` and `issuer` are needed for local session verification; `secret_key`
 * and `bapi_base_url` are needed only for the Backend API client and the
 * {@see \Atlas\Verify\SessionVerifier::verifyOnline()} slow path.
 */
return [
    // The instance's JWKS endpoint — session tokens are verified against these keys.
    'jwks_url' => env('ATLAS_JWKS_URL'),

    // Expected `iss`. Required: an unchecked issuer accepts any Atlas instance.
    'issuer' => env('ATLAS_ISSUER'),

    // Optional azp allowlist (comma-separated). When set, a token minted for a
    // different origin is refused.
    'authorized_parties' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ATLAS_AUTHORIZED_PARTIES', '')),
    ), static fn (string $v): bool => $v !== '')),

    // Secret key (sk_live_… / sk_test_…), for the Backend API client + verifyOnline.
    'secret_key' => env('ATLAS_SECRET_KEY'),

    // Backend API base URL, for verifyOnline only.
    'bapi_base_url' => env('ATLAS_BAPI_URL', 'https://api.atlasauth.net'),
];
