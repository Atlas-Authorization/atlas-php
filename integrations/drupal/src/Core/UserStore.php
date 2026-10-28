<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Core;

/**
 * The seam between the Drupal-free mapping logic in {@see UserResolver} and
 * Drupal's user API. The production implementation is
 * {@see \Drupal\atlas_auth\Drupal\DrupalUserStore}; tests inject an in-memory fake.
 */
interface UserStore
{
    /** Find a user id by the linked Atlas subject, or null if none is linked. */
    public function findByAtlasSub(string $sub): ?int;

    /** Find a user id by email address, or null if none exists. */
    public function findByEmail(string $email): ?int;

    /** Link an existing user to the Atlas subject. */
    public function linkAtlasSub(int $userId, string $sub): void;

    /**
     * Create a user from a verified identity, linked to its subject, and return the id.
     *
     * @param string $role the role to grant, or '' for none beyond "authenticated"
     */
    public function createUser(Identity $identity, string $role): int;

    /**
     * The role ids of an existing user.
     *
     * @return list<string>
     */
    public function rolesOf(int $userId): array;

    /** Log this user in. */
    public function signIn(int $userId): void;
}
