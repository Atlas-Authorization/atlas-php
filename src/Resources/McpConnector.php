<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/mcp_connector` — the instance's OAuth-protected remote MCP connector.
 */
final class McpConnector extends Resource
{
    /**
     * `GET /v1/mcp_connector` — the remote MCP connector settings and its discovery URL.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/mcp_connector');
    }

    /**
     * `PUT /v1/mcp_connector` — configure the remote MCP connector. Only the fields you send change.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function update(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('PUT', '/v1/mcp_connector', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * `DELETE /v1/mcp_connector` — disable the remote MCP connector (its settings are kept for a later
     * re-enable).
     *
     * @return array<string,mixed>
     */
    public function disable(?string $idempotencyKey = null): array
    {
        return $this->http->request('DELETE', '/v1/mcp_connector', idempotencyKey: $idempotencyKey);
    }
}
