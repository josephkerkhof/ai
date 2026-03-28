<?php

namespace Laravel\Ai\Mcp\Transports;

interface McpTransport
{
    /**
     * Open the transport connection.
     */
    public function open(): void;

    /**
     * Send a JSON-RPC request and receive the response.
     *
     * @param  array  $request  A fully-formed JSON-RPC 2.0 request array.
     * @return array The decoded JSON-RPC 2.0 response array.
     *
     * @throws \Laravel\Ai\Mcp\Exceptions\McpException
     */
    public function send(array $request): array;

    /**
     * Send a JSON-RPC notification (no response expected).
     *
     * @throws \Laravel\Ai\Mcp\Exceptions\McpException
     */
    public function notify(array $notification): void;

    /**
     * Close the transport connection and release resources.
     */
    public function close(): void;

    /**
     * Determine if the transport connection is currently open.
     */
    public function isOpen(): bool;
}
