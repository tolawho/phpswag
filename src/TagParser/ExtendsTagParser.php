<?php

namespace PhpSwag\TagParser;

use PhpSwag\DocBlockCollector;
use PhpSwag\NameResolver;
use PhpSwag\SchemaRegistry;
use PhpSwag\TypeResolver;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;

class ExtendsTagParser implements SchemaTagParserInterface
{
    private DocBlockCollector $docCollector;
    private SchemaRegistry $schemaRegistry;

    public function __construct(
        DocBlockCollector $docCollector,
        SchemaRegistry $schemaRegistry
    ) {
        $this->docCollector = $docCollector;
        $this->schemaRegistry = $schemaRegistry;
    }

    public function getSupportedTags(): array
    {
        return ['@extends', '@use'];
    }

    public function parse(array $tagData, SchemaContext $context, TypeResolver $typeResolver): void
    {
        $value = $tagData['value'] ?? '';
        $typeNode = $this->docCollector->parseType($value);
        if ($typeNode instanceof GenericTypeNode) {
            $targetFqcn = $context->nameResolver->resolve($typeNode->type->name);
            $targetSchema = $this->schemaRegistry->get($targetFqcn);
            if ($targetSchema && !empty($targetSchema->templates)) {
                foreach ($typeNode->genericTypes as $i => $argNode) {
                    $templateName = $targetSchema->templates[$i] ?? "T$i";
                    $context->schema->typeArguments[$templateName] = $typeResolver->resolve(
                        $argNode,
                        $tagData['line'] ?? null,
                        $tagData['file'] ?? null
                    );
                }
            }
        }
    }
}
