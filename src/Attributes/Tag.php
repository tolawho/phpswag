<?php

namespace PhpSwag\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Tag
{
    public function __construct(
        public string $name,
        public ?string $description = null
    ) {
    }
}
