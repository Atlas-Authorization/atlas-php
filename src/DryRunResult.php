<?php

declare(strict_types=1);

namespace Atlas;

/**
 * What a write answers with `dry_run: true`.
 *
 * `mode` is `native` (the endpoint's own dry run, validated in full), `rollback` (the real write ran in a transaction that was
 * rolled back, every side effect held back, validated in full) or `plan` (validation could not run to the end; see
 * `validationSkipped`).
 */
final class DryRunResult
{
    /**
     * @param list<array{section:string,before:mixed,after:mixed}>        $changes
     * @param list<array<string,mixed>>                                   $warnings            the same non-fatal warnings the real write returns
     * @param list<string>                                                $suppressed          side effects held back (`email`, `sms`, `outbound_network`, ...)
     * @param array{reason:string,detail:string}|null                     $validationSkipped   why `plan` mode did not validate to the end
     * @param array<string,mixed>                                         $raw
     */
    public function __construct(
        public readonly string $mode,
        public readonly bool $validated,
        public readonly array $changes,
        public readonly array $warnings,
        public readonly mixed $wouldReturn,
        public readonly ?int $wouldReturnStatus,
        public readonly array $suppressed,
        public readonly ?array $validationSkipped,
        public readonly string $note,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string,mixed> $answer the decoded dry-run answer
     */
    public static function fromArray(array $answer): self
    {
        $list = static fn (mixed $v): array => is_array($v) ? array_values($v) : [];
        $skipped = $answer['validation_skipped'] ?? null;

        return new self(
            is_string($answer['mode'] ?? null) ? $answer['mode'] : 'plan',
            ($answer['validated'] ?? false) === true,
            $list($answer['changes'] ?? []),
            $list($answer['warnings'] ?? []),
            $answer['would_return'] ?? null,
            is_int($answer['would_return_status'] ?? null) ? $answer['would_return_status'] : null,
            $list($answer['suppressed'] ?? []),
            is_array($skipped) ? $skipped : null,
            is_string($answer['note'] ?? null) ? $answer['note'] : '',
            $answer,
        );
    }
}
