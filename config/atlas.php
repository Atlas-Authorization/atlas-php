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

    // Better Auth session bridge (migration from Better Auth). Used by the `atlas.better_auth_bridge`
    // route middleware; needs `secret_key`. `sign_in_url` is where a user who still owes a second
    // factor is sent (with __atlas_attempt / __atlas_status); null continues unauthenticated.
    'better_auth_bridge' => [
        'sign_in_url' => env('ATLAS_BETTER_AUTH_SIGN_IN_URL'),
        'cookie_prefix' => env('ATLAS_BETTER_AUTH_COOKIE_PREFIX', 'better-auth'),
        'cookie_name' => env('ATLAS_BETTER_AUTH_COOKIE_NAME'),
        // The `domain` the Better Auth cookies were set on (crossSubDomainCookies.domain), so they clear.
        'cookie_domain' => env('ATLAS_BETTER_AUTH_COOKIE_DOMAIN'),
        // false only for plain-http local development.
        'secure' => env('ATLAS_BETTER_AUTH_SECURE', true),
    ],
];
