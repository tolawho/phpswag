<?php

namespace PhpSwag\TagParser;

use PhpSwag\TypeResolver;

interface SchemaTagParserInterface
{
    /**
     * Returns the list of tags supported by this parser (e.g. ['@property', '@var'])
     *
     * @return array<int, string>
     */
    public function getSupportedTags(): array;

    /**
     * Parse the given tag and update the SchemaContext.
     *
     * @param array<string, mixed> $tagData
     */
    public function parse(array $tagData, SchemaContext $context, TypeResolver $typeResolver): void;
}
