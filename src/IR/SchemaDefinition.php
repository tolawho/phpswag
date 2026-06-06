<?php

namespace PhpSwag\IR;

class SchemaDefinition
{
    public function __construct(
        public string $name,
        public array $properties = [],
        public ?string $parent = null,
        public array $traits = [],
        public array $templates = [], // e.g. ['T', 'K']
        public array $typeArguments = [] // e.g. ['T' => ['type' => 'string']]
    ) {}
}
