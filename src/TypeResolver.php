<?php

namespace PhpSwag;

use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PhpSwag\IR\SchemaDefinition;

class TypeResolver
{
    private SchemaRegistry $schemaRegistry;
    private NameResolver $nameResolver;
    private array $templates = [];

    public function __construct(SchemaRegistry $schemaRegistry, NameResolver $nameResolver, array $templates = [])
    {
        $this->schemaRegistry = $schemaRegistry;
        $this->nameResolver = $nameResolver;
        $this->templates = $templates;
    }

    public function resolve(TypeNode $typeNode): array
    {
        if ($typeNode instanceof IdentifierTypeNode) {
            return $this->resolveIdentifier($typeNode->name);
        }

        if ($typeNode instanceof NullableTypeNode) {
            $resolved = $this->resolve($typeNode->type);
            $resolved['nullable'] = true;
            return $resolved;
        }

        if ($typeNode instanceof ArrayTypeNode) {
            return [
                'type' => 'array',
                'items' => $this->resolve($typeNode->type)
            ];
        }

        if ($typeNode instanceof UnionTypeNode) {
            $types = [];
            $isNullable = false;
            foreach ($typeNode->types as $type) {
                if ($type instanceof IdentifierTypeNode && $type->name === 'null') {
                    $isNullable = true;
                    continue;
                }
                $types[] = $this->resolve($type);
            }

            if (count($types) === 1) {
                if ($isNullable) {
                    $types[0]['nullable'] = true;
                }
                return $types[0];
            }

            return ['oneOf' => $types];
        }

        if ($typeNode instanceof GenericTypeNode) {
            if ($typeNode->type->name === 'array' || $typeNode->type->name === 'list') {
                return [
                    'type' => 'array',
                    'items' => $this->resolve($typeNode->genericTypes[0])
                ];
            }

            return $this->resolveGeneric($typeNode);
        }

        return ['type' => 'string'];
    }

    private function resolveIdentifier(string $name): array
    {
        if (in_array($name, $this->templates)) {
            return ['type' => $name]; // Return template name as "type" to be substituted later
        }

        $lowered = strtolower($name);
        $map = [
            'int' => 'integer',
            'integer' => 'integer',
            'string' => 'string',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'float' => 'number',
            'double' => 'number',
            'mixed' => 'string',
            'void' => null,
        ];

        if (isset($map[$lowered])) {
            return $map[$lowered] ? ['type' => $map[$lowered]] : [];
        }

        $fqcn = $this->nameResolver->resolve($name);
        return [
            '$ref' => '#/components/schemas/' . $this->schemaRegistry->getSchemaId($fqcn)
        ];
    }

    private function resolveGeneric(GenericTypeNode $typeNode): array
    {
        $baseName = $typeNode->type->name;
        $fqcn = $this->nameResolver->resolve($baseName);

        $baseSchema = $this->schemaRegistry->get($fqcn);
        if (!$baseSchema || empty($baseSchema->templates)) {
            return $this->resolveIdentifier($baseName);
        }

        $args = [];
        $ids = [];
        foreach ($typeNode->genericTypes as $i => $argNode) {
            $resolvedArg = $this->resolve($argNode);
            $templateName = $baseSchema->templates[$i] ?? "T$i";
            $args[$templateName] = $resolvedArg;

            if (isset($resolvedArg['$ref'])) {
                $refParts = explode('/', $resolvedArg['$ref']);
                $ids[] = end($refParts);
            } elseif (isset($resolvedArg['type'])) {
                $ids[] = ucfirst($resolvedArg['type']);
            } else {
                $ids[] = 'Mixed';
            }
        }

        $instantiatedFqcn = $fqcn . '<' . implode(',', $ids) . '>';

        if (!$this->schemaRegistry->has($instantiatedFqcn)) {
            $instantiatedSchema = new SchemaDefinition(
                name: $instantiatedFqcn,
                properties: $baseSchema->properties,
                parent: $baseSchema->parent,
                traits: $baseSchema->traits,
                typeArguments: $args,
                base: $fqcn
            );
            $this->schemaRegistry->register($instantiatedSchema);

            $baseId = $this->schemaRegistry->getSchemaId($fqcn);
            $this->schemaRegistry->setCustomSchemaId($instantiatedFqcn, $baseId . '.' . implode('.', $ids));
        }

        return [
            '$ref' => '#/components/schemas/' . $this->schemaRegistry->getSchemaId($instantiatedFqcn)
        ];
    }
}
