<?php

namespace PhpSwag\IR;

class PropertyDefinition
{
    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $name,
        public array $schema,
        public ?string $description = null,
        public array $extra = [],
        public ?string $file = null,
        public ?int $line = null
    ) {
    }
}
