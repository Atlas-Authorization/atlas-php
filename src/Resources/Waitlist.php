<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/waitlist_entries` — the sign-up waitlist and its approve/deny decisions. */
final class Waitlist extends Resource
{
    /**
     * @param array{limit?:int,starting_after?:string,status?:string,query?:string} $params
     *
     * @return array<string,mixed> a list envelope with per-status counts
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/waitlist_entries', query: $params);
    }

    /**
     * Approve or deny a waitlist entry.
     *
     * @param array{status:string,note?:string} $body status is `approved` or `denied`
     *
     * @return array<string,mixed>
     */
    public function decide(string $id, array $body): array
    {
        return $this->http->request('POST', '/v1/waitlist_entries/' . rawurlencode($id) . '/decide', body: $body);
    }
}
