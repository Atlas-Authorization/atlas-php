<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** Store-bound view of the FGA model operations. */
final class FgaStoreScopeModels
{
    public function __construct(private readonly Fga $fga, private readonly string $storeId)
    {
    }

    /** @return array<string,mixed> */
    public function list(): array
    {
        return $this->fga->models->list($this->storeId);
    }

    /**
     * @param array{type_definitions:list<mixed>,schema_version?:string,conditions?:array<string,mixed>} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->fga->models->create($this->storeId, $body, $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $modelId): array
    {
        return $this->fga->models->get($this->storeId, $modelId);
    }
}
