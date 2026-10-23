<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\Constants;
use Atlas\PolicySource\PolicySourceProtocol;
use Atlas\Tests\Support\MockClient;
use Atlas\Verify\VerifyResult;
use Atlas\Webhook\Webhook;
use PHPUnit\Framework\TestCase;

/**
 * Policy acceptance: the Backend API surface, the policy source helpers (checked against vectors
 * computed with the TypeScript reference) and the `pol` claim.
 */
final class PoliciesTest extends TestCase
{
    private const SECRET = 'whsec_policy_source_test_secret';
    private const NOW = 1_800_000_000_000;
    private const SIG_KEY = 'veZ8K1cchaPsRfu/JPTU6fBWAsbUn/e/iaGPyP9nFoU=';
    private const SIG_ALL = 'yJ3sPsHcT6fbi40UVNPScqsB9/5UomhRMTD/VcpNlWA=';
    private const SIG_EVENT = 'm2QmhSM8RGmN8j3QS0wyVr1kqaHmykmswSug5wyHaVU=';

    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>|null,3:\Closure(Client):mixed}>
     */
    private static function operations(): array
    {
        return [
            ['GET', '/v1/policies?include_retired=true', null, static fn (Client $c) => $c->policies->list(['include_retired' => 'true'])],
            ['POST', '/v1/policies', ['key' => 'tos', 'title' => 'Terms'], static fn (Client $c) => $c->policies->create(['key' => 'tos', 'title' => 'Terms'])],
            ['GET', '/v1/policies/tos', null, static fn (Client $c) => $c->policies->get('tos')],
            ['PATCH', '/v1/policies/tos', ['status' => 'retired', 'grace_days' => null], static fn (Client $c) => $c->policies->update('tos', ['status' => 'retired', 'grace_days' => null])],
            ['DELETE', '/v1/policies/tos', null, static fn (Client $c) => $c->policies->delete('tos')],
            ['GET', '/v1/policies/tos/versions', null, static fn (Client $c) => $c->policies->versions->list('tos')],
            ['POST', '/v1/policies/tos/versions', ['version' => 'v2', 'publish' => true], static fn (Client $c) => $c->policies->versions->create('tos', ['version' => 'v2', 'publish' => true])],
            ['GET', '/v1/policies/tos/versions/v2', null, static fn (Client $c) => $c->policies->versions->get('tos', 'v2')],
            ['PATCH', '/v1/policies/tos/versions/v2', ['summary_of_changes' => null], static fn (Client $c) => $c->policies->versions->update('tos', 'v2', ['summary_of_changes' => null])],
            ['DELETE', '/v1/policies/tos/versions/v2', null, static fn (Client $c) => $c->policies->versions->delete('tos', 'v2')],
            ['POST', '/v1/policies/tos/versions/v2/publish', ['effective_at' => self::NOW], static fn (Client $c) => $c->policies->versions->publish('tos', 'v2', ['effective_at' => self::NOW])],
            ['POST', '/v1/policies/tos/versions/v2/publish', [], static fn (Client $c) => $c->policies->versions->publish('tos', 'v2')],
            ['POST', '/v1/policies/tos/versions/v2/retire', null, static fn (Client $c) => $c->policies->versions->retire('tos', 'v2')],
            ['GET', '/v1/policies/tos/acceptances?version=v2&status=all&limit=5', null, static fn (Client $c) => $c->policies->listAcceptances('tos', ['version' => 'v2', 'status' => 'all', 'limit' => 5])],
            ['GET', '/v1/users/user_1/policies', null, static fn (Client $c) => $c->policies->forUser('user_1')],
            ['GET', '/v1/instance/policy_settings', null, static fn (Client $c) => $c->policies->settings->get()],
            ['PATCH', '/v1/instance/policy_settings', ['session_claim' => true], static fn (Client $c) => $c->policies->settings->update(['session_claim' => true])],
            ['GET', '/v1/instance/policy_source', null, static fn (Client $c) => $c->policySource->get()],
            ['PUT', '/v1/instance/policy_source', ['enabled' => true, 'endpoint_url' => null], static fn (Client $c) => $c->policySource->update(['enabled' => true, 'endpoint_url' => null])],
            ['DELETE', '/v1/instance/policy_source', null, static fn (Client $c) => $c->policySource->delete()],
            ['POST', '/v1/instance/policy_source/test', null, static fn (Client $c) => $c->policySource->test()],
            ['POST', '/v1/instance/policy_source/sync', null, static fn (Client $c) => $c->policySource->sync()],
            ['GET', '/v1/instance/policy_source/deliveries?kind=fetch&status=failed&limit=2', null, static fn (Client $c) => $c->policySource->listDeliveries(['kind' => 'fetch', 'status' => 'failed', 'limit' => 2])],
            ['POST', '/v1/users/user_1/consents', ['policy' => 'tos', 'version' => 'v2', 'policy_version_id' => 'polv_1', 'content_sha256' => 'ab', 'display' => 'scroll', 'scrolled_to_end' => true, 'view_ms' => 1200, 'locale' => 'en'],
                static fn (Client $c) => $c->users->recordConsent('user_1', ['policy' => 'tos', 'version' => 'v2', 'policy_version_id' => 'polv_1', 'content_sha256' => 'ab', 'display' => 'scroll', 'scrolled_to_end' => true, 'view_ms' => 1200, 'locale' => 'en'])],
        ];
    }

    public function testEveryOperationHitsItsRoute(): void
    {
        $operations = self::operations();
        $this->assertCount(24, $operations);

        foreach ($operations as [$verb, $path, $body, $call]) {
            $mock = new MockClient();
            $mock->push(200, ['ok' => true]);
            $call(new Client('sk_test_123', 'https://api.atlas.test', $mock));

            $request = $mock->lastRequest();
            $this->assertSame($verb, $request->getMethod(), $path);
            $this->assertSame('https://api.atlas.test' . $path, (string) $request->getUri());
            $sent = (string) $request->getBody();
            if ($body === null) {
                $this->assertSame('', $sent, "{$verb} {$path} should send no body");
            } else {
                $this->assertSame($body === [] ? '{}' : json_encode($body), $sent, "{$verb} {$path} body");
            }
        }
    }

    public function testExportAcceptancesCsvReturnsTextAndDropsPaging(): void
    {
        $mock = new MockClient();
        $mock->push(200, "user_id,policy\nu_1,tos\n");
        $out = (new Client('sk_test_123', 'https://api.atlas.test', $mock))
            ->policies->exportAcceptancesCsv('tos', ['limit' => 9, 'status' => 'accepted']);
        $this->assertSame("user_id,policy\nu_1,tos\n", $out);
        $this->assertSame('https://api.atlas.test/v1/policies/tos/acceptances?status=accepted&format=csv', (string) $mock->lastRequest()->getUri());
    }

    public function testEventConstants(): void
    {
        $this->assertSame('user.policy_accepted', Constants::WEBHOOK_EVENT_USER_POLICY_ACCEPTED);
        $this->assertSame('policy.version_published', Constants::WEBHOOK_EVENT_POLICY_VERSION_PUBLISHED);
        $this->assertSame('2026-10-24', PolicySourceProtocol::VERSION);
    }

    /** @return array<string,string> */
    private static function headers(string $sig, string $id = 'evt_feed_1', string $ts = '1800000000000'): array
    {
        return ['Atlas-Id' => $id, 'Atlas-Timestamp' => $ts, 'Atlas-Signature' => $sig, 'Atlas-Policies-Version' => PolicySourceProtocol::VERSION];
    }

    public function testVerifyFeedRequestMatchesTheTypeScriptVectors(): void
    {
        $r = PolicySourceProtocol::verifyFeedRequest('GET', '/atlas/policies?key=tos', self::headers('v1,' . self::SIG_KEY), self::SECRET, self::NOW + 1000);
        $this->assertTrue($r->verified);
        $this->assertSame('tos', $r->key);
        $this->assertSame('evt_feed_1', $r->deliveryId);
        $this->assertSame(self::NOW, $r->timestamp);
        $this->assertSame('2026-10-24', $r->protocolVersion);

        $full = PolicySourceProtocol::verifyFeedRequest(null, '/atlas/policies', self::headers('v1,' . self::SIG_ALL, 'evt_feed_2'), self::SECRET, self::NOW);
        $this->assertTrue($full->verified);
        $this->assertNull($full->key);
    }

    public function testVerifyAcceptsAnyOfSeveralV1ValuesAndRackHeaders(): void
    {
        $multi = self::headers('v1,AAAA v2,zzzz v1,' . self::SIG_KEY);
        $this->assertTrue(PolicySourceProtocol::verifyFeedRequest('get', '/atlas/policies?key=tos', $multi, self::SECRET, self::NOW)->verified);
        $rack = [];
        foreach (self::headers('v1,' . self::SIG_KEY) as $k => $v) {
            $rack['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }
        $this->assertTrue(PolicySourceProtocol::verifyFeedRequest('GET', '/atlas/policies?key=tos', $rack, self::SECRET, self::NOW)->verified);
    }

    public function testVerifyRefusals(): void
    {
        $path = '/atlas/policies?key=tos';
        $good = self::headers('v1,' . self::SIG_KEY);
        $reason = static fn (?string $method, string $p, array $h, int $now = self::NOW, string $secret = self::SECRET) => PolicySourceProtocol::verifyFeedRequest($method, $p, $h, $secret, $now)->reason;

        $this->assertSame('missing_headers', $reason('GET', $path, []));
        $this->assertSame('malformed_signature', $reason('GET', $path, self::headers('v2,abc')));
        $this->assertSame('stale_timestamp', $reason('GET', $path, $good, self::NOW + 300_001));
        $this->assertSame('stale_timestamp', $reason('GET', $path, $good, self::NOW - 300_001));
        $this->assertSame('stale_timestamp', $reason('GET', $path, self::headers('v1,' . self::SIG_KEY, 'evt_feed_1', 'nope')));
        $this->assertSame('signature_mismatch', $reason('GET', '/atlas/policies?key=privacy', $good));
        $this->assertSame('signature_mismatch', $reason('GET', $path, $good, self::NOW, 'other'));
        $this->assertSame('signature_mismatch', $reason('GET', $path, self::headers('v1,!!!!')));
        $this->assertSame('wrong_method', $reason('POST', $path, $good));
        $this->assertTrue(PolicySourceProtocol::verifyFeedRequest('GET', $path, $good, self::SECRET, self::NOW + 300_000)->verified, 'the tolerance edge is inclusive');
    }

    public function testSignEventMatchesTheTypeScriptVector(): void
    {
        $signed = PolicySourceProtocol::signEvent(
            ['type' => 'policy.published', 'key' => 'tos', 'version' => '2026-10-24', 'occurred_at' => '2026-10-24T00:00:00.000Z'],
            self::SECRET,
            'evt_out_1',
            self::NOW + 123,
        );
        $this->assertSame('{"occurred_at":"2026-10-24T00:00:00.000Z","type":"policy.published","key":"tos","version":"2026-10-24"}', $signed->body);
        $this->assertSame(
            ['atlas-id' => 'evt_out_1', 'atlas-timestamp' => (string) (self::NOW + 123), 'atlas-signature' => 'v1,' . self::SIG_EVENT],
            $signed->headers,
        );
    }

    public function testSignEventDefaultsAndRoundTrip(): void
    {
        $signed = PolicySourceProtocol::signEvent(['type' => 'policy.retired', 'key' => 'tos'], self::SECRET);
        $body = json_decode($signed->body, true);
        $this->assertSame('policy.retired', $body['type']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT[\d:]+\.\d{3}Z$/', $body['occurred_at']);
        $this->assertStringStartsWith('evt_', $signed->headers['atlas-id']);
        // the same signature scheme Atlas verifies for any signed webhook
        $this->assertTrue(Webhook::verify($signed->body, $signed->headers, self::SECRET)->verified);
    }

    public function testPolClaimHelpers(): void
    {
        $ok = VerifyResult::ok(['sub' => 'u_1', 'pol' => ['tos' => '2026-10-24', 'privacy' => 3, 'bad' => true]]);
        $this->assertSame(['tos' => '2026-10-24', 'privacy' => '3'], $ok->policies());
        $this->assertSame('2026-10-24', $ok->acceptedPolicyVersion('tos'));
        $this->assertNull($ok->acceptedPolicyVersion('other'));
        $this->assertSame([], VerifyResult::ok(['sub' => 'u_1'])->policies());
        $this->assertSame([], VerifyResult::ok(['pol' => 'x'])->policies());
        $this->assertSame([], VerifyResult::fail('invalid')->policies());
    }
}
