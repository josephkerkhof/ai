<?php

namespace Laravel\Ai\Events;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Mcp\McpServer;

class McpToolInvoked
{
    public function __construct(
        public string $invocationId,
        public string $toolInvocationId,
        public Agent $agent,
        public McpServer $server,
        public string $toolName,
        public array $arguments,
        public mixed $result,
    ) {}
}
