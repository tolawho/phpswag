<?php

namespace PhpSwag;

use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class TypeResolver
{
    private SchemaRegistry $schemaRegistry;
    private NameResolver $nameResolver;

    public function __construct(SchemaRegistry $schemaRegistry, NameResolver $nameResolver)
    {
        $this->schemaRegistry = $schemaRegistry;
        $this->nameResolver = $nameResolver;
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
            // Handle other generics like Collection<T> or ApiResponse<T> later
            return $this->resolveIdentifier($typeNode->type->name);
        }

        return ['type' => 'string'];
    }

    private function resolveIdentifier(string $name): array
    {
        $lowered = strtolower($name);
        $map = [
            'int' => 'integer',
            'integer' => 'integer',
            'string' => 'string',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'float' => 'number',
            'double' => 'number',
            'mixed' => 'string', // OpenAPI doesn't have mixed, default to string or object
            'void' => null,
        ];

        if (isset($map[$lowered])) {
            return $map[$lowered] ? ['type' => $map[$lowered]] : [];
        }

        // It's likely a class reference
        $fqcn = $this->nameResolver->resolve($name);
        return [
            '$ref' => '#/components/schemas/' . $this->schemaRegistry->getSchemaId($fqcn)
        ];
    }
}
