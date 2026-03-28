<?php

namespace Laravel\Ai\Mcp\Protocol;

use Laravel\Ai\Mcp\Exceptions\McpException;

class JsonRpc
{
    /**
     * The incrementing request ID counter.
     */
    protected static int $idCounter = 0;

    /**
     * Build a JSON-RPC 2.0 request message.
     */
    public static function request(string $method, array $params = []): array
    {
        $message = [
            'jsonrpc' => '2.0',
            'id' => ++static::$idCounter,
            'method' => $method,
        ];

        if (filled($params)) {
            $message['params'] = $params;
        }

        return $message;
    }

    /**
     * Build a JSON-RPC 2.0 notification message (no response expected).
     */
    public static function notification(string $method, array $params = []): array
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => $method,
        ];

        if (filled($params)) {
            $message['params'] = $params;
        }

        return $message;
    }

    /**
     * Extract the result from a JSON-RPC response, or throw on error.
     *
     * @throws \Laravel\Ai\Mcp\Exceptions\McpException
     */
    public static function result(array $response): mixed
    {
        if (isset($response['error'])) {
            throw McpException::fromJsonRpcError($response['error']);
        }

        return $response['result'];
    }

    /**
     * Reset the ID counter. Useful for testing.
     */
    public static function resetIdCounter(): void
    {
        static::$idCounter = 0;
    }
}
