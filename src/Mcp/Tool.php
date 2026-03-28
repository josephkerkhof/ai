<?php

namespace Laravel\Ai\Mcp;

class Tool
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly ?string $title = null,
        public readonly ?array $annotations = null,
    ) {}

    /**
     * Create a Tool instance from an MCP tools/list response array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? '',
            inputSchema: $data['inputSchema'] ?? ['type' => 'object'],
            title: $data['title'] ?? null,
            annotations: $data['annotations'] ?? null,
        );
    }
}
