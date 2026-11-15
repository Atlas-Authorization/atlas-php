<?php

declare(strict_types=1);

namespace Atlas;

/** The result of {@see Doctor::run()}: `$ok` is true when no check failed; `$checks` lists every check with its verdict. */
final class DoctorReport
{
    /** @param list<array{name:string,status:string,detail:string}> $checks status is `ok`, `warn` or `fail` */
    public function __construct(public readonly bool $ok, public readonly array $checks)
    {
    }

    public function __toString(): string
    {
        $marks = ['ok' => '[ok]  ', 'warn' => '[warn]', 'fail' => '[FAIL]'];

        return implode("\n", array_map(static fn (array $c): string => $marks[$c['status']] . ' ' . $c['name'] . ': ' . $c['detail'], $this->checks)) . "\n";
    }
}
