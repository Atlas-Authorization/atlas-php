<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/least_privilege` - 3 operations.
 */
final class LeastPrivilege extends Resource
{
    /**
     * `GET /v1/least_privilege/report` - The least-privilege report: for every active secret key, OAuth
     * client, MCP gateway and agent, what it is allowed to do against what it used over the window, the
     * permissions never used, and the narrower grant that would still cover its use. Credentials with
     * something unused come first. `format=csv` exports one row per permission.
     *
     * @param array{days?:int,subject_type?:'api_key'|'oauth_client'|'mcp_gateway'|'agent',subject_id?:string,only_unused?:bool,limit?:int,format?:'json'|'csv',starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function report(array $params = []): array
    {
        return $this->http->request('GET', '/v1/least_privilege/report', query: $params);
    }

    /**
     * `GET /v1/least_privilege/report/:type/:id` - The least-privilege report of one credential (`type` is
     * api_key, oauth_client, mcp_gateway or agent). 404 for a credential the report does not cover, such
     * as a revoked key.
     *
     * @param array{days?:int} $params
     *
     * @return array<string,mixed>
     */
    public function get(string $type, string $id, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/least_privilege/report/' . rawurlencode($type) . '/' . rawurlencode($id),
            query: $params,
        );
    }

    /**
     * `POST /v1/least_privilege/apply` - Apply a credential's suggestion: narrow a secret key's scopes, an
     * OAuth client's allowed scopes, an MCP gateway's tool policy, or revoke an agent's unused tool
     * grants. Preview it first with `?dry_run=true`, then send the same body for real. `digest` (from the
     * report) must match the current suggestion, else 409, so what you previewed is what is applied; the
     * write is conditional on the credential being unchanged. A suggestion only ever removes permissions.
     * Over a window the data does not fully cover (`confidence: low`) it needs `acknowledge_short_window:
     * true`. Audited as `least_privilege.applied`.
     *
     * @param array{subject_type?:'api_key'|'oauth_client'|'mcp_gateway'|'agent',subject_id?:string,digest?:string,days?:int,acknowledge_short_window?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function apply(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/least_privilege/apply',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
