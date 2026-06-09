<?php

namespace PhpSwag\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OperationId
{
    public function __construct(
        public string $id
    ) {
    }
}
