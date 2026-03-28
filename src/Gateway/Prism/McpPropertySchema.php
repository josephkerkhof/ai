<?php

namespace Laravel\Ai\Gateway\Prism;

use Prism\Prism\Contracts\Schema as PrismSchema;

/**
 * A raw JSON Schema property wrapper for Prism's Schema contract.
 *
 * @internal
 */
class McpPropertySchema implements PrismSchema
{
    public function __construct(
        protected string $propertyName,
        protected array $schema,
    ) {}

    public function name(): string
    {
        return $this->propertyName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->schema;
    }
}
