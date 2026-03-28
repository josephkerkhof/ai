<?php

namespace Laravel\Ai\Mcp;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Laravel\Ai\Mcp\Transports\HttpTransport;
use Laravel\Ai\Mcp\Transports\McpTransport;
use Laravel\Ai\Mcp\Transports\StdioTransport;

class McpManager
{
    /**
     * The cached MCP server instances.
     *
     * @var array<string, McpServer>
     */
    protected array $servers = [];

    /**
     * Create a new MCP manager instance.
     */
    public function __construct(protected Application $app) {}

    /**
     * Get or create an MCP server instance by name.
     *
     * @throws \InvalidArgumentException
     */
    public function server(string $name): McpServer
    {
        if (isset($this->servers[$name])) {
            return $this->servers[$name];
        }

        $config = $this->app['config']->get("ai.mcp.servers.{$name}");

        if (is_null($config)) {
            throw new InvalidArgumentException("MCP server [{$name}] is not configured.");
        }

        $transport = $this->createTransport($config);

        return $this->servers[$name] = new McpServer($name, $transport);
    }

    /**
     * Resolve an array of server names and McpServer instances.
     *
     * @param  array<string|McpServer>  $servers
     * @return array<McpServer>
     */
    public function resolveAll(array $servers): array
    {
        return array_map(
            fn ($server) => is_string($server) ? $this->server($server) : $server,
            $servers,
        );
    }

    /**
     * Disconnect all active MCP server connections.
     */
    public function disconnectAll(): void
    {
        foreach ($this->servers as $server) {
            $server->disconnect();
        }

        $this->servers = [];
    }

    /**
     * Create a transport instance from the given configuration.
     *
     * @throws \InvalidArgumentException
     */
    protected function createTransport(array $config): McpTransport
    {
        return match ($config['transport'] ?? null) {
            'stdio' => new StdioTransport(
                command: $config['command'],
                env: $config['env'] ?? [],
                timeout: $config['timeout'] ?? 30,
            ),
            'http' => new HttpTransport(
                url: $config['url'],
                headers: $config['headers'] ?? [],
                timeout: $config['timeout'] ?? 30,
            ),
            default => throw new InvalidArgumentException(
                "Unsupported MCP transport [{$config['transport']}].",
            ),
        };
    }
}
