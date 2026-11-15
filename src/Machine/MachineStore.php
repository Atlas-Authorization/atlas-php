<?php

declare(strict_types=1);

namespace Atlas\Machine;

/**
 * Where a machine keeps its enrolled identity `{issuer, machine_id, private_key_pem, public_key_pem, enrolled_at}`. The private key
 * never leaves it: protect it like any credential ({@see FileMachineStore} writes it 0600 in a 0700 directory).
 */
interface MachineStore
{
    /** @return array<string,mixed>|null */
    public function load(): ?array;

    /** @param array<string,mixed> $identity */
    public function save(array $identity): void;

    public function clear(): void;
}
