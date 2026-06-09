<?php

namespace PhpSwag\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class PathParam extends BaseParameter
{
}
