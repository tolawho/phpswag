<?php

namespace PhpSwag\IR;

class SchemaDefinition
{
    /**
     * @param array<string, PropertyDefinition> $properties
     * @param array<int, string> $traits
     * @param array<int, string> $templates
     * @param array<string, array<string, mixed>> $typeArguments
     */
    public function __construct(
        public string $name,
        public array $properties = [],
        public ?string $parent = null,
        public array $traits = [],
        public array $templates = [], // e.g. ['T', 'K']
        public array $typeArguments = [], // e.g. ['T' => ['type' => 'string']]
        public ?string $base = null
    ) {
    }
}
