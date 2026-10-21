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
}
