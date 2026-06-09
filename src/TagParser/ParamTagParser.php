<?php

namespace PhpSwag\TagParser;

use PhpSwag\TypeResolver;

class ParamTagParser implements TagParserInterface
{
    public function getSupportedTags(): array
    {
        return ['@path', '@query', '@header', '@cookie'];
    }

    public function parse(array $tagData, RouteContext $context, TypeResolver $typeResolver): void
    {
        $in = substr($tagData['name'], 1);
        $context->parameters[] = array_merge($tagData, [
            'in' => $in,
            'schema' => $typeResolver->resolve(
                $tagData['type'],
                $tagData['line'] ?? null,
                $tagData['file'] ?? null
            ),
            'name' => $tagData['propertyName']
        ]);
    }
}
