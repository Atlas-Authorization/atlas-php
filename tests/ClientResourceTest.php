<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\Exception\AuthenticationException;
use Atlas\Exception\ConflictException;
use Atlas\Exception\NotFoundException;
use Atlas\Exception\RateLimitException;
use Atlas\Exception\TransportException;
use Atlas\Http;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the secret-key surface end to end against a mocked PSR-18 client —
 * users, sessions, organizations, roles, and the full error taxonomy.
 */
final class ClientResourceTest extends TestCase
{
    private MockClient $mock;
    private Client $atlas;

    protected function setUp(): void
    {
        $this->mock = new MockClient();
        // Inject the mock client; the PSR-17 factories auto-discover from Guzzle.
        $this->atlas = new Client('sk_test_secret', 'https://api.atlas.test', $this->mock);
    }

    public function testAllFortyFiveNamespacesAreWired(): void
    {
        $names = [
            'users', 'sessions', 'organizations', 'roles', 'permissions', 'oauthClients',
            'resourceServers', 'ssoConnections', 'scimTokens', 'domains', 'waitlist',
            'allowlist', 'blocklist', 'attackProtection', 'actorTokens', 'invitations',
            'webhooks', 'signInTokens', 'auditLogs', 'jwtTemplates', 'apiKeys',
            'oauthProviders', 'ssoOnboarding', 'scimProvisioning', 'fga', 'rateLimitPolicy',
            'riskBasedMfa', 'botSignals', 'networkAcls', 'managedWaf', 'logStreams',
            'branding', 'emailTemplates', 'smsTemplates', 'localizations', 'actions',
            'billing', 'messaging', 'importExport', 'dataSubjectRequests', 'radiusClients',
            'ltiPlatforms', 'instance', 'instanceSecurity', 'tokens',
        ];

        $this->assertCount(45, $names);
        foreach ($names as $name) {
            $this->assertTrue(isset($this->atlas->$name), "namespace {$name} is not wired");
            $this->assertIsObject($this->atlas->$name);
        }
    }

    public function testUsersGetSendsAuthorizedRequest(): void
    {
        $this->mock->push(200, ['object' => 'user', 'id' => 'user_123']);

        $user = $this->atlas->users->get('user_123');

        $this->assertSame('user_123', $user['id']);
        $request = $this->mock->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/users/user_123', (string) $request->getUri());
        $this->assertSame('Bearer sk_test_secret', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testUsersListSerializesQuery(): void
    {
        $this->mock->push(200, ['data' => [], 'has_more' => false, 'next_cursor' => null]);

        $this->atlas->users->list(['limit' => 25, 'starting_after' => 'user_9']);

        $uri = (string) $this->mock->lastRequest()->getUri();
        $this->assertStringContainsString('limit=25', $uri);
        $this->assertStringContainsString('starting_after=user_9', $uri);
    }

    public function testUserCreateSendsJsonBodyAndIdempotencyKey(): void
    {
        $this->mock->push(200, ['object' => 'user', 'id' => 'user_new']);

        $this->atlas->users->create(['email_address' => 'a@b.test'], 'idem-abc');

        $request = $this->mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('idem-abc', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame(['email_address' => 'a@b.test'], json_decode((string) $request->getBody(), true));
    }

    public function testSessionsCreateAndRevoke(): void
    {
        $this->mock->push(200, ['object' => 'session', 'id' => 'sess_1', 'jwt' => 'ey...']);
        $session = $this->atlas->sessions->create(['user_id' => 'user_1']);
        $this->assertSame('sess_1', $session['id']);
        $this->assertSame('POST', $this->mock->lastRequest()->getMethod());
        $this->assertSame('https://api.atlas.test/v1/sessions', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['object' => 'session', 'id' => 'sess_1', 'status' => 'revoked']);
        $this->atlas->sessions->revoke('sess_1');
        $this->assertSame('https://api.atlas.test/v1/sessions/sess_1/revoke', (string) $this->mock->lastRequest()->getUri());
    }

    public function testOrganizationsAndRolesList(): void
    {
        $this->mock->push(200, ['data' => [['id' => 'org_1']], 'has_more' => false]);
        $orgs = $this->atlas->organizations->list();
        $this->assertSame('org_1', $orgs['data'][0]['id']);
        $this->assertSame('https://api.atlas.test/v1/organizations', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['data' => [['key' => 'admin']]]);
        $roles = $this->atlas->roles->list();
        $this->assertSame('admin', $roles['data'][0]['key']);
        $this->assertSame('https://api.atlas.test/v1/roles', (string) $this->mock->lastRequest()->getUri());
    }

    public function testAllowlistAndBlocklistUseDistinctPaths(): void
    {
        $this->mock->push(200, ['data' => []]);
        $this->atlas->allowlist->list();
        $this->assertStringEndsWith('/v1/allowlist_identifiers', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['data' => []]);
        $this->atlas->blocklist->list();
        $this->assertStringEndsWith('/v1/blocklist_identifiers', (string) $this->mock->lastRequest()->getUri());
    }

    public function testNewResourceNamespacesReachExpectedEndpoints(): void
    {
        $this->mock->push(200, ['object' => 'instance', 'id' => 'ins_1']);
        $this->atlas->instance->get();
        $this->assertSame('https://api.atlas.test/v1/instance', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['object' => 'instance_security', 'kill_switches' => [], 'ip_allowlist' => []]);
        $this->atlas->instanceSecurity->get();
        $this->assertSame('https://api.atlas.test/v1/instance/security', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['data' => []]);
        $this->atlas->ltiPlatforms->list();
        $this->assertSame('https://api.atlas.test/v1/lti_platforms', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['object' => 'token_verification', 'verified' => true]);
        $verification = $this->atlas->tokens->verify(['token' => 'ey.a.b']);
        $this->assertTrue($verification['verified']);
        $this->assertSame('https://api.atlas.test/v1/tokens/verify', (string) $this->mock->lastRequest()->getUri());
    }

    public function testNotFoundBecomesTypedExceptionCarryingTheCode(): void
    {
        $this->mock->push(404, ['errors' => [['code' => 'NOT_FOUND', 'message' => 'No such user']]]);

        try {
            $this->atlas->users->get('user_missing');
            $this->fail('expected a NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame(404, $e->getStatus());
            $this->assertSame('NOT_FOUND', $e->getCode());
            $this->assertTrue($e->hasCode('NOT_FOUND'));
            $this->assertSame('No such user', $e->getMessage());
        }
    }

    public function testErrorStatusMapping(): void
    {
        $cases = [
            [401, AuthenticationException::class],
            [409, ConflictException::class],
            [429, RateLimitException::class],
        ];

        foreach ($cases as [$status, $class]) {
            $this->mock->push($status, ['errors' => [['code' => 'X', 'message' => 'boom']]]);
            try {
                $this->atlas->users->get('u');
                $this->fail("expected {$class} for status {$status}");
            } catch (\Atlas\Exception\AtlasApiException $e) {
                $this->assertInstanceOf($class, $e);
                $this->assertSame($status, $e->getStatus());
            }
        }
    }

    public function testTransportFailureBecomesTransportException(): void
    {
        $this->mock->pushException('dns failure');

        $this->expectException(TransportException::class);
        $this->atlas->users->get('user_1');
    }

    public function testDefaultBaseUrlConstant(): void
    {
        $this->assertSame('https://api.atlas.dev', Http::DEFAULT_API_URL);
    }
}
