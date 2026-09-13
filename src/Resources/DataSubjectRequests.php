<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/data_subject_requests` — the GDPR/DSAR admin surface (export/erasure). */
final class DataSubjectRequests extends Resource
{
    /**
     * @param array{limit?:int,starting_after?:string,type?:string,status?:string,user_id?:string} $params
     *
     * @return array<string,mixed> a CursorPage of requests
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/data_subject_requests', query: $params);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/data_subject_requests/' . rawurlencode($id));
    }

    /** Fulfil a request now — build the export package, or run the erasure. @return array<string,mixed> */
    public function fulfill(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/data_subject_requests/' . rawurlencode($id) . '/fulfill',
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Reject a request with a recorded reason. Terminal — it cannot be re-actioned.
     *
     * @param array{reason?:string} $body
     *
     * @return array<string,mixed>
     */
    public function reject(string $id, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/data_subject_requests/' . rawurlencode($id) . '/reject',
            body: $body,
            idempotencyKey: $idempotencyKey,
        );
    }
}
