<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/mcp` — the Atlas management MCP server.
 */
final class Mcp extends Resource
{
    /**
     * `GET /v1/mcp` — the MCP server descriptor and the tools this key may call.
     *
     * @return array<string,mixed>
     */
    public function get(): array
    {
        return $this->http->request('GET', '/v1/mcp');
    }

    /**
     * `POST /v1/mcp` — send one MCP JSON-RPC message (initialize, tools/list, tools/call, ping).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function call(array $body): array
    {
        return $this->http->request('POST', '/v1/mcp', body: $body);
    }
}
