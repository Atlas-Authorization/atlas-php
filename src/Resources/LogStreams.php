<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/log_streams` — the instance event feed forwarded to an external sink. */
final class LogStreams extends Resource
{
    /** @return array<string,mixed> a ListPage of streams */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/log_streams');
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/log_streams/' . rawurlencode($id));
    }

    /**
     * @param array{name:string,type:string,destination:array<string,mixed>,event_filter?:?list<string>,enabled?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/log_streams', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body PATCH accepts everything create does except `type`
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/log_streams/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/log_streams/' . rawurlencode($id));
    }

    /** Send ONE synthetic event with the real credentials; the cursor is never touched. @return array<string,mixed> */
    public function test(string $id): array
    {
        return $this->http->request('POST', '/v1/log_streams/' . rawurlencode($id) . '/test');
    }
}
