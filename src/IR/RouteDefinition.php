<?php

namespace PhpSwag\IR;

class RouteDefinition
{
    /**
     * @param array<int, string> $tags
     * @param array<int|string, array<string, mixed>> $responses
     * @param array<int, array<string, mixed>> $parameters
     * @param array{schema: array<string, mixed>, description?: string|null}|null $requestBody
     * @param array<int, array<string, array<int, string>>> $security
     */
    public function __construct(
        public string $method,
        public string $path,
        public ?string $summary = null,
        public ?string $description = null,
        public array $tags = [],
        public array $responses = [],
        public array $parameters = [],
        public ?array $requestBody = null,
        public array $security = []
    ) {
    }
}
