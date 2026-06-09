<?php

namespace PhpSwag\TagParser;

use PhpSwag\TypeResolver;
use PhpSwag\TypeAnalyzer;
use PhpSwag\IR\PropertyDefinition;

class PropertyTagParser implements SchemaTagParserInterface
{
    private TypeAnalyzer $typeAnalyzer;

    public function __construct(TypeAnalyzer $typeAnalyzer)
    {
        $this->typeAnalyzer = $typeAnalyzer;
    }

    public function getSupportedTags(): array
    {
        return ['@property', '@var'];
    }

    public function parse(array $tagData, SchemaContext $context, TypeResolver $typeResolver): void
    {
        if (!isset($tagData['type'])) {
            return;
        }

        $context->isSchema = true;

        $propertySchema = $typeResolver->resolve(
            $tagData['type'],
            $tagData['line'] ?? null,
            $tagData['file'] ?? null
        );

        $desc = is_array($tagData['description'])
            ? ($tagData['description']['description'] ?? null)
            : ($tagData['description'] ?? null);

        $extra = is_array($tagData['description']) ? $tagData['description'] : [];
        unset($extra['description']);

        $propertyName = $tagData['propertyName'];

        // Sibling or class-level explicit required overrides
        $explicitRequired = $tagData['explicitRequired'] ?? $context->classExplicitRequired[$propertyName] ?? null;

        // Also check if @required is inline in the tag's description
        if ($desc !== null && stripos($desc, '@required') !== false) {
            $explicitRequired = true;
            $desc = preg_replace('/@required\s*/i', '', $desc);
            $desc = trim($desc);
        }

        $hasDefault = ($tagData['hasDefault'] ?? false) || isset($extra['default']);
        $isNullable = $this->typeAnalyzer->isDocTypeNullable($tagData['type']);
        $typeHint = $tagData['typeHint'] ?? null;

        $required = $this->typeAnalyzer->determineRequired(
            $propertyName,
            $isNullable,
            $explicitRequired,
            $hasDefault,
            $typeHint
        );

        $context->properties[] = new PropertyDefinition(
            $propertyName,
            $propertySchema,
            $desc,
            $extra,
            $tagData['file'] ?? null,
            $tagData['line'] ?? null,
            $required
        );
    }
}
