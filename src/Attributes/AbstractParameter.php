<?php

namespace PhpSwag\Attributes;

abstract class AbstractParameter
{
    /**
     * @param array<mixed>|null $enum
     */
    public function __construct(
        public ?string $type = null,
        public ?string $description = null,
        public ?float $minimum = null,
        public ?float $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?string $format = null,
        public mixed $default = null,
        public mixed $example = null,
        public ?array $enum = null
    ) {
    }
}
