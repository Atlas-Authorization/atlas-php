<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * A store-bound view of the FGA operations, returned by {@see Fga::store()}, so
 * a single-store app never threads the store id through each call. Mirrors the
 * TS `fga.store(id)` ergonomic surface.
 */
final class FgaStoreScope
{
    public readonly FgaStoreScopeModels $models;

    public function __construct(private readonly Fga $fga, private readonly string $storeId)
    {
        $this->models = new FgaStoreScopeModels($fga, $storeId);
    }

    /**
     * @param array{writes?:mixed,deletes?:mixed} $body
     *
     * @return array<string,mixed>
     */
    public function write(array $body, ?string $idempotencyKey = null): array
    {
        return $this->fga->write($this->storeId, $body, $idempotencyKey);
    }

    /**
     * @param array{user?:string,relation?:string,object?:string} $body
     *
     * @return array<string,mixed>
     */
    public function read(array $body): array
    {
        return $this->fga->read($this->storeId, $body);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function check(array $body): array
    {
        return $this->fga->check($this->storeId, $body);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function batchCheck(array $body): array
    {
        return $this->fga->batchCheck($this->storeId, $body);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function listObjects(array $body): array
    {
        return $this->fga->listObjects($this->storeId, $body);
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function expand(array $body): array
    {
        return $this->fga->expand($this->storeId, $body);
    }
}
