<?php

declare(strict_types=1);

namespace Atlas\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Nothing public that existed before the full-coverage additions may disappear or change shape.
 * public-api-baseline.txt records every public property (`P`), constant (`C`) and method (`M`,
 * with its parameter names, `?` marking optional ones) of the earlier release; new parameters
 * may only be appended as optional ones.
 */
final class PublicApiCompatTest extends TestCase
{
    public function testEarlierPublicApiIsUnchanged(): void
    {
        $lines = array_values(array_filter(array_map('trim', file(__DIR__ . '/public-api-baseline.txt'))));
        $this->assertGreaterThan(1000, count($lines));

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            [$kind, $class, $name] = $parts;
            switch ($kind) {
                case 'P':
                    $this->assertTrue(property_exists($class, $name), "{$class}::\${$name} was removed");
                    break;
                case 'C':
                    $this->assertTrue(defined("{$class}::{$name}"), "{$class}::{$name} was removed");
                    break;
                default:
                    $this->assertTrue(method_exists($class, $name), "{$class}::{$name}() was removed");
                    $now = array_map(
                        static fn (\ReflectionParameter $p): string => $p->getName() . ($p->isOptional() ? '?' : ''),
                        (new \ReflectionMethod($class, $name))->getParameters(),
                    );
                    $was = ($parts[3] ?? '') === '' ? [] : explode(',', $parts[3]);
                    $this->assertSame($was, array_slice($now, 0, count($was)), "{$class}::{$name}() parameters changed");
                    foreach (array_slice($now, count($was)) as $extra) {
                        $this->assertStringEndsWith('?', $extra, "{$class}::{$name}() gained a required parameter");
                    }
            }
        }
    }

    public function testOldDeliveryHealthAndSearchFormsStillWork(): void
    {
        $this->assertTrue(method_exists(\Atlas\Resources\Webhooks::class, 'health'));
        $this->assertTrue(method_exists(\Atlas\Resources\Webhooks::class, 'setAlertContacts'));
        // Users never had a search() method; the new `search` property is a sub-namespace.
        $this->assertFalse(method_exists(\Atlas\Resources\Users::class, 'search'));
    }
}
