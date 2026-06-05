<?php

namespace PhpSwag\IR;

class RouteDefinition
{
    public function __construct(
        public string $method,
        public string $path,
        public ?string $summary = null,
        public ?string $description = null,
        public array $tags = [],
        public ?string $responseRef = null,
        public array $responses = []
    ) {}
}
