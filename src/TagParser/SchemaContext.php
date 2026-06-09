<?php

namespace PhpSwag\TagParser;

use PhpSwag\IR\SchemaDefinition;
use PhpSwag\IR\PropertyDefinition;
use PhpSwag\NameResolver;

class SchemaContext
{
    /** @var array<int, string> */
    public array $classTags = [];

    /** @var array<int, array<string, array<int, string>>> */
    public array $classSecurity = [];

    public ?string $classAccept = null;
    public ?string $classProduce = null;

    /** @var array<string, bool> */
    public array $classExplicitRequired = [];

    public bool $isSchema = false;

    /** @var array<int, PropertyDefinition> */
    public array $properties = [];

    public function __construct(
        public SchemaDefinition $schema,
        public NameResolver $nameResolver
    ) {
    }
}
