<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\ErrorCode;
use Atlas\Exception\ConflictException;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/**
 * API key spend caps (limits + usage metering), session keep-alive and idle
 * expiry, and the explicit notify flag on user create/import.
 */
final class ApiKeyLimitsTest extends TestCase
{
    private const LIMITS = [
        ['unit' => 'requests', 'amount' => 1000, 'period' => 'day'],
        ['unit' => 'usd_cents', 'amount' => 500, 'period' => 'request'],
    ];

    private const STATE = [
        'unit' => 'requests', 'period' => 'day', 'amount' => 1000, 'used' => 10, 'remaining' => 990,
        'window_start' => 1760400000000, 'resets_at' => 1760486400000,
    ];

    private MockClient $mock;
    private Client $atlas;

    protected function setUp(): void
    {
        $this->mock = new MockClient();
        $this->atlas = new Client('sk_test_secret', 'https://api.atlas.test', $this->mock);
    }

    /** @return array<string,mixed> */
    private function lastBody(): array
    {
        return json_decode((string) $this->mock->lastRequest()->getBody(), true);
    }

    public function testCreateSendsLimits(): void
    {
        $this->mock->push(201, ['object' => 'api_key', 'id' => 'ak_1', 'limits' => self::LIMITS, 'usage' => [self::STATE]]);

        $key = $this->atlas->apiKeys->create(['subject_type' => 'user', 'subject_id' => 'user_1', 'limits' => self::LIMITS]);

        $request = $this->mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/api_keys', (string) $request->getUri());
        $this->assertSame(self::LIMITS, $this->lastBody()['limits']);
        $this->assertSame(990, $key['usage'][0]['remaining']);
    }

    public function testUpdateClearsLimitsWithNull(): void
    {
        $this->mock->push(200, ['object' => 'api_key', 'id' => 'ak_1', 'limits' => null, 'usage' => []]);

        $this->atlas->apiKeys->update('ak_1', ['limits' => null]);

        $request = $this->mock->lastRequest();
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/api_keys/ak_1', (string) $request->getUri());
        $this->assertSame(['limits' => null], $this->lastBody());
    }

    public function testRecordUsageSendsAmountUnitAndBodyIdempotencyKey(): void
    {
        $this->mock->push(200, [
            'object' => 'api_key_usage_debit', 'key_id' => 'ak_1', 'unit' => 'requests', 'amount' => 1,
            'allowed' => true, 'exceeded' => [], 'usage' => [self::STATE],
            'counters' => [['unit' => 'requests', 'period' => 'day', 'used' => 11, 'window_start' => 1, 'resets_at' => 2]],
            'idempotent_replay' => false,
        ]);

        $debit = $this->atlas->apiKeys->recordUsage('ak_1', 1, 'requests', 'req_42');

        $request = $this->mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/api_keys/ak_1/usage', (string) $request->getUri());
        $this->assertSame(['amount' => 1, 'unit' => 'requests', 'idempotency_key' => 'req_42'], $this->lastBody());
        $this->assertSame('', $request->getHeaderLine('Idempotency-Key'), 'the idempotency key travels in the body');
        $this->assertTrue($debit['allowed']);
        $this->assertSame(11, $debit['counters'][0]['used']);
    }

    public function testRecordUsageOmitsAnAbsentIdempotencyKeyAndSurfacesARefusal(): void
    {
        $this->mock->push(200, [
            'object' => 'api_key_usage_debit', 'key_id' => 'ak_1', 'unit' => 'usd_cents', 'amount' => 900,
            'allowed' => false, 'code' => 'LIMIT_EXCEEDED', 'exceeded' => [self::STATE], 'usage' => [self::STATE],
            'counters' => [], 'idempotent_replay' => false,
        ]);

        $debit = $this->atlas->apiKeys->recordUsage('ak_1', 900, 'usd_cents');

        $this->assertSame(['amount' => 900, 'unit' => 'usd_cents'], $this->lastBody());
        $this->assertFalse($debit['allowed']);
        $this->assertSame(ErrorCode::LIMIT_EXCEEDED, $debit['code']);
    }

    public function testRecordUsageIdempotencyConflictThrows(): void
    {
        $this->mock->push(409, ['errors' => [['code' => 'IDEMPOTENCY_CONFLICT', 'message' => 'reused']]]);

        try {
            $this->atlas->apiKeys->recordUsage('ak_1', 2, 'requests', 'req_42');
            $this->fail('expected a ConflictException');
        } catch (ConflictException $e) {
            $this->assertTrue($e->hasCode(ErrorCode::IDEMPOTENCY_CONFLICT));
        }
    }

    public function testGetUsage(): void
    {
        $this->mock->push(200, ['object' => 'api_key_usage', 'key_id' => 'ak_1', 'limits' => self::LIMITS, 'usage' => [self::STATE], 'counters' => []]);

        $usage = $this->atlas->apiKeys->getUsage('ak_1');

        $request = $this->mock->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/api_keys/ak_1/usage', (string) $request->getUri());
        $this->assertSame('', (string) $request->getBody());
        $this->assertSame('api_key_usage', $usage['object']);
        $this->assertSame(self::LIMITS, $usage['limits']);
    }

    public function testVerifyReturnsNameScopesLimitsAndUsage(): void
    {
        $this->mock->push(200, [
            'valid' => true, 'id' => 'ak_1', 'name' => 'CI', 'scopes' => ['read'], 'constraints' => [],
            'limits' => self::LIMITS, 'usage' => [self::STATE],
        ]);

        $result = $this->atlas->apiKeys->verify('ak_secret');

        $this->assertSame('CI', $result['name']);
        $this->assertSame(['read'], $result['scopes']);
        $this->assertSame('requests', $result['usage'][0]['unit']);
    }

    public function testSessionTouch(): void
    {
        $this->mock->push(200, ['object' => 'session', 'id' => 'sess_1', 'idle_expires_at' => 1760486400000, 'extended' => true]);

        $session = $this->atlas->sessions->touch('sess_1');

        $request = $this->mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/sessions/sess_1/touch', (string) $request->getUri());
        $this->assertSame('', (string) $request->getBody());
        $this->assertTrue($session['extended']);
        $this->assertSame(1760486400000, $session['idle_expires_at']);
    }

    public function testSessionTouchOnAnEndedSessionThrows(): void
    {
        $this->mock->push(409, ['errors' => [['code' => 'SESSION_IDLE_EXPIRED', 'message' => 'ended']]]);

        try {
            $this->atlas->sessions->touch('sess_1');
            $this->fail('expected a ConflictException');
        } catch (ConflictException $e) {
            $this->assertTrue($e->hasCode(ErrorCode::SESSION_IDLE_EXPIRED));
        }
    }

    public function testUserSessionsListDecodesIdleExpiryOrgAndImpersonator(): void
    {
        $this->mock->push(200, ['object' => 'list', 'data' => [
            [
                'object' => 'session', 'id' => 'sess_1', 'user_id' => 'user_1', 'status' => 'active',
                'abandon_at' => 1760486400000, 'idle_expires_at' => 1760486400000,
                'last_active_organization_id' => 'org_1', 'impersonated_by' => 'user_admin',
            ],
            [
                'object' => 'session', 'id' => 'sess_2', 'user_id' => 'user_1', 'status' => 'active',
                'abandon_at' => 1760490000000, 'idle_expires_at' => 1760490000000,
                'last_active_organization_id' => null, 'impersonated_by' => null,
            ],
        ], 'has_more' => false]);

        $page = $this->atlas->users->listSessions('user_1');

        $request = $this->mock->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/users/user_1/sessions', (string) $request->getUri());
        $first = $page['data'][0];
        $this->assertSame(1760486400000, $first['idle_expires_at']);
        $this->assertSame($first['abandon_at'], $first['idle_expires_at']);
        $this->assertSame('org_1', $first['last_active_organization_id']);
        $this->assertSame('user_admin', $first['impersonated_by']);
        $second = $page['data'][1];
        $this->assertSame(1760490000000, $second['idle_expires_at']);
        $this->assertNull($second['last_active_organization_id']);
        $this->assertNull($second['impersonated_by']);
    }

    public function testSessionReadAndListCarryIdleExpiry(): void
    {
        $this->mock->push(200, ['object' => 'session', 'id' => 'sess_1', 'abandon_at' => 1760486400000, 'idle_expires_at' => 1760486400000]);
        $this->assertSame(1760486400000, $this->atlas->sessions->get('sess_1')['idle_expires_at']);

        $this->mock->push(200, ['object' => 'list', 'data' => [['object' => 'session', 'id' => 'sess_1', 'idle_expires_at' => 1760486400000]]]);
        $page = $this->atlas->sessions->list('user_1');
        $this->assertSame('https://api.atlas.test/v1/sessions?user_id=user_1', (string) $this->mock->lastRequest()->getUri());
        $this->assertSame(1760486400000, $page['data'][0]['idle_expires_at']);
    }

    public function testUserCreateAndImportSendNotify(): void
    {
        $this->mock->push(201, ['id' => 'user_1']);
        $this->atlas->users->create(['email_address' => ['ada@example.com'], 'notify' => false]);
        $this->assertFalse($this->lastBody()['notify']);

        $this->mock->push(202, ['id' => 'job_1']);
        $this->atlas->importExport->importUsers(['users' => [], 'notify' => false]);
        $this->assertSame('https://api.atlas.test/v1/user_imports', (string) $this->mock->lastRequest()->getUri());
        $this->assertFalse($this->lastBody()['notify']);
    }
}
