<?php

namespace PhpSwag\TagParser;

use PhpSwag\Exception\DiagnosticException;
use PhpSwag\TypeResolver;
use PhpSwag\TypeAnalyzer;
use PhpSwag\DocBlockCollector;

class ResponseTagParser implements TagParserInterface
{
    private TypeAnalyzer $typeAnalyzer;
    private DocBlockCollector $docCollector;

    public function __construct(TypeAnalyzer $typeAnalyzer, DocBlockCollector $docCollector)
    {
        $this->typeAnalyzer = $typeAnalyzer;
        $this->docCollector = $docCollector;
    }

    public function getSupportedTags(): array
    {
        return ['@response', '@success', '@failure'];
    }

    public function parse(array $tagData, RouteContext $context, TypeResolver $typeResolver): void
    {
        $tagName = $tagData['name'];
        $value = $tagData['value'] ?? '';
        if (preg_match('/^(\d+|default)\s+(.*)$/i', $value, $matches)) {
            $code = strtolower($matches[1]);
            $typeAndDesc = trim($matches[2]);
            [$typeToParse, $respDesc] = $this->typeAnalyzer->splitTypeAndDescription($typeAndDesc);

            if ($respDesc === '') {
                if ($tagName === '@success') {
                    $respDesc = 'Success';
                } elseif ($tagName === '@failure') {
                    $respDesc = 'Failure';
                } else {
                    $respDesc = 'OK';
                }
            }

            $typeNode = $this->docCollector->parseType($typeToParse);
            $context->responses[$code] = $typeResolver->resolve(
                $typeNode,
                $tagData['line'] ?? null,
                $tagData['file'] ?? null
            );
            $context->responseDescriptions[$code] = $respDesc;
        } else {
            throw new DiagnosticException(
                sprintf(
                    "Invalid syntax for tag '%s': expected format is '%s CODE TYPE [description]', got '%s'",
                    $tagName,
                    $tagName,
                    $value
                ),
                0,
                null,
                $tagData['file'] ?? null,
                $tagData['line'] ?? null
            );
        }
    }
}
