<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\ErrorCode;
use Atlas\Exception\BadRequestException;
use Atlas\Resources\Design;
use Atlas\Resources\Instance;
use Atlas\Resources\Organizations;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/**
 * Sign in with Atlas apps, design (incl. raw image uploads), preflight, cookie
 * consent (incl. the settings link), OAuth clients, OAuth provider filters,
 * webhooks, entitlement overrides and org policy — request shape and response
 * decoding.
 */
final class PlatformDesignTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR";
    private const JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF";

    private MockClient $mock;
    private Client $atlas;
    private Client $platformClient;

    protected function setUp(): void
    {
        $this->mock = new MockClient();
        $this->atlas = new Client('sk_test_123', 'https://api.atlas.test', $this->mock);
        $this->platformClient = new Client('psk_test_1', 'https://api.atlas.test', $this->mock);
    }

    public function testAtlasIdAppCreateSendsLogoutAndBrandingFields(): void
    {
        $this->mock->push(200, ['object' => 'atlas_id_app', 'id' => 'aia_1', 'client_id' => 'cid', 'client_secret' => 's']);

        $app = $this->platformClient->platform->atlasIdApps->create([
            'name' => 'Acme',
            'redirect_uris' => ['https://acme.com/cb'],
            'post_logout_redirect_uris' => ['https://acme.com/bye'],
            'backchannel_logout_uri' => 'https://acme.com/bcl',
            'backchannel_logout_session_required' => true,
            'frontchannel_logout_uri' => 'https://acme.com/fcl',
            'frontchannel_logout_session_required' => false,
            'branding' => ['theme' => 'dark', 'copy' => ['sign_in_title' => 'Hi {app}'], 'design' => ['preset' => 'glass']],
        ], 'k1');

        $request = $this->mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/platform/atlas_id_apps', (string) $request->getUri());
        $this->assertSame('Bearer psk_test_1', $request->getHeaderLine('Authorization'));
        $this->assertSame('k1', $request->getHeaderLine('Idempotency-Key'));
        $sent = json_decode((string) $request->getBody(), true);
        $this->assertSame('https://acme.com/bcl', $sent['backchannel_logout_uri']);
        $this->assertTrue($sent['backchannel_logout_session_required']);
        $this->assertSame(['https://acme.com/bye'], $sent['post_logout_redirect_uris']);
        $this->assertSame('Hi {app}', $sent['branding']['copy']['sign_in_title']);
        $this->assertSame('s', $app['client_secret']);
    }

    public function testAtlasIdAppUpdateRotateAndPreview(): void
    {
        $apps = $this->platformClient->platform->atlasIdApps;

        $this->mock->push(200, ['id' => 'aia_1']);
        $apps->update('aia_1', ['branding' => ['accent_color' => null]]);
        $this->assertSame('PATCH', $this->mock->lastRequest()->getMethod());
        $this->assertSame('{"branding":{"accent_color":null}}', (string) $this->mock->lastRequest()->getBody());

        $this->mock->push(200, ['client_secret' => 'new']);
        $this->assertSame('new', $apps->rotateSecret('aia_1')['client_secret']);
        $this->assertSame('https://api.atlas.test/v1/platform/atlas_id_apps/aia_1/rotate_secret', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['object' => 'design_preview', 'css' => ':root{}']);
        $preview = $apps->previewDesign(['preset' => 'midnight']);
        $this->assertSame('{"design":{"preset":"midnight"}}', (string) $this->mock->lastRequest()->getBody());
        $this->assertSame(':root{}', $preview['css']);
    }

    public function testDesignUploadSendsRawBytesWithSniffedContentType(): void
    {
        $this->mock->push(201, ['object' => 'design_asset', 'asset_id' => 'dasset_1', 'kind' => 'background']);

        $asset = $this->atlas->design->uploadAsset(self::PNG, 'background');

        $request = $this->mock->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/design/assets?kind=background', (string) $request->getUri());
        $this->assertSame('image/png', $request->getHeaderLine('Content-Type'));
        $this->assertSame(self::PNG, (string) $request->getBody());
        $this->assertSame('dasset_1', $asset['asset_id']);
    }

    public function testUploadHonoursAnExplicitContentTypeAndThePlatformPath(): void
    {
        $this->mock->push(201, ['asset_id' => 'dasset_2']);
        $this->platformClient->platform->designAssets->upload(self::JPEG, 'logo', 'image/jpeg');
        $this->assertSame('https://api.atlas.test/v1/platform/design/assets?kind=logo', (string) $this->mock->lastRequest()->getUri());
        $this->assertSame('image/jpeg', $this->mock->lastRequest()->getHeaderLine('Content-Type'));

        $this->mock->push(201, ['asset_id' => 'dasset_3']);
        $this->atlas->design->uploadAsset('GIF89a....');
        $this->assertSame('image/gif', $this->mock->lastRequest()->getHeaderLine('Content-Type'));
        $this->assertSame('https://api.atlas.test/v1/design/assets', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(201, ['asset_id' => 'dasset_4']);
        $this->atlas->design->uploadAsset("RIFF\x00\x00\x00\x00WEBPVP8 ");
        $this->assertSame('image/webp', $this->mock->lastRequest()->getHeaderLine('Content-Type'));
    }

    public function testUploadRefusesUnknownBytesWithoutAContentType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        try {
            $this->atlas->design->uploadAsset('not an image');
        } finally {
            $this->assertSame(0, $this->mock->requestCount());
        }
    }

    public function testDesignUpdateWrapsTheDesignAndEmptyDesignIsAnObject(): void
    {
        $this->mock->push(200, ['object' => 'design', 'design' => ['preset' => 'glass'], 'warnings' => []]);
        $result = $this->atlas->design->update(['preset' => 'glass', 'palette' => ['primary' => '#112233']]);
        $this->assertSame('PATCH', $this->mock->lastRequest()->getMethod());
        $this->assertSame('https://api.atlas.test/v1/instance/design', (string) $this->mock->lastRequest()->getUri());
        $this->assertSame('{"design":{"preset":"glass","palette":{"primary":"#112233"}}}', (string) $this->mock->lastRequest()->getBody());
        $this->assertSame('glass', $result['design']['preset']);

        $this->mock->push(200, ['object' => 'organization_design']);
        $this->atlas->organizations->replaceDesign('org_1', []);
        $this->assertSame('PUT', $this->mock->lastRequest()->getMethod());
        $this->assertSame('{"design":{}}', (string) $this->mock->lastRequest()->getBody());
        $this->assertContains('backgroundDark', Design::SECTIONS);
    }

    public function testPreflightDecodesTypedFindings(): void
    {
        $this->mock->push(200, [
            'object' => 'preflight', 'ok' => false, 'checked_at' => 1,
            'findings' => [[
                'severity' => 'error', 'code' => 'OAUTH_PROVIDER_NO_CREDENTIALS',
                'message' => 'Google is on without credentials.', 'fix' => 'Add them.',
                'subject' => ['provider' => 'google'],
            ]],
        ]);

        $report = $this->atlas->instance->preflight();

        $this->assertSame('https://api.atlas.test/v1/instance/preflight', (string) $this->mock->lastRequest()->getUri());
        $this->assertFalse($report['ok']);
        $finding = $report['findings'][0];
        $this->assertSame('error', $finding['severity']);
        $this->assertSame('Add them.', $finding['fix']);
        $this->assertSame('google', $finding['subject']['provider']);
    }

    public function testCookieConsentRoundTrip(): void
    {
        $this->mock->push(200, ['object' => 'cookie_consent', 'enabled' => true, 'script_url' => 'https://cmp.example/s.js']);
        $this->atlas->instance->setCookieConsent(['script_url' => 'https://cmp.example/s.js', 'extra_origins' => ['https://cdn.cmp.example']]);
        $this->assertSame('PUT', $this->mock->lastRequest()->getMethod());
        $this->assertSame('https://api.atlas.test/v1/instance/cookie_consent', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['enabled' => false]);
        $this->assertFalse($this->atlas->instance->deleteCookieConsent()['enabled']);
        $this->assertSame('DELETE', $this->mock->lastRequest()->getMethod());
    }

    public function testCookieConsentSettingsLinkRoundTrip(): void
    {
        $this->mock->push(200, [
            'object' => 'cookie_consent', 'enabled' => true, 'script_url' => 'https://cmp.example/s.js',
            'settings_link' => [
                'label' => 'Cookie settings', 'preset' => 'cookiebot', 'attributes' => ['class' => 'footer-link'],
                'rendered_attributes' => ['class' => 'footer-link', 'href' => 'javascript:Cookiebot.renew()'],
            ],
        ]);

        $consent = $this->atlas->instance->setCookieConsent([
            'script_url' => 'https://cmp.example/s.js',
            'settings_link' => [
                'label' => 'Cookie settings',
                'preset' => Instance::COOKIE_SETTINGS_LINK_PRESET_COOKIEBOT,
                'attributes' => ['class' => 'footer-link'],
            ],
        ]);

        $this->assertSame([
            'script_url' => 'https://cmp.example/s.js',
            'settings_link' => ['label' => 'Cookie settings', 'preset' => 'cookiebot', 'attributes' => ['class' => 'footer-link']],
        ], json_decode((string) $this->mock->lastRequest()->getBody(), true));
        $link = $consent['settings_link'];
        $this->assertNotNull($link);
        $this->assertSame('Cookie settings', $link['label']);
        $this->assertSame('cookiebot', $link['preset']);
        $this->assertSame(['class' => 'footer-link'], $link['attributes']);
        $this->assertSame('footer-link', $link['rendered_attributes']['class']);
        $this->assertArrayHasKey('href', $link['rendered_attributes']);
    }

    public function testCookieConsentSettingsLinkNullClearsAndUnknownPresetDecodes(): void
    {
        $this->mock->push(200, ['object' => 'cookie_consent', 'enabled' => true, 'settings_link' => null]);
        $consent = $this->atlas->instance->setCookieConsent(['script_url' => 'https://cmp.example/s.js', 'settings_link' => null]);
        $sent = json_decode((string) $this->mock->lastRequest()->getBody(), true);
        $this->assertArrayHasKey('settings_link', $sent);
        $this->assertNull($sent['settings_link']);
        $this->assertNull($consent['settings_link']);

        $this->mock->push(200, [
            'object' => 'cookie_consent', 'enabled' => true,
            'settings_link' => ['label' => 'Privacy', 'preset' => 'newcmp', 'attributes' => [], 'rendered_attributes' => ['data-newcmp' => 'open']],
        ]);
        $link = $this->atlas->instance->getCookieConsent()['settings_link'];
        $this->assertSame('newcmp', $link['preset']);
        $this->assertSame(['data-newcmp' => 'open'], $link['rendered_attributes']);
    }

    public function testCookieSettingsLinkPresetConstants(): void
    {
        $this->assertSame('cookiemunch', Instance::COOKIE_SETTINGS_LINK_PRESET_COOKIEMUNCH);
        $this->assertSame('cookiebot', Instance::COOKIE_SETTINGS_LINK_PRESET_COOKIEBOT);
        $this->assertSame('onetrust', Instance::COOKIE_SETTINGS_LINK_PRESET_ONETRUST);
        $this->assertSame('custom', Instance::COOKIE_SETTINGS_LINK_PRESET_CUSTOM);
        $this->assertSame(['cookiemunch', 'cookiebot', 'onetrust', 'custom'], Instance::COOKIE_SETTINGS_LINK_PRESETS);
    }

    public function testOAuthClientSkipLogoutConfirmation(): void
    {
        $this->mock->push(201, ['object' => 'oauth_client', 'id' => 'oc_1', 'first_party' => true, 'skip_logout_confirmation' => true]);
        $client = $this->atlas->oauthClients->create([
            'name' => 'Acme', 'redirect_uris' => ['https://app.example.com/cb'], 'first_party' => true,
            'skip_logout_confirmation' => true,
        ]);
        $this->assertSame('POST', $this->mock->lastRequest()->getMethod());
        $this->assertTrue(json_decode((string) $this->mock->lastRequest()->getBody(), true)['skip_logout_confirmation']);
        $this->assertTrue($client['skip_logout_confirmation']);

        $this->mock->push(200, ['object' => 'oauth_client', 'id' => 'oc_1', 'skip_logout_confirmation' => false]);
        $updated = $this->atlas->oauthClients->update('oc_1', ['skip_logout_confirmation' => false]);
        $this->assertSame('PATCH', $this->mock->lastRequest()->getMethod());
        $this->assertSame('https://api.atlas.test/v1/oauth_clients/oc_1', (string) $this->mock->lastRequest()->getUri());
        $this->assertSame('{"skip_logout_confirmation":false}', (string) $this->mock->lastRequest()->getBody());
        $this->assertFalse($updated['skip_logout_confirmation']);

        $this->mock->push(200, ['object' => 'oauth_client', 'id' => 'oc_1', 'skip_logout_confirmation' => true]);
        $this->assertTrue($this->atlas->oauthClients->get('oc_1')['skip_logout_confirmation']);

        $this->mock->push(200, ['data' => [['object' => 'oauth_client', 'id' => 'oc_1', 'skip_logout_confirmation' => false]]]);
        $this->assertFalse($this->atlas->oauthClients->list()['data'][0]['skip_logout_confirmation']);
    }

    public function testOAuthClientSkipLogoutConfirmationOnThirdPartyIsRefused(): void
    {
        $this->mock->push(422, ['errors' => [['code' => 'VALIDATION_FAILED', 'message' => 'first-party only', 'param' => 'skip_logout_confirmation']]]);

        try {
            $this->atlas->oauthClients->update('oc_3p', ['skip_logout_confirmation' => true]);
            $this->fail('expected a BadRequestException');
        } catch (BadRequestException $e) {
            $this->assertTrue($e->hasCode(ErrorCode::VALIDATION_FAILED));
            $this->assertSame('skip_logout_confirmation', $e->getErrors()[0]->param);
        }
    }

    public function testOAuthProvidersListFiltersAndDisplayFields(): void
    {
        $this->mock->push(200, ['data' => [['provider' => 'google', 'icon_url' => null, 'mode' => 'native']]]);
        $page = $this->atlas->oauthProviders->list(['provider' => 'google', 'configured' => true]);
        $this->assertSame('https://api.atlas.test/v1/oauth_providers?provider=google&configured=true', (string) $this->mock->lastRequest()->getUri());
        $this->assertSame('native', $page['data'][0]['mode']);

        $this->mock->push(200, ['provider' => 'siwe', 'setting_values' => ['domain' => 'app.example.com']]);
        $result = $this->atlas->oauthProviders->upsert('siwe', [
            'display_name' => 'Wallet', 'mode' => 'redirect',
            'settings' => ['domain' => 'app.example.com', 'rpc_url' => null],
        ]);
        $sent = json_decode((string) $this->mock->lastRequest()->getBody(), true);
        $this->assertArrayHasKey('rpc_url', $sent['settings']);
        $this->assertNull($sent['settings']['rpc_url']);
        $this->assertSame('app.example.com', $result['setting_values']['domain']);
    }

    public function testWebhookDeliveriesAcceptALimitAndEndpointsAreComplete(): void
    {
        $this->mock->push(200, ['data' => []]);
        $this->atlas->webhooks->deliveries('we_1', ['limit' => 10]);
        $this->assertSame('https://api.atlas.test/v1/webhook_endpoints/we_1/deliveries?limit=10', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['secret' => 'whsec_x']);
        $this->assertSame('whsec_x', $this->atlas->webhooks->endpoints->revealSecret('we_1')['secret']);
    }

    public function testEntitlementOverridesAndOrgEntitlements(): void
    {
        $this->mock->push(200, ['id' => 'eo_1']);
        $this->atlas->entitlementOverrides->create(['subject_type' => 'org', 'subject_id' => 'org_1', 'plan' => 'enterprise']);
        $this->assertSame('{"subject_type":"org","subject_id":"org_1","plan":"enterprise"}', (string) $this->mock->lastRequest()->getBody());

        $this->mock->push(200, ['data' => []]);
        $this->atlas->entitlementOverrides->list(['subject_type' => 'org', 'subject_id' => 'org_1']);
        $this->assertSame('https://api.atlas.test/v1/entitlement_overrides?subject_type=org&subject_id=org_1', (string) $this->mock->lastRequest()->getUri());

        $this->mock->push(200, ['plan' => 'enterprise']);
        $this->atlas->organizations->entitlements('org_1');
        $this->assertSame('https://api.atlas.test/v1/organizations/org_1/entitlements', (string) $this->mock->lastRequest()->getUri());
    }

    public function testOrgPolicySendsCamelCaseKeysAndNullClears(): void
    {
        $this->mock->push(200, ['policy' => ['requireMfa' => true]]);
        $this->atlas->organizations->updatePolicy('org_1', ['requireMfa' => true, 'ipAllowlist' => null, 'endUserPool' => ['enabled' => true]]);
        $this->assertSame('{"requireMfa":true,"ipAllowlist":null,"endUserPool":{"enabled":true}}', (string) $this->mock->lastRequest()->getBody());
        $this->assertContains('maxSessionAgeSeconds', Organizations::POLICY_FIELDS);
    }

    public function testRawExportReturnsText(): void
    {
        $this->mock->push(200, "{\"id\":\"ae_1\"}\n{\"id\":\"ae_2\"}\n", ['Content-Type' => 'application/x-ndjson']);
        $text = $this->atlas->auditEvents->export(['format' => 'jsonl']);
        $this->assertStringContainsString('ae_2', $text);
        $this->assertSame('https://api.atlas.test/v1/audit_events/export?format=jsonl', (string) $this->mock->lastRequest()->getUri());
    }

    public function testErrorCodeConstants(): void
    {
        $this->assertSame('EMAIL_DELIVERY_NOT_CONFIGURED', ErrorCode::EMAIL_DELIVERY_NOT_CONFIGURED);
        $this->assertSame('WRONG_KEY_SURFACE', ErrorCode::WRONG_KEY_SURFACE);
        $this->assertSame('EMAIL_PROVIDER_REQUIRED', ErrorCode::EMAIL_PROVIDER_REQUIRED);
        $this->assertSame('EMAIL_PROVIDER_IN_USE', ErrorCode::EMAIL_PROVIDER_IN_USE);
    }
}
