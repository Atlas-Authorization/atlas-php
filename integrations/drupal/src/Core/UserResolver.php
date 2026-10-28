<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Core;

/**
 * The decision "which Drupal user does this verified Atlas identity become?",
 * free of any Drupal coupling: it works over an injected {@see UserStore}, so it
 * is unit-tested without Drupal.
 */
final class UserResolver
{
    /**
     * Map a verified identity onto a user id, linking or creating as needed.
     *
     * Order: (1) an account already linked by Atlas `sub`; (2) an account with a
     * matching VERIFIED email, which is then linked; (3) provision a new user when
     * auto-provisioning is on. The email match requires `email_verified`, so nobody
     * can take over an existing account by asserting its address at an identity
     * provider that never checked it.
     *
     * @throws LoginException when no user can be resolved, or the resolved user
     *                        holds no role on the allowlist
     */
    public static function resolveUser(Identity $identity, Options $options, UserStore $store): int
    {
        $existing = $store->findByAtlasSub($identity->subject);
        if ($existing !== null) {
            return self::gateRoles($existing, $options, $store);
        }

        if ($identity->email !== null && $identity->email !== '' && $identity->emailVerified) {
            $byEmail = $store->findByEmail($identity->email);
            if ($byEmail !== null) {
                // Gate first: a refused account is not linked.
                self::gateRoles($byEmail, $options, $store);
                $store->linkAtlasSub($byEmail, $identity->subject);

                return $byEmail;
            }
        }

        if (!$options->auto_provision) {
            throw new LoginException('no_matching_user');
        }
        if ($identity->email === null || $identity->email === '') {
            throw new LoginException('cannot_provision_without_email');
        }
        if ($options->default_role !== '' && !$options->roleIsAllowed($options->default_role)) {
            throw new LoginException('provision_role_not_allowed');
        }

        return $store->createUser($identity, $options->default_role);
    }

    /**
     * @throws LoginException when the user holds no permitted role
     */
    private static function gateRoles(int $userId, Options $options, UserStore $store): int
    {
        $roles = $store->rolesOf($userId);
        if ($options->allowed_roles !== [] && array_intersect($roles, $options->allowed_roles) === []) {
            throw new LoginException('role_not_allowed');
        }

        return $userId;
    }
}
