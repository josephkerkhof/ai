<?php

namespace Laravel\Ai\Mcp\Exceptions;

use Laravel\Ai\Exceptions\AiException;

class McpException extends AiException
{
    /**
     * Create an exception from a JSON-RPC error response.
     */
    public static function fromJsonRpcError(array $error): self
    {
        $message = sprintf(
            'MCP JSON-RPC error [%s]: %s',
            $error['code'] ?? 'unknown',
            $error['message'] ?? 'Unknown error',
        );

        if (isset($error['data'])) {
            $message .= ' - '.json_encode($error['data']);
        }

        return new self($message, (int) ($error['code'] ?? 0));
    }

    /**
     * Create an exception for a tool execution error (isError flag).
     */
    public static function toolExecutionError(string $toolName, string $errorMessage): self
    {
        return new self("MCP tool [{$toolName}] returned an error: {$errorMessage}");
    }

    /**
     * Create an exception for a subprocess that exited unexpectedly.
     */
    public static function processExited(string $server, int $exitCode): self
    {
        return new self("MCP server [{$server}] process exited unexpectedly with code [{$exitCode}].");
    }

    /**
     * Create an exception for a transport timeout.
     */
    public static function timedOut(string $server, int $timeout): self
    {
        return new self("MCP server [{$server}] did not respond within [{$timeout}] seconds.");
    }

    /**
     * Create an exception for a connection failure.
     */
    public static function connectionFailed(string $server, string $reason): self
    {
        return new self("Failed to connect to MCP server [{$server}]: {$reason}");
    }
}
