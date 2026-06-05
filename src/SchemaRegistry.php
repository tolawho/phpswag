<?php

namespace PhpSwag;

use PhpSwag\IR\SchemaDefinition;

class SchemaRegistry
{
    /** @var array<string, SchemaDefinition> */
    private array $schemas = [];

    public function register(SchemaDefinition $schema): void
    {
        $this->schemas[$schema->name] = $schema;
    }

    public function has(string $fqcn): bool
    {
        return isset($this->schemas[$fqcn]);
    }

    public function get(string $fqcn): ?SchemaDefinition
    {
        return $this->schemas[$fqcn] ?? null;
    }

    /** @return array<string, SchemaDefinition> */
    public function getAll(): array
    {
        return $this->schemas;
    }

    public function getSchemaId(string $fqcn): string
    {
        return str_replace('\\', '_', $fqcn);
    }
}
