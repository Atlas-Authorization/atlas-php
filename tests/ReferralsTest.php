<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\ErrorCode;
use Atlas\Exception\ConflictException;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/**
 * Referral attribution: the instance settings, connection test and call log, and the user
 * referral fields and fraud flag.
 */
final class ReferralsTest extends TestCase
{
    private const BASE = 'https://api.atlas.test';
    private const SETTINGS = [
        'object' => 'referral_settings', 'enabled' => true, 'provider' => 'webhook',
        'endpoint_url' => 'https://referrals.example.com/atlas', 'signing_secret_set' => true, 'api_key_set' => false,
        'url_params' => ['ref', 'via', 'aff'], 'auto_enroll_users' => true, 'timeout_ms' => 3000,
        'security' => [
            'challenge_referred_signups' => 'always', 'on_high_risk' => 'flag',
            'hold_until_email_verified' => false, 'max_referred_signups_per_ip_hour' => 5,
        ],
        'providers' => [['id' => 'webhook', 'capabilities' => ['signal' => true], 'needs' => ['endpoint_url', 'signing_secret']]],
    ];
    private const CALL = [
        'object' => 'referral_call', 'id' => 'rfc_1', 'kind' => 'signup', 'status' => 'succeeded',
        'outcome' => 'referred', 'customer_ref' => 'user_1', 'attempts' => 2,
        'risk' => ['level' => 'low', 'score' => 5, 'signals' => [], 'captcha' => 'passed', 'emailVerified' => true],
    ];

    private MockClient $mock;
    private Client $atlas;

    protected function setUp(): void
    {
        $this->mock = new MockClient();
        $this->atlas = new Client('sk_test_secret', self::BASE, $this->mock);
    }

    private function body(): mixed
    {
        return json_decode((string) $this->mock->lastRequest()->getBody(), true);
    }

    private function rawBody(): string
    {
        return (string) $this->mock->lastRequest()->getBody();
    }

    private function sent(): array
    {
        $r = $this->mock->lastRequest();
        return [$r->getMethod(), (string) $r->getUri()];
    }

    public function testSettingsGetUpdateAndTest(): void
    {
        $this->mock->push(200, self::SETTINGS);
        $this->assertSame('flag', $this->atlas->referrals->get()['security']['on_high_risk']);
        $this->assertSame(['GET', self::BASE . '/v1/instance/referrals'], $this->sent());

        $this->mock->push(200, self::SETTINGS);
        $this->atlas->referrals->update(
            ['enabled' => true, 'link_template' => null, 'security' => ['on_high_risk' => 'hold']],
            'idem_1',
        );
        $this->assertSame('PATCH', $this->mock->lastRequest()->getMethod());
        $this->assertSame('{"enabled":true,"link_template":null,"security":{"on_high_risk":"hold"}}', $this->rawBody());
        $this->assertSame('idem_1', $this->mock->lastRequest()->getHeaderLine('Idempotency-Key'));

        $this->mock->push(200, ['ok' => false, 'http_status' => 401, 'error' => ['code' => 'signature_rejected', 'message' => 'm']]);
        $this->assertSame('signature_rejected', $this->atlas->referrals->test()['error']['code']);
        $this->assertSame(['POST', self::BASE . '/v1/instance/referrals/test'], $this->sent());
    }

    public function testInboundTwoWaySyncFields(): void
    {
        $inbound = [
            'url' => 'https://api.example.test/v1/webhooks/referrals/ins_1', 'secret_set' => true, 'endpoint_id' => 'we_1',
            'last_event' => ['at' => 1700000000000, 'type' => 'referral.updated', 'outcome' => 'applied'],
        ];
        $this->mock->push(200, self::SETTINGS + ['inbound' => $inbound]);
        $view = $this->atlas->referrals->get();
        $this->assertSame('https://api.example.test/v1/webhooks/referrals/ins_1', $view['inbound']['url']);
        $this->assertSame('applied', $view['inbound']['last_event']['outcome']);

        $this->mock->push(200, self::SETTINGS);
        $this->atlas->referrals->update(['inbound_secret' => 'whsec_' . str_repeat('y', 32), 'inbound_endpoint_id' => 'we_1']);
        $this->assertSame('{"inbound_secret":"whsec_' . str_repeat('y', 32) . '","inbound_endpoint_id":"we_1"}', $this->rawBody());

        $this->mock->push(200, self::SETTINGS);
        $this->atlas->referrals->update(['inbound_secret' => null, 'inbound_endpoint_id' => null]);
        $this->assertSame('{"inbound_secret":null,"inbound_endpoint_id":null}', $this->rawBody());
    }

    public function testCallLogAndRetry(): void
    {
        $this->mock->push(200, ['object' => 'list', 'data' => [self::CALL], 'has_more' => false]);
        $page = $this->atlas->referrals->listCalls(['limit' => 10, 'status' => 'failed', 'kind' => 'signup', 'user_id' => 'user_1']);
        $this->assertTrue($page['data'][0]['risk']['emailVerified']);
        $this->assertSame(
            ['GET', self::BASE . '/v1/instance/referrals/calls?limit=10&status=failed&kind=signup&user_id=user_1'],
            $this->sent(),
        );

        $this->mock->push(200, ['object' => 'list', 'data' => []]);
        $this->atlas->referrals->listCalls();
        $this->assertSame(['GET', self::BASE . '/v1/instance/referrals/calls'], $this->sent());

        $this->mock->push(200, self::CALL);
        $this->assertSame('referred', $this->atlas->referrals->retryCall('rfc_1', 'idem_2')['outcome']);
        $this->assertSame(['POST', self::BASE . '/v1/instance/referrals/calls/rfc_1/retry'], $this->sent());
        $this->assertSame('idem_2', $this->mock->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function testUserReferralFieldsAndFlag(): void
    {
        $user = [
            'object' => 'user', 'id' => 'user_1',
            'referral' => [
                'partner_id' => 'pn_1', 'referrer_external_id' => 'cust_9', 'referrer_user_id' => 'user_9',
                'matched_via' => 'code', 'status' => 'lead', 'risk_level' => 'low', 'flagged' => false,
            ],
            'referral_code' => 'ada-1', 'referral_link' => 'https://app.example.com/join?ref=ada-1',
        ];
        $this->mock->push(200, $user);
        $created = $this->atlas->users->create([
            'email_address' => 'a@example.com',
            'referral' => ['code' => 'ACME10', 'click_id' => 'clk_123'],
            'referral_code' => 'ada-1',
        ]);
        $this->assertSame('user_9', $created['referral']['referrer_user_id']);
        $this->assertSame(
            ['email_address' => 'a@example.com', 'referral' => ['code' => 'ACME10', 'click_id' => 'clk_123'], 'referral_code' => 'ada-1'],
            $this->body(),
        );

        $this->mock->push(200, $user);
        $this->atlas->users->update('user_1', ['referral_code' => null]);
        $this->assertSame('{"referral_code":null}', $this->rawBody());

        $this->mock->push(200, ['object' => 'user_referral', 'user_id' => 'user_1', 'status' => 'flagged', 'flagged' => true]);
        $this->assertTrue($this->atlas->users->flagReferral('user_1', 'ring', 'idem_3')['flagged']);
        $this->assertSame(['POST', self::BASE . '/v1/users/user_1/referral/flag'], $this->sent());
        $this->assertSame(['reason' => 'ring'], $this->body());
        $this->assertSame('idem_3', $this->mock->lastRequest()->getHeaderLine('Idempotency-Key'));

        $this->mock->push(200, ['object' => 'user_referral']);
        $this->atlas->users->flagReferral('user_1');
        $this->assertSame('{}', $this->rawBody());
    }

    public function testReferralErrorCodes(): void
    {
        $this->mock->push(409, ['errors' => [['code' => 'REFERRAL_CODE_TAKEN', 'message' => 'taken', 'param' => 'referral_code']]]);
        try {
            $this->atlas->users->update('user_1', ['referral_code' => 'ada-1']);
            $this->fail('expected a conflict');
        } catch (ConflictException $e) {
            $this->assertSame(409, $e->getStatus());
            $this->assertSame(ErrorCode::REFERRAL_CODE_TAKEN, $e->getErrorCode());
        }
        $this->assertSame('REFERRALS_NOT_CONFIGURED', ErrorCode::REFERRALS_NOT_CONFIGURED);
    }
    public function testV2SettingsPingTestAndCallPaging(): void
    {
        $settings = self::SETTINGS + [
            'tracking_token' => 'tt_public_1', 'signup_code_field' => true, 'include_referral_claims' => true,
            'connection' => [
                'last_ping' => ['ok' => true, 'org_name' => 'Acme', 'clock_skew_warning' => true],
                'alert' => ['code' => 'signature_rejected', 'message' => '401', 'at' => 9],
            ],
        ];
        $this->mock->push(200, $settings);
        $view = $this->atlas->referrals->get();
        $this->assertSame('tt_public_1', $view['tracking_token']);
        $this->assertSame('signature_rejected', $view['connection']['alert']['code']);

        $this->mock->push(200, $settings);
        $this->atlas->referrals->update([
            'provider' => 'referally', 'tracking_token' => 'tt_public_1', 'code_lookup_url' => null,
            'forward_lifecycle' => false, 'mirror_to_public_metadata' => true, 'include_referral_claims' => true,
        ]);
        $this->assertSame(
            '{"provider":"referally","tracking_token":"tt_public_1","code_lookup_url":null,"forward_lifecycle":false,"mirror_to_public_metadata":true,"include_referral_claims":true}',
            $this->rawBody(),
        );

        $this->mock->push(200, ['ok' => true, 'method' => 'ping', 'ping' => ['ok' => true, 'org_name' => 'Acme'], 'warnings' => ['clock skew']]);
        $tested = $this->atlas->referrals->test();
        $this->assertSame(['ping', 'Acme'], [$tested['method'], $tested['ping']['org_name']]);

        $this->mock->push(200, ['object' => 'list', 'data' => [self::CALL + ['error_code' => 'signature_rejected', 'batch_id' => 'rbf_1']], 'has_more' => true]);
        $page = $this->atlas->referrals->listCalls(['limit' => 1000, 'error_code' => 'signature_rejected', 'starting_after' => 'rfc_0']);
        $this->assertSame(
            ['GET', self::BASE . '/v1/instance/referrals/calls?limit=1000&error_code=signature_rejected&starting_after=rfc_0'],
            $this->sent(),
        );
        $this->assertSame('rbf_1', $page['data'][0]['batch_id']);
    }

    public function testV2RetryFailedSimulateBackfillAndUserResult(): void
    {
        $this->mock->push(200, ['object' => 'referral_retry', 'requeued' => 4]);
        $this->assertSame(4, $this->atlas->referrals->retryFailed(['error_code' => 'signature_rejected'], 'k1')['requeued']);
        $this->assertSame(['POST', self::BASE . '/v1/instance/referrals/calls/retry_failed'], $this->sent());
        $this->assertSame('{"error_code":"signature_rejected"}', $this->rawBody());
        $this->assertSame('k1', $this->mock->lastRequest()->getHeaderLine('Idempotency-Key'));
        $this->mock->push(200, ['object' => 'referral_retry', 'requeued' => 0]);
        $this->atlas->referrals->retryFailed();
        $this->assertSame('{}', $this->rawBody());

        $this->mock->push(200, [
            'object' => 'referral_simulation', 'ok' => true, 'customer_ref' => 'atlas_test_abc',
            'result' => ['referral' => ['partner_external_ref' => 'cust_9'], 'partner' => null, 'notes' => ['n']],
        ]);
        $sim = $this->atlas->referrals->simulate('ACME10');
        $this->assertSame('cust_9', $sim['result']['referral']['partner_external_ref']);
        $this->assertSame(['POST', self::BASE . '/v1/instance/referrals/simulate'], $this->sent());
        $this->assertSame('{"code":"ACME10"}', $this->rawBody());
        $this->mock->push(200, ['object' => 'referral_simulation', 'ok' => false]);
        $this->atlas->referrals->simulate();
        $this->assertSame('{}', $this->rawBody());

        $job = ['object' => 'referral_backfill', 'id' => 'rbf_1', 'status' => 'running', 'calls' => ['pending' => 10]];
        $this->mock->push(200, $job);
        $out = $this->atlas->referrals->backfill(['since' => '2026-01-01', 'codes' => ['cust_1' => 'ACME10']], 'k2');
        $this->assertSame(10, $out['calls']['pending']);
        $this->assertSame(['POST', self::BASE . '/v1/instance/referrals/backfill'], $this->sent());
        $this->assertSame('{"since":"2026-01-01","codes":{"cust_1":"ACME10"}}', $this->rawBody());
        $this->mock->push(200, ['status' => 'completed'] + $job);
        $this->assertSame('completed', $this->atlas->referrals->getBackfill('rbf_1')['status']);
        $this->assertSame(['GET', self::BASE . '/v1/instance/referrals/backfill/rbf_1'], $this->sent());

        $this->mock->push(200, [
            'object' => 'user_referral', 'user_id' => 'user_1', 'status' => 'attributed', 'customer_ref' => 'cust_1',
            'referral_status' => 'active', 'notes' => ['n'],
        ]);
        $res = $this->atlas->referrals->getForUser('user_1');
        $this->assertSame(['attributed', 'active'], [$res['status'], $res['referral_status']]);
        $this->assertSame(['GET', self::BASE . '/v1/users/user_1/referral'], $this->sent());
    }

    public function testV2SetExternalIdAndBeforeUserCreateHook(): void
    {
        $this->mock->push(200, ['object' => 'user', 'id' => 'user_1', 'external_id' => 'cust_1', 'referral' => ['status' => 'pending']]);
        $this->assertSame('pending', $this->atlas->users->setExternalId('user_1', 'cust_1')['referral']['status']);
        $this->assertSame(['PATCH', self::BASE . '/v1/users/user_1'], $this->sent());
        $this->assertSame('{"external_id":"cust_1"}', $this->rawBody());
        $this->mock->push(200, ['object' => 'user', 'id' => 'user_1']);
        $this->atlas->users->setExternalId('user_1', null);
        $this->assertSame('{"external_id":null}', $this->rawBody());

        $hook = [
            'object' => 'before_user_create_hook', 'enabled' => true, 'url' => 'https://app.example.com/hooks/before-create',
            'signing_secret_set' => true, 'timeout_ms' => 3000, 'last_call' => ['at' => 3, 'status' => 'ok', 'error' => null],
        ];
        $this->mock->push(200, $hook);
        $this->assertSame('ok', $this->atlas->userHooks->getBeforeUserCreate()['last_call']['status']);
        $this->assertSame(['GET', self::BASE . '/v1/instance/hooks/before_user_create'], $this->sent());
        $this->mock->push(200, $hook);
        $this->atlas->userHooks->updateBeforeUserCreate(['enabled' => true, 'url' => null, 'timeout_ms' => 2000], 'k3');
        $this->assertSame(['PATCH', self::BASE . '/v1/instance/hooks/before_user_create'], $this->sent());
        $this->assertSame('{"enabled":true,"url":null,"timeout_ms":2000}', $this->rawBody());
        $this->assertSame('k3', $this->mock->lastRequest()->getHeaderLine('Idempotency-Key'));
        $this->mock->push(200, ['ok' => true, 'latency_ms' => 12, 'answer' => ['external_id' => 'cust_1']]);
        $this->assertSame('cust_1', $this->atlas->userHooks->testBeforeUserCreate()['answer']['external_id']);
        $this->assertSame(['POST', self::BASE . '/v1/instance/hooks/before_user_create/test'], $this->sent());
    }
}
