<?php

namespace Laravel\Ai\Gateway\Concerns;

use Closure;
use Laravel\Ai\Mcp\McpServer;
use Laravel\Ai\Mcp\Tool as McpTool;

trait InvokesMcpTools
{
    protected Closure $invokingMcpToolCallback;

    protected Closure $mcpToolInvokedCallback;

    /**
     * Specify callbacks that should be invoked when MCP tools are invoking / invoked.
     */
    public function onMcpToolInvocation(Closure $invoking, Closure $invoked): self
    {
        $this->invokingMcpToolCallback = $invoking;
        $this->mcpToolInvokedCallback = $invoked;

        return $this;
    }

    /**
     * Execute an MCP tool via its server.
     */
    protected function executeMcpTool(McpServer $server, string $name, array $arguments): string
    {
        call_user_func($this->invokingMcpToolCallback, $server, $name, $arguments);

        return (string) tap(
            $server->callTool($name, $arguments),
            fn ($result) => call_user_func($this->mcpToolInvokedCallback, $server, $name, $arguments, $result)
        );
    }

    /**
     * Find an MCP tool by name across the given servers.
     *
     * @param  array<McpServer>  $mcpServers
     * @return array{McpServer, McpTool}|null
     */
    protected function findMcpTool(string $name, array $mcpServers): ?array
    {
        foreach ($mcpServers as $server) {
            foreach ($server->tools() as $tool) {
                if ($tool->name === $name) {
                    return [$server, $tool];
                }
            }
        }

        return null;
    }

    /**
     * Initialize the MCP tool invocation callbacks.
     */
    protected function initializeMcpToolCallbacks(): void
    {
        $this->invokingMcpToolCallback ??= fn () => true;
        $this->mcpToolInvokedCallback ??= fn () => true;
    }
}
