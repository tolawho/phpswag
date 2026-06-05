<?php

namespace PhpSwag\IR;

class SchemaDefinition
{
    public function __construct(
        public string $name,
        public array $properties = []
    ) {}
}
