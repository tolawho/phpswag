<?php

namespace PhpSwag\TagParser;

use PhpSwag\Exception\DiagnosticException;
use PhpSwag\TypeResolver;

class RouteTagParser implements TagParserInterface
{
    public function getSupportedTags(): array
    {
        return ['@route'];
    }

    public function parse(array $tagData, RouteContext $context, TypeResolver $typeResolver): void
    {
        $value = $tagData['value'] ?? '';
        if (preg_match('/^(GET|POST|PUT|DELETE|PATCH)\s+(\S+)/i', $value, $matches)) {
            $context->routeTag = strtoupper($matches[1]) . ' ' . $matches[2];
        } else {
            throw new DiagnosticException(sprintf(
                "Invalid syntax for tag '@route' in %s%s: expected format is '@route METHOD PATH', got '%s'",
                $tagData['file'] ?? 'unknown',
                isset($tagData['line']) ? " on line " . $tagData['line'] : "",
                $value
            ));
        }
    }
}
