<?php

namespace PhpSwag\TagParser;

use PhpSwag\TypeResolver;

class BasicMethodTagParser implements TagParserInterface
{
    public function getSupportedTags(): array
    {
        return [
            '@summary',
            '@description',
            '@tag',
            '@accept',
            '@consume',
            '@produce',
            '@operationId',
            '@operationid',
            '@deprecated'
        ];
    }

    public function parse(array $tagData, RouteContext $context, TypeResolver $typeResolver): void
    {
        $tagName = $tagData['name'];
        $value = $tagData['value'] ?? '';

        switch ($tagName) {
            case '@summary':
                $context->summary = $value;
                break;
            case '@description':
                $context->description = $value;
                break;
            case '@tag':
                $splitTags = array_filter(array_map('trim', explode(',', $value)), fn($t) => $t !== '');
                $context->tags = array_merge($context->tags, $splitTags);
                break;
            case '@accept':
            case '@consume':
                $context->accept = $value;
                $context->hasMethodAccept = true;
                break;
            case '@produce':
                $context->produce = $value;
                $context->hasMethodProduce = true;
                break;
            case '@operationId':
            case '@operationid':
                $context->operationId = $value;
                break;
            case '@deprecated':
                $context->deprecated = true;
                break;
        }
    }
}
