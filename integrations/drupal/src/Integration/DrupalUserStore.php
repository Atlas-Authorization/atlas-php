<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Integration;

use Drupal\atlas_auth\Core\Identity;
use Drupal\atlas_auth\Core\LoginException;
use Drupal\atlas_auth\Core\UserStore;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\user\UserInterface;

/**
 * The Drupal side of {@see UserStore}: accounts are linked to the Atlas subject
 * through the External Authentication module's authmap, so a user keeps their
 * account when their email changes.
 */
final class DrupalUserStore implements UserStore {

  public const PROVIDER = 'atlas_auth';

  public function __construct(
    private readonly ExternalAuthInterface $externalAuth,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  public function findByAtlasSub(string $sub): ?int {
    $account = $this->externalAuth->load($sub, self::PROVIDER);
    return $account ? (int) $account->id() : NULL;
  }

  public function findByEmail(string $email): ?int {
    $matches = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $email]);
    $account = reset($matches);
    return $account ? (int) $account->id() : NULL;
  }

  public function linkAtlasSub(int $userId, string $sub): void {
    $account = $this->load($userId);
    if (!$this->externalAuth->linkExistingAccount($sub, self::PROVIDER, $account)) {
      throw new LoginException('already_linked');
    }
  }

  public function createUser(Identity $identity, string $role): int {
    $data = ['mail' => $identity->email, 'status' => 1];
    $name = $identity->email;
    if ($name !== NULL && !$this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $name])) {
      $data['name'] = $name;
    }
    $account = $this->externalAuth->register($identity->subject, self::PROVIDER, $data);
    if ($role !== '') {
      $account->addRole($role);
      $account->save();
    }
    return (int) $account->id();
  }

  public function rolesOf(int $userId): array {
    return array_values($this->load($userId)->getRoles());
  }

  public function signIn(int $userId): void {
    $account = $this->load($userId);
    // user_login_finalize() does not look at the status: a blocked account never gets in.
    if ($account->isBlocked()) {
      throw new LoginException('account_blocked');
    }
    user_login_finalize($account);
  }

  private function load(int $userId): UserInterface {
    $account = $this->entityTypeManager->getStorage('user')->load($userId);
    if (!$account instanceof UserInterface) {
      throw new LoginException('unknown_user');
    }
    return $account;
  }

}
