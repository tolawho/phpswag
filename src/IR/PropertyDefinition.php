<?php

namespace PhpSwag\IR;

class PropertyDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $isNullable = false,
        public ?string $description = null
    ) {}
}
