<?php

namespace PhpSwag\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $method,
        public string $path
    ) {
    }
}
