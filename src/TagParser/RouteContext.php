<?php

namespace PhpSwag\TagParser;

class RouteContext
{
    public ?string $routeTag = null;
    public ?string $summary = null;
    public ?string $description = null;

    /** @var array<int, string> */
    public array $tags = [];

    /** @var array<int|string, array<string, mixed>> */
    public array $responses = [];

    /** @var array<int|string, string> */
    public array $responseDescriptions = [];

    /** @var array<int, array<string, mixed>> */
    public array $parameters = [];

    /** @var array{schema: array<string, mixed>, description?: string|null}|null */
    public ?array $requestBody = null;

    /** @var array<int, array<string, array<int, string>>> */
    public array $security = [];

    public ?string $accept = null;
    public ?string $produce = null;
    public ?string $operationId = null;
    public bool $deprecated = false;

    /** @var array<string, mixed> */
    public array $extensions = [];

    // Tracking flags for method-level overrides
    public bool $hasMethodSecurity = false;
    public bool $hasMethodAccept = false;
    public bool $hasMethodProduce = false;

    /**
     * @param array<int, string> $classTags
     */
    public function __construct(
        array $classTags = []
    ) {
        $this->tags = $classTags;
    }
}
