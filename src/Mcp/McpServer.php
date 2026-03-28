<?php

namespace Laravel\Ai\Mcp;

use Laravel\Ai\Mcp\Exceptions\McpException;
use Laravel\Ai\Mcp\Protocol\JsonRpc;
use Laravel\Ai\Mcp\Transports\HttpTransport;
use Laravel\Ai\Mcp\Transports\McpTransport;
use Laravel\Ai\Mcp\Transports\StdioTransport;

class McpServer
{
    /**
     * Whether the MCP connection has been initialized.
     */
    protected bool $initialized = false;

    /**
     * The cached tool definitions from this server.
     *
     * @var array<Tool>|null
     */
    protected ?array $cachedTools = null;

    /**
     * The server's capabilities from the initialize response.
     */
    protected array $serverCapabilities = [];

    /**
     * Optional instructions from the server.
     */
    protected ?string $serverInstructions = null;

    /**
     * The negotiated protocol version.
     */
    protected ?string $protocolVersion = null;

    /**
     * Create a new MCP server instance.
     */
    public function __construct(
        protected string $name,
        protected McpTransport $transport,
    ) {}

    /**
     * Get the server's configured name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Initialize the MCP connection (handshake).
     *
     * @throws \Laravel\Ai\Mcp\Exceptions\McpException
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->transport->open();

        $response = $this->transport->send(JsonRpc::request('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new \stdClass,
            'clientInfo' => [
                'name' => 'laravel-ai',
                'version' => '0.x',
            ],
        ]));

        $result = JsonRpc::result($response);

        $this->serverCapabilities = $result['capabilities'] ?? [];
        $this->serverInstructions = $result['instructions'] ?? null;
        $this->protocolVersion = $result['protocolVersion'] ?? '2025-11-25';

        if ($this->transport instanceof HttpTransport) {
            $this->transport->setProtocolVersion($this->protocolVersion);
        }

        $this->transport->notify(JsonRpc::notification('notifications/initialized'));

        $this->initialized = true;
    }

    /**
     * List available tools from the MCP server, handling pagination.
     *
     * @return array<Tool>
     *
     * @throws \Laravel\Ai\Mcp\Exceptions\McpException
     */
    public function tools(): array
    {
        $this->ensureInitialized();

        if ($this->cachedTools !== null) {
            return $this->cachedTools;
        }

        $tools = [];
        $cursor = null;

        do {
            $params = [];

            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $response = $this->transport->send(JsonRpc::request('tools/list', $params));
            $result = JsonRpc::result($response);

            foreach ($result['tools'] ?? [] as $toolData) {
                $tools[] = Tool::fromArray($toolData);
            }

            $cursor = $result['nextCursor'] ?? null;
        } while ($cursor !== null);

        return $this->cachedTools = $tools;
    }

    /**
     * Call a tool on the MCP server.
     *
     * @return string The text content from the tool response.
     *
     * @throws \Laravel\Ai\Mcp\Exceptions\McpException
     */
    public function callTool(string $name, array $arguments = []): string
    {
        $this->ensureInitialized();

        $response = $this->transport->send(JsonRpc::request('tools/call', [
            'name' => $name,
            'arguments' => (object) $arguments,
        ]));

        $result = JsonRpc::result($response);

        $textContent = collect($result['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        if ($result['isError'] ?? false) {
            throw McpException::toolExecutionError($name, $textContent);
        }

        return $textContent;
    }

    /**
     * Determine if the server has a tool with the given name.
     */
    public function hasTool(string $name): bool
    {
        return collect($this->tools())->contains(fn (Tool $tool) => $tool->name === $name);
    }

    /**
     * Disconnect from the MCP server.
     */
    public function disconnect(): void
    {
        if (! $this->initialized) {
            return;
        }

        $this->transport->close();

        $this->initialized = false;
        $this->cachedTools = null;
        $this->serverCapabilities = [];
        $this->serverInstructions = null;
        $this->protocolVersion = null;
    }

    /**
     * Get the server's capabilities.
     */
    public function capabilities(): array
    {
        return $this->serverCapabilities;
    }

    /**
     * Get the server's instructions.
     */
    public function instructions(): ?string
    {
        return $this->serverInstructions;
    }

    /**
     * Create an MCP server using stdio transport.
     */
    public static function stdio(array $command, array $env = [], int $timeout = 30): static
    {
        $name = implode(' ', $command);

        return new static($name, new StdioTransport($command, $env, $timeout));
    }

    /**
     * Create an MCP server using HTTP transport.
     */
    public static function http(string $url, array $headers = [], int $timeout = 30): static
    {
        return new static($url, new HttpTransport($url, $headers, $timeout));
    }

    /**
     * Ensure the MCP connection has been initialized.
     */
    protected function ensureInitialized(): void
    {
        if (! $this->initialized) {
            $this->initialize();
        }
    }
}
