<?php

namespace PhpSwag\IR;

class SchemaDefinition
{
    /**
     * @param array<string, PropertyDefinition> $properties
     * @param array<int, string> $traits
     * @param array<int, string> $templates
     * @param array<string, array<string, mixed>> $typeArguments
     * @param array<int, mixed>|null $enum
     */
    public function __construct(
        public string $name,
        public array $properties = [],
        public ?string $parent = null,
        public array $traits = [],
        public array $templates = [], // e.g. ['T', 'K']
        public array $typeArguments = [], // e.g. ['T' => ['type' => 'string']]
        public ?string $base = null,
        public ?string $file = null,
        public ?int $line = null,
        public ?array $enum = null,
        public ?string $enumType = null
    ) {
    }
}
