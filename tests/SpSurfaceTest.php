<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\ErrorCode;
use Atlas\Exception\PermissionException;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/**
 * The integrator-platform surface (0.17.0): secret keys, whoami, partner links, privacy executor, integrations, notices,
 * advisories, audit exports, status, webhook health/replay, and the options (dry run, traceparent) and error codes around them.
 */
final class SpSurfaceTest extends TestCase
{
    private const BASE = 'https://api.atlas.test';

    /**
     * @return list<array{0:string,1:string,2:array<string,mixed>|null,3:bool,4:\Closure(Client):mixed}>
     */
    private static function operations(): array
    {
        return [
            ['GET', '/v1/whoami', null, false, static fn (Client $c) => $c->whoami->get()],
            ['POST', '/v1/secret_keys', json_decode('{"name":"ci"}', true), true, static fn (Client $c) => $c->secretKeys->create(['name' => 'ci'], 'idem_1')],
            ['GET', '/v1/secret_keys?limit=5', null, false, static fn (Client $c) => $c->secretKeys->list(['limit' => 5])],
            ['GET', '/v1/secret_keys/sk_1', null, false, static fn (Client $c) => $c->secretKeys->get('sk_1')],
            ['PATCH', '/v1/secret_keys/sk_1', json_decode('{"name":"n"}', true), false, static fn (Client $c) => $c->secretKeys->update('sk_1', ['name' => 'n'])],
            ['POST', '/v1/secret_keys/sk_1/rotate', json_decode('{"overlap_seconds":60}', true), true, static fn (Client $c) => $c->secretKeys->rotate('sk_1', ['overlap_seconds' => 60], 'idem_1')],
            ['POST', '/v1/secret_keys/sk_1/revoke', null, false, static fn (Client $c) => $c->secretKeys->revoke('sk_1')],
            ['GET', '/v1/keys', null, false, static fn (Client $c) => $c->keys->list()],
            ['POST', '/v1/keys/revoke_leaked', json_decode('{"secrets":["a"]}', true), false, static fn (Client $c) => $c->keys->revokeLeaked(['secrets' => ['a']])],
            ['POST', '/v1/partner_links', json_decode('{"partner":"p","scopes":["x"]}', true), true, static fn (Client $c) => $c->partnerLinks->create(['partner' => 'p', 'scopes' => ['x']], 'idem_1')],
            ['GET', '/v1/partner_links', null, false, static fn (Client $c) => $c->partnerLinks->list()],
            ['GET', '/v1/partner_links/pl_1', null, false, static fn (Client $c) => $c->partnerLinks->get('pl_1')],
            ['POST', '/v1/partner_links/pl_1/rotate_secret', null, false, static fn (Client $c) => $c->partnerLinks->rotateSecret('pl_1')],
            ['DELETE', '/v1/partner_links/pl_1', null, false, static fn (Client $c) => $c->partnerLinks->revoke('pl_1')],
            ['GET', '/v1/partner_links/outbound', null, false, static fn (Client $c) => $c->partnerLinks->listOutbound()],
            ['PUT', '/v1/partner_links/outbound/cookiemunch', json_decode('{"url":"u"}', true), false, static fn (Client $c) => $c->partnerLinks->saveOutbound('cookiemunch', ['url' => 'u'])],
            ['POST', '/v1/partner_links/outbound/cookiemunch/test', null, false, static fn (Client $c) => $c->partnerLinks->testOutbound('cookiemunch')],
            ['DELETE', '/v1/partner_links/outbound/cookiemunch', null, false, static fn (Client $c) => $c->partnerLinks->removeOutbound('cookiemunch')],
            ['GET', '/v1/atlas_id_apps', null, false, static fn (Client $c) => $c->atlasIdGrants->listApps()],
            ['GET', '/v1/atlas_id_apps/app_1/users?email=a', null, false, static fn (Client $c) => $c->atlasIdGrants->lookup('app_1', 'a')],
            ['DELETE', '/v1/atlas_id_apps/app_1/grants/g_1', null, false, static fn (Client $c) => $c->atlasIdGrants->revoke('app_1', 'g_1')],
            ['GET', '/v1/privacy', null, false, static fn (Client $c) => $c->privacy->discover()],
            ['GET', '/v1/privacy/presets/cookiemunch', null, false, static fn (Client $c) => $c->privacy->preset('cookiemunch')],
            ['POST', '/v1/privacy/lookup', json_decode('{"emails":["a@b.c"]}', true), false, static fn (Client $c) => $c->privacy->lookup(['emails' => ['a@b.c']])],
            ['POST', '/v1/privacy/requests', json_decode('{"subjectId":"u","type":"deletion","caseId":"c"}', true), true, static fn (Client $c) => $c->privacy->createRequest(['subjectId' => 'u', 'type' => 'deletion', 'caseId' => 'c'], 'idem_1')],
            ['GET', '/v1/privacy/requests/pr_1', null, false, static fn (Client $c) => $c->privacy->getRequest('pr_1')],
            ['POST', '/v1/privacy/requests/pr_1/cancel', null, true, static fn (Client $c) => $c->privacy->cancelRequest('pr_1', 'idem_1')],
            ['GET', '/v1/instance/privacy_orchestrator', null, false, static fn (Client $c) => $c->privacy->getOrchestrator()],
            ['PUT', '/v1/instance/privacy_orchestrator', json_decode('{"provider":"custom"}', true), false, static fn (Client $c) => $c->privacy->setOrchestrator(['provider' => 'custom'])],
            ['DELETE', '/v1/instance/privacy_orchestrator', null, false, static fn (Client $c) => $c->privacy->deleteOrchestrator()],
            ['GET', '/v1/entitlements?user_id=u', null, false, static fn (Client $c) => $c->entitlements->list(['user_id' => 'u'])],
            ['GET', '/v1/instance/notices?all=true', null, false, static fn (Client $c) => $c->notices->list(['all' => true])],
            ['POST', '/v1/instance/notices/n_1/acknowledge', null, false, static fn (Client $c) => $c->notices->acknowledge('n_1')],
            ['GET', '/v1/security_advisories', null, false, static fn (Client $c) => $c->securityAdvisories->list()],
            ['POST', '/v1/security_advisories/a_1/acknowledge', null, false, static fn (Client $c) => $c->securityAdvisories->acknowledge('a_1')],
            ['GET', '/v1/public/security_advisories', null, false, static fn (Client $c) => $c->securityAdvisories->catalog()],
            ['GET', '/v1/instance/sub_processors', null, false, static fn (Client $c) => $c->subProcessors->get()],
            ['GET', '/v1/audit_exports', null, false, static fn (Client $c) => $c->auditExports->list()],
            ['GET', '/v1/audit_exports/ae_1', null, false, static fn (Client $c) => $c->auditExports->get('ae_1')],
            ['POST', '/v1/audit_exports', json_decode('{"url":"u"}', true), true, static fn (Client $c) => $c->auditExports->create(['url' => 'u'], 'idem_1')],
            ['PATCH', '/v1/audit_exports/ae_1', json_decode('{"enabled":false}', true), false, static fn (Client $c) => $c->auditExports->update('ae_1', ['enabled' => false])],
            ['DELETE', '/v1/audit_exports/ae_1', null, false, static fn (Client $c) => $c->auditExports->delete('ae_1')],
            ['POST', '/v1/audit_exports/ae_1/test', null, false, static fn (Client $c) => $c->auditExports->test('ae_1')],
            ['POST', '/v1/audit_exports/ae_1/rotate_secret', null, false, static fn (Client $c) => $c->auditExports->rotateSecret('ae_1')],
            ['GET', '/v1/status', null, false, static fn (Client $c) => $c->status->service()],
            ['GET', '/v1/instance/status_subscription', null, false, static fn (Client $c) => $c->status->getSubscription()],
            ['PUT', '/v1/instance/status_subscription', json_decode('{"enabled":true}', true), false, static fn (Client $c) => $c->status->updateSubscription(['enabled' => true])],
            ['DELETE', '/v1/instance/status_subscription', null, false, static fn (Client $c) => $c->status->disableSubscription()],
            ['POST', '/v1/instance/status_subscription/rotate_feed', null, false, static fn (Client $c) => $c->status->rotateFeed()],
            ['GET', '/v1/instance/status_incidents?open=true&limit=5', null, false, static fn (Client $c) => $c->status->listIncidents(['open' => true, 'limit' => 5])],
            ['GET', '/v1/instance/status_dependencies', null, false, static fn (Client $c) => $c->status->listDependencies()],
            ['POST', '/v1/instance/status_dependencies', json_decode('{"url":"u"}', true), false, static fn (Client $c) => $c->status->createDependency(['url' => 'u'])],
            ['GET', '/v1/instance/status_dependencies/d_1', null, false, static fn (Client $c) => $c->status->getDependency('d_1')],
            ['PATCH', '/v1/instance/status_dependencies/d_1', json_decode('{"name":"n"}', true), false, static fn (Client $c) => $c->status->updateDependency('d_1', ['name' => 'n'])],
            ['DELETE', '/v1/instance/status_dependencies/d_1', null, false, static fn (Client $c) => $c->status->deleteDependency('d_1')],
            ['POST', '/v1/instance/status_dependencies/d_1/refresh', null, false, static fn (Client $c) => $c->status->refreshDependency('d_1')],
            ['GET', '/v1/instance/integrations', null, false, static fn (Client $c) => $c->integrations->list()],
            ['GET', '/v1/instance/integrations/chatly', null, false, static fn (Client $c) => $c->integrations->get('chatly')],
            ['PUT', '/v1/instance/integrations/chatly?dry_run=true', json_decode('{"enabled":true}', true), false, static fn (Client $c) => $c->integrations->set('chatly', ['enabled' => true], ['dry_run' => true])],
            ['DELETE', '/v1/instance/integrations/chatly', null, false, static fn (Client $c) => $c->integrations->remove('chatly')],
            ['GET', '/v1/instance/integrations/chatly/deliveries?trace=t', null, false, static fn (Client $c) => $c->integrations->deliveries('chatly', ['trace' => 't'])],
            ['POST', '/v1/instance/integrations/chatly/events_secret', null, false, static fn (Client $c) => $c->integrations->revealEventsSecret('chatly')],
            ['POST', '/v1/instance/integrations/chatly/atlas_id_tokens', json_decode('{"access_token":"t"}', true), false, static fn (Client $c) => $c->integrations->atlasIdToken('chatly', 't')],
            ['POST', '/v1/integrations/verify_errors', json_decode('{"integration":"chatly"}', true), false, static fn (Client $c) => $c->integrations->reportVerifyError(['integration' => 'chatly'])],
            ['GET', '/v1/integrations/chatly/card?sub=u', null, false, static fn (Client $c) => $c->integrations->card('chatly', ['sub' => 'u'])],
            ['POST', '/v1/integrations/chatly/card/actions/revoke_sessions', json_decode('{"sub":"u"}', true), false, static fn (Client $c) => $c->integrations->revokeCardSessions('chatly', ['sub' => 'u'])],
            ['GET', '/v1/public/instances/ins_1', null, false, static fn (Client $c) => $c->integrations->instanceMetadata('ins_1')],
            ['POST', '/v1/sessions/s_1/integration_tokens/chatly', null, false, static fn (Client $c) => $c->sessions->createIntegrationToken('s_1', 'chatly')],
            ['POST', '/v1/sessions/s_1/integration_tokens/chatly', null, false, static fn (Client $c) => $c->sessions->getToken('s_1', ['for' => 'chatly'])],
            ['POST', '/v1/sessions/s_1/tokens/tpl', null, false, static fn (Client $c) => $c->sessions->getToken('s_1', 'tpl')],
            ['GET', '/v1/webhook_endpoints?include_deprecated=true', null, false, static fn (Client $c) => $c->webhooks->endpoints->list(['include_deprecated' => true])],
            ['GET', '/v1/webhook_endpoints/we_1', null, false, static fn (Client $c) => $c->webhooks->endpoints->get('we_1')],
            ['PUT', '/v1/webhook_endpoints/by_url', json_decode('{"url":"https://x.test/h"}', true), true, static fn (Client $c) => $c->webhooks->endpoints->upsertByUrl(['url' => 'https://x.test/h'], 'idem_1')],
            ['POST', '/v1/webhook_endpoints/we_1/test?wait=5s', null, false, static fn (Client $c) => $c->webhooks->endpoints->testNow('we_1', '5s')],
            ['POST', '/v1/webhook_endpoints/we_1/redeliver', json_decode('{"status":"failed"}', true), true, static fn (Client $c) => $c->webhooks->endpoints->redeliverWindow('we_1', ['status' => 'failed'], 'idem_1')],
            ['POST', '/v1/webhook_endpoints/we_1/replay', json_decode('{"since":1}', true), true, static fn (Client $c) => $c->webhooks->endpoints->replay('we_1', ['since' => 1], 'idem_1')],
            ['GET', '/v1/webhook_endpoints/we_1/deliveries?status=failed&starting_after=d', null, false, static fn (Client $c) => $c->webhooks->deliveries('we_1', ['status' => 'failed', 'starting_after' => 'd'])],
            ['GET', '/v1/meta/egress_ips', null, false, static fn (Client $c) => $c->webhooks->egressIps()],
            ['GET', '/v1/delivery_health?state=unhealthy', null, false, static fn (Client $c) => $c->webhooks->health(['state' => 'unhealthy'])],
            ['PUT', '/v1/delivery_health/webhook/we_1/alert_contacts', json_decode('{"alert_contacts":["a@b.c"]}', true), false, static fn (Client $c) => $c->webhooks->setAlertContacts('webhook', 'we_1', ['a@b.c'])],
            ['GET', '/v1/events?since=1&order=asc', null, false, static fn (Client $c) => $c->events->list(['since' => 1, 'order' => 'asc'])],
            ['GET', '/v1/users?updated_after=1&order_by=-updated_at', null, false, static fn (Client $c) => $c->users->list(['updated_after' => 1, 'order_by' => '-updated_at'])],
            ['GET', '/v1/instance/preflight?min_severity=warning&include_suppressed=true', null, false, static fn (Client $c) => $c->instance->preflight(['min_severity' => 'warning', 'include_suppressed' => true])],
            ['GET', '/v1/instance/unknown_fields_report?days=7', null, false, static fn (Client $c) => $c->instance->unknownFieldsReport(['days' => 7])],
            ['GET', '/v1/openapi/diff?since=2026-10-01&surface=bapi', null, false, static fn (Client $c) => $c->instance->openapiDiff('2026-10-01', 'bapi')],
            ['GET', '/v1/integration-health?only=email%2Cwebhooks&probe=false', null, false, static fn (Client $c) => $c->instance->integrationHealth(['email', 'webhooks'], false)],
            ['GET', '/v1/integration-health/providers', null, false, static fn (Client $c) => $c->instance->integrationHealthProviders()],
            ['GET', '/v1/embed?instance=ins_1&shape=button', null, false, static fn (Client $c) => $c->instance->embed('ins_1', null, 'button')],
            ['GET', '/v1/messaging_providers/email', null, false, static fn (Client $c) => $c->messaging->getChannel('email')],
            ['POST', '/v1/instance/referrals/referalli/install', json_decode('{"return_to":"/x"}', true), true, static fn (Client $c) => $c->referrals->install('referalli', ['return_to' => '/x'], 'idem_1')],
            ['GET', '/v1/platform/whoami', null, false, static fn (Client $c) => $c->platform->whoami()],
            ['GET', '/v1/platform/keys?limit=5', null, false, static fn (Client $c) => $c->platform->listKeys(['limit' => 5])],
            ['POST', '/v1/platform/keys', json_decode('{"name":"n"}', true), true, static fn (Client $c) => $c->platform->createKey(['name' => 'n'], 'idem_1')],
            ['PATCH', '/v1/platform/keys/k_1', json_decode('{"name":"n"}', true), false, static fn (Client $c) => $c->platform->updateKey('k_1', ['name' => 'n'])],
            ['POST', '/v1/platform/keys/k_1/revoke', null, false, static fn (Client $c) => $c->platform->revokeKey('k_1')],
            ['GET', '/v1/platform/audit_logs?limit=5', null, false, static fn (Client $c) => $c->platform->auditLogs(['limit' => 5])],
            ['GET', '/v1/platform/audit_streams', null, false, static fn (Client $c) => $c->platform->listAuditStreams()],
            ['POST', '/v1/platform/audit_streams', json_decode('{"url":"u"}', true), false, static fn (Client $c) => $c->platform->createAuditStream(['url' => 'u'])],
            ['PATCH', '/v1/platform/audit_streams/s_1', json_decode('{"enabled":false}', true), false, static fn (Client $c) => $c->platform->updateAuditStream('s_1', ['enabled' => false])],
            ['DELETE', '/v1/platform/audit_streams/s_1', null, false, static fn (Client $c) => $c->platform->deleteAuditStream('s_1')],
            ['POST', '/v1/platform/audit_streams/s_1/test', null, false, static fn (Client $c) => $c->platform->testAuditStream('s_1')],
            ['POST', '/v1/platform/apps/app_1/clone_config', json_decode('{"target_app_id":"t"}', true), true, static fn (Client $c) => $c->platform->apps->cloneConfig('app_1', ['target_app_id' => 't'], 'idem_1')],
            ['POST', '/v1/platform/apps/app_1/secret_keys', json_decode('{"name":"n"}', true), true, static fn (Client $c) => $c->platform->apps->createSecretKey('app_1', ['name' => 'n'], 'idem_1')],
            ['POST', '/v1/platform/apps/bulk_delete?dry_run=true', json_decode('{"metadata":{"pr":"1"}}', true), false, static fn (Client $c) => $c->platform->apps->bulkDeleteByMetadata(['pr' => '1'], true)],
        ];
    }

    public function testEveryOperationHitsItsRoute(): void
    {
        $operations = self::operations();
        $this->assertCount(104, $operations);

        foreach ($operations as [$verb, $path, $body, $idem, $call]) {
            $mock = new MockClient();
            $mock->push(200, ['ok' => true]);
            $call(new Client('atlas_live_sk_123', self::BASE, $mock));

            $request = $mock->lastRequest();
            $this->assertSame($verb, $request->getMethod(), $path);
            $this->assertSame(self::BASE . $path, (string) $request->getUri());
            $this->assertSame('Bearer atlas_live_sk_123', $request->getHeaderLine('Authorization'));
            $sent = (string) $request->getBody();
            if ($body === null) {
                $this->assertSame('', $sent, "{$verb} {$path} should send no body");
            } else {
                $this->assertSame($body, json_decode($sent, true), "{$verb} {$path} body");
            }
            $this->assertSame($idem ? 'idem_1' : '', $request->getHeaderLine('Idempotency-Key'), "{$verb} {$path} idempotency");
        }
    }

    public function testDryRunAddsTheQueryToWritesOnlyAndDropsTheIdempotencyKey(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['object' => 'dry_run', 'dry_run' => true]);
        $mock->push(200, ['data' => []]);
        $dry = (new Client('sk_test_1', self::BASE, $mock))->withOptions(dryRun: true);

        $dry->webhooks->endpoints->create(['url' => 'https://x.test/h'], 'idem_1');
        $write = $mock->requests[0];
        $this->assertSame(self::BASE . '/v1/webhook_endpoints?dry_run=true', (string) $write->getUri());
        $this->assertSame('', $write->getHeaderLine('Idempotency-Key'));

        $dry->users->list();
        $this->assertSame(self::BASE . '/v1/users', (string) $mock->requests[1]->getUri());
    }

    public function testTraceparentIsSentOnEveryRequestAndOptionsCompose(): void
    {
        $mock = new MockClient();
        $mock->push(200, []);
        $mock->push(200, []);
        $tp = \Atlas\Traceparent::generate();
        $client = (new Client('sk_test_1', self::BASE, $mock))->withStrict(true)->withOptions(traceparent: $tp);
        $client->whoami->get();
        $this->assertSame($tp, $mock->lastRequest()->getHeaderLine('traceparent'));
        $client->withOptions(dryRun: true)->keys->revokeLeaked(['secrets' => ['a']]);
        $this->assertSame($tp, $mock->lastRequest()->getHeaderLine('traceparent'));
        $this->assertStringContainsString('dry_run=true', (string) $mock->lastRequest()->getUri());
        $this->assertSame('true', $mock->lastRequest()->getHeaderLine('Atlas-Strict'));
    }

    public function testTraceparentHelpers(): void
    {
        $tp = \Atlas\Traceparent::generate();
        $this->assertMatchesRegularExpression('/^00-[0-9a-f]{32}-[0-9a-f]{16}-01$/', $tp);
        $parsed = \Atlas\Traceparent::parse($tp);
        $this->assertNotNull($parsed);
        $child = \Atlas\Traceparent::child($tp);
        $this->assertSame($parsed['trace_id'], \Atlas\Traceparent::traceId($child));
        $this->assertNotSame($tp, $child);
        $this->assertNull(\Atlas\Traceparent::parse('00-' . str_repeat('0', 32) . '-' . str_repeat('1', 16) . '-01'));
        $this->assertNull(\Atlas\Traceparent::parse('garbage'));
        $this->assertNotNull(\Atlas\Traceparent::child(null));
    }

    public function testInsufficientScopeAndRouteNotFoundErrors(): void
    {
        $mock = new MockClient();
        $mock->push(403, ['errors' => [['code' => 'INSUFFICIENT_SCOPE', 'message' => 'm', 'meta' => ['required_scope' => 'users:write']]]]);
        $atlas = new Client('sk_test_1', self::BASE, $mock);
        try {
            $atlas->users->ban('u');
            $this->fail('expected a refusal');
        } catch (PermissionException $e) {
            $this->assertTrue($e->hasCode(ErrorCode::INSUFFICIENT_SCOPE));
            $this->assertTrue($e->hasCode(ErrorCode::SCOPE_MISSING), 'the legacy spelling matches too');
            $this->assertSame('users:write', $e->getRequiredScope());
            $this->assertTrue(ErrorCode::isScopeError($e->getErrorCode()));
        }

        $mock->push(404, ['errors' => [[
            'code' => 'ROUTE_NOT_FOUND', 'message' => 'no', 'did_you_mean' => ['/v1/secret_keys'], 'allowed_methods' => ['GET'],
            'docs_url' => 'https://atlasauth.net/errors/ROUTE_NOT_FOUND',
        ]]]);
        try {
            $atlas->http->request('GET', '/v1/secretkeys');
            $this->fail('expected a 404');
        } catch (\Atlas\Exception\NotFoundException $e) {
            $this->assertSame(['/v1/secret_keys'], $e->getDidYouMean());
            $this->assertSame(['GET'], $e->getErrors()[0]->allowedMethods);
            $this->assertSame('https://atlasauth.net/errors/ROUTE_NOT_FOUND', $e->getErrors()[0]->docsUrl);
        }

        $mock->push(403, ['errors' => [['code' => 'SCOPE_MISSING', 'message' => 'old server']]]);
        try {
            $atlas->users->ban('u');
        } catch (PermissionException $e) {
            $this->assertTrue($e->hasCode('INSUFFICIENT_SCOPE'));
        }
        $this->assertSame('STEP_UP_REQUIRED', ErrorCode::STEP_UP_REQUIRED);
    }

    public function testKeyFormatAcceptsBothFormats(): void
    {
        foreach (['atlas_live_sk_AbC123', 'atlas_test_sk_x-y_z', 'sk_live_abc', 'sk_test_abc', 'sk_abc'] as $k) {
            $this->assertTrue(\Atlas\KeyFormat::isSecretKey($k), $k);
        }
        foreach (['atlas_live_pk_abc', 'pk_test_abc'] as $k) {
            $this->assertTrue(\Atlas\KeyFormat::isPublishableKey($k), $k);
        }
        foreach (['atlas_live_psk_abc', 'psk_live_abc'] as $k) {
            $this->assertTrue(\Atlas\KeyFormat::isPlatformKey($k), $k);
        }
        $this->assertTrue(\Atlas\KeyFormat::isWebhookSecret('whsec_abc123'));
        $this->assertTrue(\Atlas\KeyFormat::isWebhookSecret('atlas_live_whsec_abc123'));
        $this->assertSame('atlas', \Atlas\KeyFormat::parse('atlas_test_sk_abc')['format']);
        $this->assertSame('test', \Atlas\KeyFormat::parse('atlas_test_sk_abc')['env']);
        $this->assertNull(\Atlas\KeyFormat::parse('nope'));
        $this->assertFalse(\Atlas\KeyFormat::isSecretKey('atlas_live_pk_abc'));
    }

    public function testWebhookEventNames(): void
    {
        $this->assertSame('organization_membership.created', \Atlas\WebhookEvents::canonical('organizationMembership.created'));
        $this->assertSame('session.revoked', \Atlas\WebhookEvents::canonical('session.ended'));
        $this->assertSame('user.created', \Atlas\WebhookEvents::canonical('user.created'));
        $this->assertTrue(\Atlas\WebhookEvents::isDeprecated('appRole.created'));
        $this->assertSame(['session.revoked', 'session.ended', 'session.removed'], \Atlas\WebhookEvents::spellings('session.removed'));
        $this->assertTrue(\Atlas\WebhookEvents::matches('billing.trial.ended', 'billing_trial.ended'));
        $this->assertSame('secret_key.expiring', \Atlas\WebhookEvents::SECRET_KEY_EXPIRING);
        $this->assertSame('status.incident.resolved', \Atlas\WebhookEvents::STATUS_INCIDENT_RESOLVED);
    }

    public function testSealedBoxRoundTrip(): void
    {
        if (!function_exists('sodium_crypto_box_seal')) {
            $this->markTestSkipped('ext-sodium missing');
        }
        $pair = \Atlas\Auth\SealedBox::generateKeyPair();
        $this->assertStringStartsWith('x25519:', $pair['secretRecipient']);
        $sealed = \Atlas\Auth\SealedBox::seal('atlas_live_sk_secret', $pair['secretRecipient']);
        $this->assertSame('atlas_live_sk_secret', \Atlas\Auth\SealedBox::open($sealed, $pair['publicKey'], $pair['secretKey']));
        $other = \Atlas\Auth\SealedBox::generateKeyPair();
        $this->expectException(\InvalidArgumentException::class);
        \Atlas\Auth\SealedBox::open($sealed, $other['publicKey'], $other['secretKey']);
    }
}
