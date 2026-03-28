<?php

namespace Laravel\Ai\Gateway\Prism\Concerns;

use Laravel\Ai\Gateway\Prism\McpPropertySchema;
use Laravel\Ai\Gateway\Prism\PrismTool;
use Laravel\Ai\Mcp\McpServer;
use Laravel\Ai\Mcp\Tool as McpTool;

trait AddsMcpToolsToPrismRequests
{
    /**
     * Add MCP server tools to the Prism request.
     *
     * @param  array<McpServer>  $mcpServers
     */
    protected function addMcpTools($request, array $mcpServers): void
    {
        $prismTools = [];

        foreach ($mcpServers as $server) {
            foreach ($server->tools() as $tool) {
                $prismTools[] = $this->createPrismMcpTool($server, $tool);
            }
        }

        if (filled($prismTools)) {
            $existingTools = $request->tools ?? [];

            $request->withTools([...$existingTools, ...$prismTools]);
        }
    }

    /**
     * Create a Prism tool from an MCP tool definition.
     */
    protected function createPrismMcpTool(McpServer $server, McpTool $tool): PrismTool
    {
        $prismTool = (new PrismTool)
            ->as($tool->name)
            ->for($tool->description)
            ->using(fn ($arguments) => $this->invokeMcpTool($server, $tool->name, $arguments))
            ->withoutErrorHandling();

        $properties = $tool->inputSchema['properties'] ?? [];
        $required = $tool->inputSchema['required'] ?? [];

        foreach ($properties as $name => $schema) {
            $prismTool->withParameter(
                new McpPropertySchema($name, $schema),
                in_array($name, $required),
            );
        }

        return $prismTool;
    }

    /**
     * Invoke an MCP tool via its server with Prism argument unwrapping.
     */
    protected function invokeMcpTool(McpServer $server, string $name, array $arguments): string
    {
        $arguments = $arguments['schema_definition'] ?? $arguments;

        call_user_func($this->invokingMcpToolCallback, $server, $name, $arguments);

        return (string) tap(
            $server->callTool($name, $arguments),
            fn ($result) => call_user_func($this->mcpToolInvokedCallback, $server, $name, $arguments, $result)
        );
    }
}
