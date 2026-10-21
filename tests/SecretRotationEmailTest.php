<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\ErrorCode;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/**
 * OAuth client secret rotation with overlap, the development email sender, managed email on your
 * own provider account, origin integrations and the new error codes.
 */
final class SecretRotationEmailTest extends TestCase
{
    private MockClient $mock;
    private Client $atlas;

    protected function setUp(): void
    {
        $this->mock = new MockClient();
        $this->atlas = new Client('sk_test_secret', 'https://api.atlas.test', $this->mock);
    }

    public function testRotateSecretSendsOverlapAndReturnsTheExpiry(): void
    {
        $this->mock->push(200, [
            'object' => 'oauth_client', 'id' => 'oc_1', 'client_secret' => 'cs_new',
            'overlap_seconds' => 0, 'previous_secret_expires_at' => null,
        ]);
        $out = $this->atlas->oauthClients->rotateSecret('oc_1', ['overlap_seconds' => 0], 'idem_1');

        $request = $this->mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/oauth_clients/oc_1/rotate_secret', (string) $request->getUri());
        $this->assertSame(['overlap_seconds' => 0], json_decode((string) $request->getBody(), true));
        $this->assertSame('idem_1', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame('cs_new', $out['client_secret']);
        $this->assertNull($out['previous_secret_expires_at']);

        $this->mock->push(200, ['object' => 'oauth_client', 'id' => 'oc_1', 'overlap_seconds' => 86400]);
        $this->atlas->oauthClients->rotateSecret('oc_1');
        $this->assertSame('', (string) $this->mock->lastRequest()->getBody());
    }

    public function testDevelopmentEmailGetEnableDisable(): void
    {
        $dev = [
            'object' => 'development_email', 'provider_id' => 'mp_1', 'enabled' => true, 'active' => true,
            'transport' => 'resend', 'from_address' => 'onboarding@resend.dev', 'subject_prefix' => '[Atlas test] ',
            'limits' => ['daily' => 100, 'per_recipient_hourly' => 10],
        ];
        $url = 'https://api.atlas.test/v1/messaging_providers/email/development';

        $this->mock->push(200, $dev);
        $this->assertSame(10, $this->atlas->messaging->getDevelopmentEmail()['limits']['per_recipient_hourly']);
        $this->assertSame('GET', $this->mock->lastRequest()->getMethod());
        $this->assertSame($url, (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(201, $dev);
        $this->atlas->messaging->enableDevelopmentEmail(['provider_id' => 'mp_1']);
        $this->assertSame('POST', $this->mock->lastRequest()->getMethod());
        $this->assertSame(['provider_id' => 'mp_1'], json_decode((string) $this->mock->lastRequest()->getBody(), true));

        $this->mock->push(200, ['object' => 'development_email', 'deleted' => true, 'provider_id' => 'mp_1', 'mode' => 'standard']);
        $this->assertSame('standard', $this->atlas->messaging->disableDevelopmentEmail()['mode']);
        $this->assertSame('DELETE', $this->mock->lastRequest()->getMethod());
        $this->assertSame($url, (string) $this->mock->lastRequest()->getUri());
    }

    public function testManagedEmailOnYourOwnAccountAndProviderMode(): void
    {
        $this->mock->push(201, [
            'object' => 'managed_email', 'provider_id' => 'mp_2', 'limits' => null,
            'reputation' => 'your_provider_account', 'feedback_webhook' => 'registered',
        ]);
        $out = $this->atlas->messaging->createManagedEmail([
            'from_address' => 'hi@acme.test', 'transport' => 'brevo', 'config' => ['api_key' => 'xkeysib-x'],
        ]);
        $this->assertSame(
            ['from_address' => 'hi@acme.test', 'transport' => 'brevo', 'config' => ['api_key' => 'xkeysib-x']],
            json_decode((string) $this->mock->lastRequest()->getBody(), true),
        );
        $this->assertNull($out['limits']);
        $this->assertSame('mp_2', $out['provider_id']);

        $this->mock->push(200, ['object' => 'messaging_provider', 'id' => 'mp_1', 'mode' => 'development']);
        $p = $this->atlas->messaging->setEmail(['transport' => 'resend', 'mode' => 'development', 'config' => ['api_key' => 're_x']]);
        $this->assertSame('development', json_decode((string) $this->mock->lastRequest()->getBody(), true)['mode']);
        $this->assertSame('mp_1', $p['id']);
    }

    public function testAllowedOriginsAcceptIntegrationObjects(): void
    {
        $this->mock->push(200, ['object' => 'instance', 'allowed_origin_integrations' => ['https://app.acme.test' => 'redirect']]);
        $out = $this->atlas->instance->update([
            'allowed_origins' => ['https://a.acme.test', ['origin' => 'https://app.acme.test', 'integration' => 'redirect']],
        ]);
        $this->assertSame(
            ['allowed_origins' => ['https://a.acme.test', ['origin' => 'https://app.acme.test', 'integration' => 'redirect']]],
            json_decode((string) $this->mock->lastRequest()->getBody(), true),
        );
        $this->assertSame('redirect', $out['allowed_origin_integrations']['https://app.acme.test']);
    }

    public function testNewErrorCodes(): void
    {
        $this->assertSame('READ_ONLY_FIELD', ErrorCode::READ_ONLY_FIELD);
        $this->assertSame('DEVELOPMENT_EMAIL_NOT_ALLOWED', ErrorCode::DEVELOPMENT_EMAIL_NOT_ALLOWED);
        $this->assertSame('DEVELOPMENT_EMAIL_UNAVAILABLE', ErrorCode::DEVELOPMENT_EMAIL_UNAVAILABLE);
        $this->assertSame('DEVELOPMENT_EMAIL_LIMIT_REACHED', ErrorCode::DEVELOPMENT_EMAIL_LIMIT_REACHED);
        $this->assertSame('DEVELOPMENT_EMAIL_TEMPLATE_NOT_ALLOWED', ErrorCode::DEVELOPMENT_EMAIL_TEMPLATE_NOT_ALLOWED);
    }
}
