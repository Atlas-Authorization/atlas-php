<?php

declare(strict_types=1);

namespace Atlas\Tests;

use Atlas\Client;
use Atlas\ErrorCode;
use Atlas\Exception\AtlasApiException;
use Atlas\Tests\Support\MockClient;
use Atlas\UnknownFields;
use PHPUnit\Framework\TestCase;

/**
 * Strict request bodies (`Atlas-Strict`) and the `Atlas-Unknown-Fields` warning.
 */
final class StrictBodiesTest extends TestCase
{
    private MockClient $mock;

    protected function setUp(): void
    {
        $this->mock = new MockClient();
    }

    public function testNoStrictHeaderByDefault(): void
    {
        $atlas = new Client('sk_test_1', 'https://api.atlas.test', $this->mock);
        $this->mock->push(200, ['id' => 'user_1']);
        $atlas->users->update('user_1', ['first_name' => 'Ada']);
        $this->assertFalse($this->mock->lastRequest()->hasHeader('Atlas-Strict'));
    }

    public function testClientLevelStrictIsSentOnWritesOnly(): void
    {
        $atlas = new Client('sk_test_1', 'https://api.atlas.test', $this->mock, strict: true);

        $this->mock->push(200, ['id' => 'user_1']);
        $atlas->users->update('user_1', ['first_name' => 'Ada']);
        $this->assertSame('true', $this->mock->lastRequest()->getHeaderLine('Atlas-Strict'));

        $this->mock->push(200, ['deleted' => true]);
        $atlas->users->delete('user_1');
        $this->assertSame('true', $this->mock->lastRequest()->getHeaderLine('Atlas-Strict'));

        $this->mock->push(200, ['id' => 'user_1']);
        $atlas->users->get('user_1');
        $this->assertFalse($this->mock->lastRequest()->hasHeader('Atlas-Strict'));
    }

    public function testWithStrictOverridesPerCallAndLeavesTheOriginalAlone(): void
    {
        $atlas = new Client('sk_test_1', 'https://api.atlas.test', $this->mock, strict: true);

        $this->mock->push(200, ['id' => 'user_1']);
        $atlas->withStrict(false)->users->update('user_1', ['legacy' => 1]);
        $this->assertSame('false', $this->mock->lastRequest()->getHeaderLine('Atlas-Strict'));
        $this->assertSame('Bearer sk_test_1', $this->mock->lastRequest()->getHeaderLine('Authorization'));

        $this->mock->push(200, ['id' => 'user_1']);
        $atlas->users->update('user_1', ['first_name' => 'Ada']);
        $this->assertSame('true', $this->mock->lastRequest()->getHeaderLine('Atlas-Strict'));

        $this->mock->push(200, ['id' => 'org_1']);
        $atlas->withStrict(null)->organizations->create(['name' => 'Acme']);
        $this->assertFalse($this->mock->lastRequest()->hasHeader('Atlas-Strict'));
    }

    public function testTransportRequestAcceptsAStrictOverride(): void
    {
        $atlas = new Client('sk_test_1', 'https://api.atlas.test', $this->mock);
        $this->mock->push(200, ['id' => 'user_1']);
        $atlas->http->request('POST', '/v1/users', body: ['email_address' => 'a@b.c'], strict: true);
        $this->assertSame('true', $this->mock->lastRequest()->getHeaderLine('Atlas-Strict'));
    }

    public function testStrictRejectionSurfacesTheUnknownFieldCode(): void
    {
        $atlas = new Client('sk_test_1', 'https://api.atlas.test', $this->mock, strict: true);
        $this->mock->push(422, ['errors' => [['code' => 'UNKNOWN_FIELD', 'message' => 'Unknown field "nmae"', 'param' => 'nmae']]]);

        try {
            $atlas->users->update('user_1', ['nmae' => 'x']);
            $this->fail('expected an exception');
        } catch (AtlasApiException $e) {
            $this->assertSame(422, $e->getStatus());
            $this->assertTrue($e->hasCode(ErrorCode::UNKNOWN_FIELD));
            $this->assertSame('nmae', $e->getErrors()[0]->param);
        }
    }

    public function testUnknownFieldsHeaderReachesTheCallback(): void
    {
        $seen = [];
        $atlas = new Client(
            'sk_test_1',
            'https://api.atlas.test',
            $this->mock,
            onUnknownFields: static function (UnknownFields $w) use (&$seen): void {
                $seen[] = $w;
            },
        );
        $this->mock->push(200, ['id' => 'user_1'], ['Atlas-Unknown-Fields' => 'nmae, profile.colour']);

        $result = $atlas->users->update('user_1', ['nmae' => 'x']);

        $this->assertSame('user_1', $result['id']);
        $this->assertCount(1, $seen);
        $this->assertSame(['nmae', 'profile.colour'], $seen[0]->fields);
        $this->assertSame('PATCH', $seen[0]->method);
        $this->assertSame('/v1/users/user_1', $seen[0]->path);

        // The callback survives withStrict().
        $this->mock->push(200, ['id' => 'user_1'], ['Atlas-Unknown-Fields' => 'x']);
        $atlas->withStrict(false)->users->update('user_1', ['x' => 1]);
        $this->assertCount(2, $seen);
    }

    public function testCallbackIsSkippedWithoutTheHeaderAndItsErrorsAreSwallowed(): void
    {
        $calls = 0;
        $atlas = new Client('sk_test_1', 'https://api.atlas.test', $this->mock, onUnknownFields: static function () use (&$calls): void {
            $calls++;
        });
        $this->mock->push(200, ['id' => 'user_1']);
        $atlas->users->update('user_1', ['first_name' => 'A']);
        $this->assertSame(0, $calls);

        $boom = new Client('sk_test_1', 'https://api.atlas.test', $this->mock, onUnknownFields: static function (): void {
            throw new \RuntimeException('boom');
        });
        $this->mock->push(200, ['ok' => true], ['Atlas-Unknown-Fields' => 'x']);
        $this->assertTrue($boom->users->update('user_1', ['x' => 1])['ok']);
    }
}
