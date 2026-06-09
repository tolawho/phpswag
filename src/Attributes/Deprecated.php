<?php

namespace PhpSwag\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Deprecated
{
    public function __construct()
    {
    }
}
