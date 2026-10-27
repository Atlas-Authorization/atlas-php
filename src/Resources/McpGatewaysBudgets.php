<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/mcp_gateways/:id/budgets` - 6 operations.
 */
final class McpGatewaysBudgets extends Resource
{
    /**
     * `GET /v1/mcp_gateways/:id/budgets` - The cross-call budgets of a gateway, newest first (at most 50):
     * ceilings on how many calls, how much cost, or how many calls per window one agent or one session may
     * make.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/mcp_gateways/' . rawurlencode($id) . '/budgets', query: $params);
    }

    /**
     * `POST /v1/mcp_gateways/:id/budgets` - Add a budget. `metric` is `calls` (a count), `cost` (the sum
     * of each call's cost: `cost_per_call`, or the number found at `cost_arg` in the call arguments, such
     * as an amount) or `rate` (calls per `window_seconds`, required). `limit` is the ceiling.
     * `window_seconds` makes the counter reset in fixed windows; without it a counter never resets (a
     * per-session one ends with the session). `scope` is `agent` (each agent counts separately) or
     * `session` (each session of each agent; the client must echo the `Mcp-Session-Id` from initialize).
     * `agent_id` limits it to one agent and `tool` to one tool. A call is charged against every budget it
     * touches at once, or against none; only a call that is allowed and about to run is charged.
     *
     * @param array{name?:string,scope?:'agent'|'session',metric?:'calls'|'cost'|'rate',limit?:int|float,window_seconds?:int|null,agent_id?:string|null,tool?:string|null,cost_per_call?:int|float,cost_arg?:string|null,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/mcp_gateways/' . rawurlencode($id) . '/budgets',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/mcp_gateways/:id/budgets/:budgetId` - Read one budget of an MCP gateway.
     *
     * @return array<string,mixed>
     */
    public function get(string $id, string $budgetId): array
    {
        return $this->http->request(
            'GET',
            '/v1/mcp_gateways/' . rawurlencode($id) . '/budgets/' . rawurlencode($budgetId),
        );
    }

    /**
     * `PATCH /v1/mcp_gateways/:id/budgets/:budgetId` - Change a budget. Omitted fields keep their value.
     * Raising the limit takes effect on the next call; counters are kept.
     *
     * @param array{name?:string,scope?:'agent'|'session',metric?:'calls'|'cost'|'rate',limit?:int|float,window_seconds?:int|null,agent_id?:string|null,tool?:string|null,cost_per_call?:int|float,cost_arg?:string|null,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, string $budgetId, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/mcp_gateways/' . rawurlencode($id) . '/budgets/' . rawurlencode($budgetId),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/mcp_gateways/:id/budgets/:budgetId` - Delete a budget and stop counting against it.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, string $budgetId, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/mcp_gateways/' . rawurlencode($id) . '/budgets/' . rawurlencode($budgetId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/mcp_gateways/:id/budgets/:budgetId/usage` - What one agent has used of a budget right now,
     * with the room left and when the window resets. A per-session budget also needs the `session_id` (the
     * Mcp-Session-Id of that session).
     *
     * @param array{agent_id?:string,session_id?:string} $params
     *
     * @return array<string,mixed>
     */
    public function usage(string $id, string $budgetId, array $params = []): array
    {
        return $this->http->request(
            'GET',
            '/v1/mcp_gateways/' . rawurlencode($id) . '/budgets/' . rawurlencode($budgetId) . '/usage',
            query: $params,
        );
    }
}
