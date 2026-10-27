<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/mcp_gateways` - 5 operations.
 */
final class McpGateways extends Resource
{
    public readonly McpGatewaysBudgets $budgets;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->budgets = new McpGatewaysBudgets($http);
    }

    /**
     * `GET /v1/mcp_gateways` - List the MCP tool gateways of the instance.
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/mcp_gateways');
    }

    /**
     * `POST /v1/mcp_gateways` - Create an MCP gateway: one URL (`url` in the response) that fronts your
     * MCP server. `tools` maps a tool name to its policy (allow, scopes the agent token must hold,
     * argument conditions, approval, response masking); `default_policy` (deny by default) decides tools
     * not listed. upstream_authorization is write-only and sent to the upstream as its Authorization
     * header.
     *
     * @param array{name:string,upstream_url:string,upstream_authorization?:string,default_policy?:'allow'|'deny',enabled?:bool,abac?:array{enabled?:bool,policy_ids?:list<mixed>},tools?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/mcp_gateways',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/mcp_gateways/:id` - Read one MCP gateway.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/mcp_gateways/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/mcp_gateways/:id` - Update an MCP gateway. Omitted fields keep their value; `tools` is
     * replaced as a whole. `abac` turns on authorization of every tool call through the ABAC policy
     * engine.
     *
     * @param array{abac?:array{enabled?:bool,policy_ids?:list<mixed>}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/mcp_gateways/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/mcp_gateways/:id` - Delete an MCP gateway.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/mcp_gateways/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
