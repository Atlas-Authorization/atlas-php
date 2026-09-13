<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/audit_logs` — the instance audit trail. */
final class AuditLogs extends Resource
{
    /**
     * @param array{limit?:int,starting_after?:string,actor_id?:string,action?:string} $params
     *
     * @return array<string,mixed> a CursorPage of audit logs
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/audit_logs', query: $params);
    }
}
