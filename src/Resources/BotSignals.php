<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/bot_signals` + `/v1/bot_labels` — the anti-bot signal lake and its labels. */
final class BotSignals extends Resource
{
    /**
     * Page the signal lake, newest first, for an incremental pull.
     *
     * @param array{limit?:int,before?:int} $query
     *
     * @return array<string,mixed> a `{ data, next_before }` export page
     */
    public function list(array $query = []): array
    {
        return $this->http->request('GET', '/v1/bot_signals', query: $query);
    }

    /**
     * Page the training labels, newest first.
     *
     * @param array{limit?:int,before?:int} $query
     *
     * @return array<string,mixed>
     */
    public function listLabels(array $query = []): array
    {
        return $this->http->request('GET', '/v1/bot_labels', query: $query);
    }

    /**
     * Attach a training label to a user / device / ip_hash / attempt.
     *
     * @param array{subject_type:string,subject_id:string,label:string,confidence?:float,note?:string} $body
     *
     * @return array<string,mixed>
     */
    public function createLabel(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/bot_labels', body: $body, idempotencyKey: $idempotencyKey);
    }
}
