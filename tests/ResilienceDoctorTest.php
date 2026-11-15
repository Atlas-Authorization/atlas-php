<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Auth\DeviceFlow;
use Atlas\Auth\OAuthFlowException;
use Atlas\Client;
use Atlas\Exception\RateLimitException;
use Atlas\Exception\ServerException;
use Atlas\Resilience;
use Atlas\Telemetry\OpenTelemetry;
use Atlas\Tests\Support\MockClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

final class ResilienceDoctorTest extends TestCase
{
    /** @var list<int> */
    private array $slept = [];

    private function client(MockClient $mock, ?Resilience $r = null): Client
    {
        $f = new HttpFactory();

        return new Client('sk_test_x', 'https://api.example.test', $mock, $f, $f, resilience: $r);
    }

    private function resilience(int $max = 2, bool $status = true): Resilience
    {
        return new Resilience(maxRetries: $max, baseDelayMs: 100, maxDelayMs: 10_000, consultStatus: $status, sleep: function (int $ms): void {
            $this->slept[] = $ms;
        });
    }

    public function testWithoutResilienceNothingIsRetried(): void
    {
        $mock = new MockClient();
        $mock->push(503, ['errors' => [['code' => 'UNAVAILABLE', 'message' => 'down']]]);
        $this->expectException(ServerException::class);
        $this->client($mock)->users->get('user_1');
    }

    public function testRetryAfterIsHonouredOn429(): void
    {
        $mock = new MockClient();
        $mock->push(429, ['errors' => [['code' => 'RATE_LIMITED', 'message' => 'slow']]], ['Retry-After' => '3']);
        $mock->push(200, ['id' => 'user_1']);
        $out = $this->client($mock, $this->resilience())->users->get('user_1');
        self::assertSame('user_1', $out['id']);
        self::assertSame([3000], $this->slept);
    }

    public function testAnOverlongRetryAfterIsNotSleptAndTheErrorSurfaces(): void
    {
        $mock = new MockClient();
        $mock->push(429, ['errors' => [['code' => 'RATE_LIMITED', 'message' => 'slow']]], ['Retry-After' => '3600']);
        $this->expectException(RateLimitException::class);
        try {
            $this->client($mock, $this->resilience())->users->get('user_1');
        } finally {
            self::assertSame([], $this->slept);
        }
    }

    public function testBacksOffOn503ThenGivesUpAfterMaxRetries(): void
    {
        $mock = new MockClient();
        for ($i = 0; $i < 3; $i++) {
            $mock->push(503, ['errors' => [['code' => 'UNAVAILABLE', 'message' => 'down']]]);
        }
        try {
            $this->client($mock, $this->resilience(2, false))->users->get('user_1');
            self::fail('expected ServerException');
        } catch (ServerException) {
            self::assertSame(3, $mock->requestCount());
            self::assertCount(2, $this->slept);
            self::assertLessThanOrEqual(100, $this->slept[0]);
            self::assertLessThanOrEqual(200, $this->slept[1]);
        }
    }

    public function testAnOutageReportedByTheStatusEndpointFailsFast(): void
    {
        $mock = new MockClient();
        $mock->push(503, ['errors' => [['code' => 'UNAVAILABLE', 'message' => 'down']]]);
        $mock->push(200, ['status' => 'major_outage']);
        try {
            $this->client($mock, $this->resilience())->users->get('user_1');
            self::fail('expected ServerException');
        } catch (ServerException) {
            self::assertSame(2, $mock->requestCount(), 'one request, one status read, no retry');
            self::assertSame('/v1/status', $mock->lastRequest()->getUri()->getPath());
            self::assertSame([], $this->slept);
        }
    }

    public function testWritesWithoutAnIdempotencyKeyAreNeverRetried(): void
    {
        $mock = new MockClient();
        $mock->push(503, ['errors' => [['code' => 'UNAVAILABLE', 'message' => 'down']]]);
        $this->expectException(ServerException::class);
        $this->client($mock, $this->resilience(2, false))->users->create(['email_address' => ['a@example.com']]);
    }

    public function testWritesWithAnIdempotencyKeyAreRetried(): void
    {
        $mock = new MockClient();
        $mock->push(503, ['errors' => [['code' => 'UNAVAILABLE', 'message' => 'down']]]);
        $mock->push(200, ['id' => 'user_9']);
        $out = $this->client($mock, $this->resilience(2, false))->users->create(['email_address' => ['a@example.com']], 'idem-1');
        self::assertSame('user_9', $out['id']);
        self::assertSame('idem-1', $mock->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function testTransportFailuresOnReadsAreRetried(): void
    {
        $mock = new MockClient();
        $mock->pushException();
        $mock->push(200, ['id' => 'user_1']);
        self::assertSame('user_1', $this->client($mock, $this->resilience())->users->get('user_1')['id']);
    }

    public function testRetryAfterParsing(): void
    {
        self::assertSame(7, Resilience::parseRetryAfter('7'));
        self::assertSame(60, Resilience::parseRetryAfter(gmdate('D, d M Y H:i:s', 1_000_060) . ' GMT', 1_000_000));
        self::assertNull(Resilience::parseRetryAfter(''));
        self::assertNull(Resilience::parseRetryAfter('soon'));
    }

    public function testDoctorReportsEveryCheck(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['instance_id' => 'ins_1'], ['Date' => gmdate('D, d M Y H:i:s') . ' GMT']);
        $mock->push(200, ['status' => 'operational']);
        $mock->push(200, ['keys' => [['kid' => 'k1']]]);
        $report = $this->client($mock)->doctor('https://x.example/jwks.json');
        self::assertTrue($report->ok, (string) $report);
        $names = array_column($report->checks, 'name');
        foreach (['base url', 'api reachability', 'secret key', 'clock skew', 'service status', 'jwks'] as $n) {
            self::assertContains($n, $names);
        }
        self::assertStringContainsString('ins_1', (string) $report);
    }

    public function testDoctorFailsOnABadKeyAClockSkewAndAnEmptyJwks(): void
    {
        $mock = new MockClient();
        $mock->push(401, ['errors' => [['code' => 'UNAUTHENTICATED', 'message' => 'no']]], ['Date' => gmdate('D, d M Y H:i:s', time() - 600) . ' GMT']);
        $mock->push(200, ['status' => 'major_outage']);
        $mock->push(200, ['keys' => []]);
        $report = $this->client($mock)->doctor('https://x.example/jwks.json');
        self::assertFalse($report->ok);
        $by = array_column($report->checks, 'status', 'name');
        self::assertSame('fail', $by['secret key']);
        self::assertSame('fail', $by['clock skew']);
        self::assertSame('fail', $by['service status']);
        self::assertSame('fail', $by['jwks']);
    }

    public function testOpenTelemetryRouteLabelsAreLowCardinality(): void
    {
        self::assertSame('/v1/users/{id}/ban', OpenTelemetry::route('/v1/users/user_2abcDEF123/ban'));
        self::assertSame('/v1/sessions/{id}/touch', OpenTelemetry::route('/v1/sessions/0b1c2d3e-4f50-6789-abcd-ef0123456789/touch'));
        self::assertSame('/v1/users', OpenTelemetry::route('/v1/users'));
        // with telemetry on and no OpenTelemetry installed, requests still work
        $mock = new MockClient();
        $mock->push(200, ['id' => 'user_1']);
        $f = new HttpFactory();
        $atlas = new Client('sk_test_x', 'https://api.example.test', $mock, $f, $f, telemetry: true);
        self::assertSame('user_1', $atlas->users->get('user_1')['id']);
    }

    public function testDeviceFlowPollsThroughPendingAndSlowDown(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['device_code' => 'dc', 'user_code' => 'ABCD-EFGH', 'verification_uri' => 'https://x/d', 'expires_in' => 600, 'interval' => 5]);
        $mock->push(400, ['error' => 'authorization_pending']);
        $mock->push(400, ['error' => 'slow_down']);
        $mock->push(200, ['access_token' => 'at']);
        $f = new HttpFactory();
        $waits = [];
        $flow = new DeviceFlow('https://acme-fapi.example.test', 'client_1', null, $mock, $f, $f, function (int $ms) use (&$waits): void {
            $waits[] = $ms;
        });
        $start = $flow->start(['openid', 'profile']);
        parse_str((string) $mock->requests[0]->getBody(), $form);
        self::assertSame('openid profile', $form['scope']);
        self::assertSame('/oauth2/device_authorization', $mock->requests[0]->getUri()->getPath());
        self::assertSame('at', $flow->poll($start)['access_token']);
        self::assertSame([5000, 5000, 10000], $waits);
    }

    public function testDeviceFlowDenialThrows(): void
    {
        $mock = new MockClient();
        $mock->push(400, ['error' => 'access_denied', 'error_description' => 'no']);
        $f = new HttpFactory();
        $flow = new DeviceFlow('https://acme-fapi.example.test', 'client_1', null, $mock, $f, $f, static function (int $ms): void {
        });
        $this->expectException(OAuthFlowException::class);
        $flow->poll(['device_code' => 'dc', 'expires_in' => 600]);
    }
}
