<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/actions` — tenant code run in the hardened isolate at auth-pipeline triggers. */
final class Actions extends Resource
{
    /** @return array<string,mixed> a ListPage of actions */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/actions');
    }

    /**
     * @param array{name:string,trigger:string,code:string,enabled?:bool,secrets?:array<string,string>} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/actions', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/actions/' . rawurlencode($id));
    }

    /**
     * @param array{name?:string,code?:string,enabled?:bool,secrets?:array<string,string>} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/actions/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/actions/' . rawurlencode($id));
    }

    /**
     * Dry-run against a sample event in the sandbox; nothing is persisted.
     *
     * @param array<string,mixed> $event
     *
     * @return array<string,mixed>
     */
    public function test(string $id, array $event = []): array
    {
        return $this->http->request('POST', '/v1/actions/' . rawurlencode($id) . '/test', body: ['event' => $event]);
    }

    /** The actions bound to a trigger, in run order. @return array<string,mixed> */
    public function getBindings(string $trigger): array
    {
        return $this->http->request('GET', '/v1/actions/bindings/' . rawurlencode($trigger));
    }

    /**
     * Replace a trigger's ordered binding set.
     *
     * @param list<string> $actionIds
     *
     * @return array<string,mixed>
     */
    public function setBindings(string $trigger, array $actionIds): array
    {
        return $this->http->request(
            'PUT',
            '/v1/actions/bindings/' . rawurlencode($trigger),
            body: ['action_ids' => $actionIds],
        );
    }
}
