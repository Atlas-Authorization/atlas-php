<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Tests;

use Drupal\atlas_auth\Core\Options;
use Drupal\atlas_auth\Core\Identity;
use Drupal\atlas_auth\Core\UserResolver;
use Drupal\atlas_auth\Core\LoginException;
use Drupal\atlas_auth\Tests\Support\ArrayUserStore;
use PHPUnit\Framework\TestCase;

/**
 * The Drupal-free mapping decision in {@see UserResolver::resolveUser()}:
 * linked-by-sub wins, a verified-email match links and reuses, provisioning is
 * gated on the auto-provision toggle and a verified email, and the role
 * allowlist is enforced.
 */
final class UserResolverTest extends TestCase
{
    private function options(array $overrides = []): Options
    {
        return Options::fromArray(array_merge([
            'instance_domain' => 'issuer.test',
            'client_id' => 'c',
            'client_secret' => 's',
        ], $overrides));
    }

    private function identity(array $overrides = []): Identity
    {
        return new Identity(
            $overrides['subject'] ?? 'atlas_user_1',
            array_key_exists('email', $overrides) ? $overrides['email'] : 'person@example.com',
            $overrides['emailVerified'] ?? true,
            $overrides['claims'] ?? [],
        );
    }

    public function testExistingLinkBySubIsReused(): void
    {
        $store = new ArrayUserStore();
        $store->bySub['atlas_user_1'] = 42;
        $store->roles[42] = ['administrator'];

        $id = UserResolver::resolveUser($this->identity(), $this->options(), $store);

        $this->assertSame(42, $id);
        $this->assertSame([], $store->created, 'an already-linked user must not be provisioned');
    }

    public function testVerifiedEmailMatchLinksAndReuses(): void
    {
        $store = new ArrayUserStore();
        $store->byEmail['person@example.com'] = 7;
        $store->roles[7] = ['editor'];

        $id = UserResolver::resolveUser($this->identity(), $this->options(), $store);

        $this->assertSame(7, $id);
        $this->assertSame(7, $store->bySub['atlas_user_1'], 'the matched account must be linked by sub for next time');
        $this->assertSame([], $store->created);
    }

    public function testUnverifiedEmailDoesNotTakeOverExistingAccount(): void
    {
        $store = new ArrayUserStore();
        $store->byEmail['person@example.com'] = 7;

        // email_verified = false, auto-provision off → no takeover, no account.
        $this->expectException(LoginException::class);
        $this->expectExceptionMessage('no_matching_user');
        UserResolver::resolveUser($this->identity(['emailVerified' => false]), $this->options(), $store);
    }

    public function testProvisionsWhenEnabled(): void
    {
        $store = new ArrayUserStore();
        $id = UserResolver::resolveUser(
            $this->identity(),
            $this->options(['auto_provision' => true, 'default_role' => 'author']),
            $store,
        );

        $this->assertSame(100, $id);
        $this->assertCount(1, $store->created);
        $this->assertSame('author', $store->created[0]['role']);
        $this->assertSame('atlas_user_1', $store->created[0]['sub']);
    }

    public function testNoAccountAndNoProvisioningThrows(): void
    {
        $store = new ArrayUserStore();
        $this->expectException(LoginException::class);
        $this->expectExceptionMessage('no_matching_user');
        UserResolver::resolveUser($this->identity(), $this->options(), $store);
    }

    public function testProvisioningWithoutEmailThrows(): void
    {
        $store = new ArrayUserStore();
        $this->expectException(LoginException::class);
        $this->expectExceptionMessage('cannot_provision_without_email');
        UserResolver::resolveUser(
            $this->identity(['email' => null]),
            $this->options(['auto_provision' => true]),
            $store,
        );
    }

    public function testRoleAllowlistBlocksDisallowedRole(): void
    {
        $store = new ArrayUserStore();
        $store->bySub['atlas_user_1'] = 9;
        $store->roles[9] = ['authenticated'];

        $this->expectException(LoginException::class);
        $this->expectExceptionMessage('role_not_allowed');
        UserResolver::resolveUser(
            $this->identity(),
            $this->options(['allowed_roles' => ['administrator', 'editor']]),
            $store,
        );
    }

    public function testAnyAllowedRoleOfSeveralIsEnough(): void
    {
        $store = new ArrayUserStore();
        $store->bySub['atlas_user_1'] = 9;
        $store->roles[9] = ['authenticated', 'editor'];

        $id = UserResolver::resolveUser($this->identity(), $this->options(['allowed_roles' => ['editor']]), $store);

        $this->assertSame(9, $id);
    }

    public function testARefusedAccountMatchedByEmailIsNotLinked(): void
    {
        $store = new ArrayUserStore();
        $store->byEmail['person@example.com'] = 7;
        $store->roles[7] = ['authenticated'];

        try {
            UserResolver::resolveUser($this->identity(), $this->options(['allowed_roles' => ['administrator']]), $store);
            $this->fail('the role gate must refuse');
        } catch (LoginException $e) {
            $this->assertSame('role_not_allowed', $e->getMessage());
        }
        $this->assertSame([], $store->bySub, 'a refused account must not be linked');
    }

    public function testProvisioningWithADefaultRoleOutsideTheAllowlistIsRefused(): void
    {
        $store = new ArrayUserStore();
        $this->expectException(LoginException::class);
        $this->expectExceptionMessage('provision_role_not_allowed');
        UserResolver::resolveUser(
            $this->identity(),
            $this->options(['auto_provision' => true, 'default_role' => 'author', 'allowed_roles' => ['editor']]),
            $store,
        );
    }
}
