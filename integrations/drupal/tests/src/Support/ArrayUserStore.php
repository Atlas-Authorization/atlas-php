<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Tests\Support;

use Drupal\atlas_auth\Core\Identity;
use Drupal\atlas_auth\Core\UserStore;

/**
 * An in-memory {@see UserStore} for testing {@see \Drupal\atlas_auth\Core\UserResolver::resolveUser()}
 * without Drupal: it records what the mapping logic asked for, so a test can assert the exact decision
 * (linked by sub, matched by email and linked, or provisioned).
 */
final class ArrayUserStore implements UserStore
{
    /** @var array<string,int> atlas sub => user id */
    public array $bySub = [];

    /** @var array<string,int> email => user id */
    public array $byEmail = [];

    /** @var array<int,list<string>> user id => role ids */
    public array $roles = [];

    /** @var list<array{email: string, sub: string, role: string}> provisioning log */
    public array $created = [];

    public int $nextId = 100;

    public ?int $signedIn = null;

    public function findByAtlasSub(string $sub): ?int
    {
        return $this->bySub[$sub] ?? null;
    }

    public function findByEmail(string $email): ?int
    {
        return $this->byEmail[$email] ?? null;
    }

    public function linkAtlasSub(int $userId, string $sub): void
    {
        $this->bySub[$sub] = $userId;
    }

    public function createUser(Identity $identity, string $role): int
    {
        $id = $this->nextId++;
        $this->created[] = ['email' => (string) $identity->email, 'sub' => $identity->subject, 'role' => $role];
        $this->bySub[$identity->subject] = $id;
        if ($identity->email !== null) {
            $this->byEmail[$identity->email] = $id;
        }
        $this->roles[$id] = $role === '' ? [] : [$role];

        return $id;
    }

    public function rolesOf(int $userId): array
    {
        return $this->roles[$userId] ?? [];
    }

    public function signIn(int $userId): void
    {
        $this->signedIn = $userId;
    }
}
