<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Webhook\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';
    private const NOW = 1_757_937_600_000;
    private const BODY = '{"id":"evt_1","type":"user.updated","data":{"id":"user_1"}}';

    /** @return array<string, string> */
    private static function sign(string $id, int $ts, string $body, string $secret = self::SECRET): array
    {
        $mac = base64_encode(hash_hmac('sha256', "{$id}.{$ts}.{$body}", $secret, true));
        return ['Atlas-Id' => $id, 'Atlas-Timestamp' => (string) $ts, 'Atlas-Signature' => "v1,{$mac}"];
    }

    public function testReturnsTheDeliveryIdAndTimestamp(): void
    {
        $result = Webhook::verify(self::BODY, self::sign('evt_1', self::NOW - 1000, self::BODY), self::SECRET, self::NOW);
        self::assertTrue($result->verified);
        self::assertSame('evt_1', $result->deliveryId);
        self::assertSame(self::NOW - 1000, $result->timestamp);
        self::assertSame('user.updated', $result->event['type'] ?? null);
    }

    public function testReadsServerStyleHeaders(): void
    {
        $server = [];
        foreach (self::sign('evt_1', self::NOW, self::BODY) as $k => $v) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }
        self::assertTrue(Webhook::verify(self::BODY, $server, self::SECRET, self::NOW)->verified);
    }

    public function testRejections(): void
    {
        $headers = self::sign('evt_1', self::NOW, self::BODY);
        $tampered = Webhook::verify(str_replace('user_1', 'user_2', self::BODY), $headers, self::SECRET, self::NOW);
        self::assertSame('signature_mismatch', $tampered->reason);
        self::assertNull($tampered->deliveryId);
        self::assertSame('signature_mismatch', Webhook::verify(self::BODY, self::sign('evt_1', self::NOW, self::BODY, 'whsec_x'), self::SECRET, self::NOW)->reason);
        self::assertSame('stale_timestamp', Webhook::verify(self::BODY, self::sign('evt_1', self::NOW - 360_000, self::BODY), self::SECRET, self::NOW)->reason);
        self::assertSame('stale_timestamp', Webhook::verify(self::BODY, self::sign('evt_1', self::NOW + 360_000, self::BODY), self::SECRET, self::NOW)->reason);
        self::assertSame('missing_headers', Webhook::verify(self::BODY, [], self::SECRET, self::NOW)->reason);
        $bad = ['Atlas-Signature' => 'v2,abc'] + $headers;
        self::assertSame('malformed_signature', Webhook::verify(self::BODY, $bad, self::SECRET, self::NOW)->reason);
    }

    public function testSeveralSignaturesVerifyWhenOneMatches(): void
    {
        $headers = self::sign('evt_1', self::NOW, self::BODY);
        $fresh = $headers['Atlas-Signature'];
        $stale = self::sign('evt_1', self::NOW, self::BODY, 'whsec_old_secret')['Atlas-Signature'];
        foreach (["{$stale} {$fresh}", "{$fresh} {$stale}", "v2,abc {$fresh}"] as $value) {
            $result = Webhook::verify(self::BODY, ['Atlas-Signature' => $value] + $headers, self::SECRET, self::NOW);
            self::assertTrue($result->verified, $value);
        }
        $onlyStale = ['Atlas-Signature' => "{$stale} {$stale}"] + $headers;
        self::assertSame('signature_mismatch', Webhook::verify(self::BODY, $onlyStale, self::SECRET, self::NOW)->reason);
        $noV1 = ['Atlas-Signature' => 'v2,abc v0,def'] + $headers;
        self::assertSame('malformed_signature', Webhook::verify(self::BODY, $noV1, self::SECRET, self::NOW)->reason);
    }
}
