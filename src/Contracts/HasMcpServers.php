<?php

namespace Laravel\Ai\Contracts;

use Laravel\Ai\Mcp\McpServer;

interface HasMcpServers
{
    /**
     * Get the MCP servers available to the agent.
     *
     * @return array<string|McpServer>
     */
    public function mcpServers(): array;
}
