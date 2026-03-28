<?php

namespace Laravel\Ai\Mcp\Transports;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Mcp\Exceptions\McpException;

class HttpTransport implements McpTransport
{
    protected bool $opened = false;

    protected ?string $sessionId = null;

    protected ?string $protocolVersion = null;

    /**
     * Create a new HTTP transport instance.
     *
     * @param  string  $url  The MCP server endpoint URL.
     * @param  array  $headers  Additional headers to include in requests.
     * @param  int  $timeout  Timeout in seconds for HTTP requests.
     */
    public function __construct(
        protected string $url,
        protected array $headers = [],
        protected int $timeout = 30,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function open(): void
    {
        $this->opened = true;
    }

    /**
     * {@inheritdoc}
     */
    public function send(array $request): array
    {
        $this->ensureOpen();

        $response = $this->post($request);

        $this->captureSessionId($response);

        $contentType = $response->header('Content-Type');

        if (str_contains($contentType, 'text/event-stream')) {
            return $this->parseJsonRpcFromSse($response->body(), $request['id']);
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw McpException::connectionFailed($this->url, 'Invalid JSON response from server.');
        }

        return $decoded;
    }

    /**
     * {@inheritdoc}
     */
    public function notify(array $notification): void
    {
        $this->ensureOpen();

        $response = $this->post($notification);

        $this->captureSessionId($response);

        if (! $response->successful() && $response->status() !== 202) {
            throw McpException::connectionFailed(
                $this->url,
                "Server returned HTTP {$response->status()} for notification.",
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->sessionId !== null) {
            Http::timeout($this->timeout)
                ->withHeaders($this->buildHeaders())
                ->delete($this->url);
        }

        $this->opened = false;
        $this->sessionId = null;
        $this->protocolVersion = null;
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen(): bool
    {
        return $this->opened;
    }

    /**
     * Set the protocol version for subsequent requests.
     */
    public function setProtocolVersion(string $version): void
    {
        $this->protocolVersion = $version;
    }

    /**
     * Send a POST request to the MCP endpoint.
     */
    protected function post(array $body): Response
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                ...$this->buildHeaders(),
                'Accept' => 'application/json, text/event-stream',
            ])
            ->withBody(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'application/json')
            ->post($this->url);

        if ($response->status() === 404 && $this->sessionId !== null) {
            throw McpException::connectionFailed(
                $this->url,
                'Session expired or invalid. A new session must be started.',
            );
        }

        if ($response->serverError()) {
            throw McpException::connectionFailed(
                $this->url,
                "Server returned HTTP {$response->status()}.",
            );
        }

        return $response;
    }

    /**
     * Build the request headers.
     */
    protected function buildHeaders(): array
    {
        $headers = $this->headers;

        if ($this->sessionId !== null) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        if ($this->protocolVersion !== null) {
            $headers['MCP-Protocol-Version'] = $this->protocolVersion;
        }

        return $headers;
    }

    /**
     * Capture the session ID from the response headers.
     */
    protected function captureSessionId(Response $response): void
    {
        $sessionId = $response->header('Mcp-Session-Id');

        if (filled($sessionId)) {
            $this->sessionId = $sessionId;
        }
    }

    /**
     * Parse a JSON-RPC response from an SSE stream body.
     */
    protected function parseJsonRpcFromSse(string $body, string|int $requestId): array
    {
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || ! str_starts_with($line, 'data:')) {
                continue;
            }

            $data = trim(substr($line, 5));

            if ($data === '' || $data === '[DONE]') {
                continue;
            }

            $decoded = json_decode($data, true);

            if (is_array($decoded) && isset($decoded['id']) && $decoded['id'] === $requestId) {
                return $decoded;
            }
        }

        throw McpException::connectionFailed($this->url, 'No matching JSON-RPC response found in SSE stream.');
    }

    /**
     * Ensure the transport is open.
     */
    protected function ensureOpen(): void
    {
        if (! $this->opened) {
            throw McpException::connectionFailed($this->url, 'Transport is not open.');
        }
    }
}
