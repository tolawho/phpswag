<?php

namespace PhpSwag\TagParser;

use PhpSwag\TypeResolver;

interface TagParserInterface
{
    /**
     * Returns the list of tags supported by this parser (e.g. ['@route'])
     *
     * @return array<int, string>
     */
    public function getSupportedTags(): array;

    /**
     * Parse the given tag and update the RouteContext.
     *
     * @param array<string, mixed> $tagData
     */
    public function parse(array $tagData, RouteContext $context, TypeResolver $typeResolver): void;
}
