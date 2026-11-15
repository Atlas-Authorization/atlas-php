<?php

declare(strict_types=1);

namespace Atlas\Tests\Generated;

use Atlas\Generated\Catalog;
use Atlas\Http;
use Atlas\Tests\Support\MockClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

final class NullBodyTest extends TestCase
{
    public function testAnExplicitNullInABodyIsSentToClearTheField(): void
    {
        $mock = new MockClient();
        $mock->push(200, ['id' => 'x']);
        $f = new HttpFactory();
        $catalog = new Catalog(new Http('sk_test_x', 'https://api.example.test', $mock, $f, $f));
        $catalog->administrativeUnits->update('au_1', ['description' => null, 'name' => 'n']);
        self::assertSame('{"description":null,"name":"n"}', (string) $mock->lastRequest()->getBody());
    }
}
