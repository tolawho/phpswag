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
    /** @var array<int, string> */
    private array $templates = [];

    /**
     * @param array<int, string> $templates
     */
    public function __construct(SchemaRegistry $schemaRegistry, NameResolver $nameResolver, array $templates = [])
    {
        $this->schemaRegistry = $schemaRegistry;
        $this->nameResolver = $nameResolver;
        $this->templates = $templates;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function resolve(TypeNode $typeNode, ?int $line = null, ?string $file = null): array
    {
        if ($typeNode instanceof IdentifierTypeNode) {
            return $this->resolveIdentifier($typeNode->name, $line, $file);
        }

        if ($typeNode instanceof NullableTypeNode) {
            $resolved = $this->resolve($typeNode->type, $line, $file);
            $resolved['nullable'] = true;
            return $resolved;
        }

        if ($typeNode instanceof ArrayTypeNode) {
            return [
                'type' => 'array',
                'items' => $this->resolve($typeNode->type, $line, $file)
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
                $types[] = $this->resolve($type, $line, $file);
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
                if (
                    count($typeNode->genericTypes) === 2 &&
                    $typeNode->genericTypes[0] instanceof IdentifierTypeNode &&
                    $typeNode->genericTypes[0]->name === 'string'
                ) {
                    return [
                        'type' => 'object',
                        'additionalProperties' => $this->resolve($typeNode->genericTypes[1], $line, $file)
                    ];
                }
                return [
                    'type' => 'array',
                    'items' => $this->resolve($typeNode->genericTypes[0], $line, $file)
                ];
            }

            return $this->resolveGeneric($typeNode, $line, $file);
        }

        return ['type' => 'string'];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveIdentifier(string $name, ?int $line = null, ?string $file = null): array
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

        if (array_key_exists($lowered, $map)) {
            $mappedVal = $map[$lowered];
            return $mappedVal !== null ? ['type' => $mappedVal] : [];
        }

        $fqcn = $this->nameResolver->resolve($name);
        if (!$this->schemaRegistry->has($fqcn)) {
            throw new \PhpSwag\Exception\DiagnosticException(sprintf(
                "Unresolved class '%s'%s%s",
                $fqcn,
                $file !== null ? " in $file" : "",
                $line !== null ? " on line $line" : ""
            ));
        }

        return [
            '$ref' => '#/components/schemas/' . $this->schemaRegistry->getSchemaId($fqcn)
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveGeneric(GenericTypeNode $typeNode, ?int $line = null, ?string $file = null): array
    {
        $baseName = $typeNode->type->name;
        $fqcn = $this->nameResolver->resolve($baseName);

        $baseSchema = $this->schemaRegistry->get($fqcn);
        if (!$baseSchema || empty($baseSchema->templates)) {
            return $this->resolveIdentifier($baseName, $line, $file);
        }

        $args = [];
        $ids = [];
        foreach ($typeNode->genericTypes as $i => $argNode) {
            $resolvedArg = $this->resolve($argNode, $line, $file);
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
