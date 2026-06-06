<?php

namespace PhpSwag\IR;

class PropertyDefinition
{
    public function __construct(
        public string $name,
        public array $schema,
        public ?string $description = null
    ) {
    }
}
