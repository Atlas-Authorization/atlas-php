<?php

declare(strict_types=1);

namespace Atlas\Machine;

/** A {@see MachineStore} in one JSON file: the file is written 0600 in a 0700 directory, atomically (temp file then rename). */
final class FileMachineStore implements MachineStore
{
    public function __construct(private readonly string $path)
    {
    }

    public function load(): ?array
    {
        $raw = @file_get_contents($this->path);
        $data = $raw === false ? null : json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    public function save(array $identity): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException("Could not create $dir.");
        }
        $tmp = $this->path . '.' . getmypid() . '.tmp';
        $fh = fopen($tmp, 'x');
        if ($fh === false) {
            throw new \RuntimeException("Could not write $tmp.");
        }
        chmod($tmp, 0600);
        fwrite($fh, json_encode($identity, JSON_THROW_ON_ERROR));
        fclose($fh);
        rename($tmp, $this->path);
    }

    public function clear(): void
    {
        @unlink($this->path);
    }
}
