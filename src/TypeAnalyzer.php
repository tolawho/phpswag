<?php

namespace PhpSwag;

use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class TypeAnalyzer
{
    public function isNativeTypeNullable(?Node $type): bool
    {
        if ($type === null) {
            return true;
        }
        if ($type instanceof Node\NullableType) {
            return true;
        }
        if ($type instanceof Node\Identifier && strtolower($type->name) === 'mixed') {
            return true;
        }
        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $subType) {
                if (
                    $subType instanceof Node\Identifier &&
                    in_array(strtolower($subType->name), ['null', 'mixed'])
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function isDocTypeNullable(TypeNode $typeNode): bool
    {
        if ($typeNode instanceof NullableTypeNode) {
            return true;
        }
        if (
            $typeNode instanceof IdentifierTypeNode &&
            strtolower($typeNode->name) === 'mixed'
        ) {
            return true;
        }
        if ($typeNode instanceof UnionTypeNode) {
            foreach ($typeNode->types as $type) {
                if (
                    $type instanceof IdentifierTypeNode &&
                    in_array(strtolower($type->name), ['null', 'mixed'])
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function determineRequired(
        string $propertyName,
        bool $isNullable,
        ?bool $explicitRequired,
        bool $hasDefault,
        ?Node $typeHint
    ): bool {
        if ($explicitRequired !== null) {
            return $explicitRequired;
        }

        // If it has a default value, it is optional (not required)
        if ($hasDefault) {
            return false;
        }

        // If there is a native type hint, use its nullability
        if ($typeHint !== null) {
            return !$this->isNativeTypeNullable($typeHint);
        }

        // Otherwise, use the PHPDoc nullability
        return !$isNullable;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function splitTypeAndDescription(string $str): array
    {
        $str = trim($str);
        if (preg_match('/^([a-zA-Z0-9_\\\\]+)</', $str, $matches)) {
            $base = $matches[1];
            $depth = 0;
            $typeLen = 0;
            $started = false;
            for ($i = 0; $i < strlen($str); $i++) {
                $char = $str[$i];
                if ($char === '<') {
                    $depth++;
                    $started = true;
                } elseif ($char === '>') {
                    $depth--;
                }
                if ($started && $depth === 0) {
                    $typeLen = $i + 1;
                    break;
                }
            }
            if ($typeLen > 0) {
                $type = substr($str, 0, $typeLen);
                $desc = trim(substr($str, $typeLen));
                if (str_starts_with($desc, '[]')) {
                    $type .= '[]';
                    $desc = trim(substr($desc, 2));
                }
                return [$type, $desc];
            }
        }

        $parts = preg_split('/\s+/', $str, 2);
        $type = $parts[0] ?? '';
        $desc = $parts[1] ?? '';
        return [$type, $desc];
    }
}
