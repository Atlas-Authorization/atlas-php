<?php

declare(strict_types=1);

namespace Atlas\Verify;

/**
 * Step-up checks on a verified Atlas token's claims. Session and template tokens carry `auth_time` (the first-factor sign-in or the last
 * step-up), `amr` and `acr` (`urn:atlas:acr:basic`, `urn:atlas:acr:mfa`, `urn:atlas:acr:step_up`).
 *
 *     if (!RecentAuth::authenticatedWithin($claims, 300)) { return reauthenticationRequired(maxAge: 300); }
 */
final class RecentAuth
{
    /**
     * Whether `auth_time` is within `$maxAgeSeconds` of now. A token without `auth_time` never is.
     *
     * @param array<string,mixed> $claims
     */
    public static function authenticatedWithin(array $claims, int $maxAgeSeconds, ?int $now = null): bool
    {
        $at = $claims['auth_time'] ?? null;

        return (is_int($at) || is_float($at)) && ($now ?? time()) - $at <= $maxAgeSeconds;
    }

    /**
     * Whether the session proved a second factor (`acr` is mfa, or `amr` includes `mfa`).
     *
     * @param array<string,mixed> $claims
     */
    public static function usedMultiFactor(array $claims): bool
    {
        return ($claims['acr'] ?? null) === 'urn:atlas:acr:mfa' || (is_array($claims['amr'] ?? null) && in_array('mfa', $claims['amr'], true));
    }

    /**
     * Whether the session satisfies a "requires MFA" rule: the server's own `mfa_satisfied` verdict when the token carries it (final),
     * else `mfa === true`, {@see usedMultiFactor()}, or an enterprise SSO sign-in (`sso_connection_id`: the identity provider owns the factor).
     *
     * @param array<string,mixed> $claims
     */
    public static function mfaSatisfied(array $claims): bool
    {
        if (is_bool($claims['mfa_satisfied'] ?? null)) {
            return $claims['mfa_satisfied'];
        }

        return ($claims['mfa'] ?? null) === true || self::usedMultiFactor($claims) || is_string($claims['sso_connection_id'] ?? null);
    }

    /**
     * Whether the session meets the active organization's MFA policy: true when the organization does not require MFA, or the token says
     * `org_mfa_ok`. The claims describe the ACTIVE organization only: pass the organization you authorize for (its Atlas id or external id)
     * and a token whose active organization is another one answers false.
     *
     * @param array<string,mixed> $claims
     */
    public static function mfaOrgSatisfied(array $claims, ?string $orgIdOrExternalId = null): bool
    {
        $required = ($claims['org_mfa_required'] ?? null) === true;
        if ($orgIdOrExternalId === null) {
            return !$required || ($claims['org_mfa_ok'] ?? null) === true;
        }
        $matches = ($claims['org_id'] ?? null) === $orgIdOrExternalId || ($claims['org_external_id'] ?? null) === $orgIdOrExternalId;
        if ($required) {
            // Withheld claims carry no org_id: the MFA fields are then all the token says.
            return ($claims['org_mfa_ok'] ?? null) === true && ($matches || !array_key_exists('org_id', $claims));
        }

        return $matches;
    }
}
