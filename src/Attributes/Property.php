<?php

namespace PhpSwag\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Property extends AbstractParameter
{
    /**
     * @param array<mixed>|null $enum
     */
    public function __construct(
        public ?string $name = null,
        ?string $type = null,
        ?string $description = null,
        ?float $minimum = null,
        ?float $maximum = null,
        ?int $minLength = null,
        ?int $maxLength = null,
        ?string $pattern = null,
        ?string $format = null,
        mixed $default = null,
        mixed $example = null,
        ?array $enum = null,
        public ?bool $required = null
    ) {
        parent::__construct(
            type: $type,
            description: $description,
            minimum: $minimum,
            maximum: $maximum,
            minLength: $minLength,
            maxLength: $maxLength,
            pattern: $pattern,
            format: $format,
            default: $default,
            example: $example,
            enum: $enum
        );
    }
}
