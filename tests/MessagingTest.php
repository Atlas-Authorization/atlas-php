<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\Tests\Support\MockClient;
use PHPUnit\Framework\TestCase;

/** Request shape of the messaging-provider writes. */
final class MessagingTest extends TestCase
{
    private MockClient $mock;
    private Client $atlas;

    protected function setUp(): void
    {
        $this->mock = new MockClient();
        $this->atlas = new Client('sk_test_secret', 'https://api.atlas.test', $this->mock);
    }

    public function testSetEmailSendsTypedSmtpBodyUnchanged(): void
    {
        $this->mock->push(200, [
            'object' => 'messaging_provider', 'channel' => 'email', 'transport' => 'smtp',
            'from_address' => null, 'status' => 'active', 'config' => ['host' => 'smtp.acme.test'],
            'secrets_set' => ['password' => true], 'updated_at' => 1,
        ]);

        $p = $this->atlas->messaging->setEmail([
            'transport' => 'smtp',
            'from_address' => 'Acme <no-reply@acme.test>',
            'config' => ['host' => 'smtp.acme.test', 'port' => 465, 'secure' => true, 'password' => 'p'],
        ]);

        $request = $this->mock->lastRequest();
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('https://api.atlas.test/v1/messaging_providers/email', (string) $request->getUri());
        $this->assertSame([
            'transport' => 'smtp',
            'from_address' => 'Acme <no-reply@acme.test>',
            'config' => ['host' => 'smtp.acme.test', 'port' => 465, 'secure' => true, 'password' => 'p'],
        ], json_decode((string) $request->getBody(), true));
        $this->assertTrue($p['secrets_set']['password']);
        $this->assertArrayNotHasKey('password', $p['config']);
    }

    public function testSetSmsUpdateMayOmitStoredSecret(): void
    {
        $this->mock->push(200, ['object' => 'messaging_provider']);

        $this->atlas->messaging->setSms(['transport' => 'twilio', 'config' => ['from' => '+15551234567']]);

        $request = $this->mock->lastRequest();
        $this->assertSame('https://api.atlas.test/v1/messaging_providers/sms', (string) $request->getUri());
        $this->assertSame(
            ['transport' => 'twilio', 'config' => ['from' => '+15551234567']],
            json_decode((string) $request->getBody(), true),
        );
    }
}
