<?php

namespace Laravel\Ai\Gateway\Concerns;

use Laravel\Ai\Mcp\McpServer;
use Laravel\Ai\Mcp\Tool as McpTool;

trait MapsMcpTools
{
    /**
     * Map MCP server tools to OpenAI function definitions.
     *
     * @param  array<McpServer>  $mcpServers
     */
    protected function mapMcpTools(array $mcpServers): array
    {
        $mapped = [];

        foreach ($mcpServers as $server) {
            foreach ($server->tools() as $tool) {
                $mapped[] = $this->mapMcpTool($tool);
            }
        }

        return $mapped;
    }

    /**
     * Map a single MCP tool definition to an OpenAI function definition.
     */
    protected function mapMcpTool(McpTool $tool): array
    {
        $definition = [
            'type' => 'function',
            'name' => $tool->name,
            'description' => $tool->description,
        ];

        if (filled($tool->inputSchema)) {
            $definition['parameters'] = $tool->inputSchema;
        }

        return $definition;
    }
}
