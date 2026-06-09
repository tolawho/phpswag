<?php

namespace PhpSwag\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Response
{
    /**
     * @param int|string $code Response HTTP status code (e.g. 200, 404) or "default"
     */
    public function __construct(
        public int|string $code,
        public string $type,
        public ?string $description = null
    ) {
    }
}
