<?php

namespace PhpSwag\Attributes;

use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Identifier;
use PhpSwag\NameResolver;

class AttributeParser
{
    /** @var array<string, array<int, string>> */
    private static array $parameterMaps = [
        'PhpSwag\Attributes\Route' => ['method', 'path'],
        'PhpSwag\Attributes\Get' => ['path'],
        'PhpSwag\Attributes\Post' => ['path'],
        'PhpSwag\Attributes\Put' => ['path'],
        'PhpSwag\Attributes\Delete' => ['path'],
        'PhpSwag\Attributes\Tag' => ['name', 'description'],
        'PhpSwag\Attributes\OperationId' => ['id'],
        'PhpSwag\Attributes\Deprecated' => [],
        'PhpSwag\Attributes\Response' => ['code', 'type', 'description'],
        'PhpSwag\Attributes\RequestBody' => [
            'type', 'description', 'minimum', 'maximum', 'minLength',
            'maxLength', 'pattern', 'format', 'default', 'example', 'enum'
        ],
        'PhpSwag\Attributes\QueryParam' => [
            'name', 'type', 'description', 'minimum', 'maximum', 'minLength',
            'maxLength', 'pattern', 'format', 'default', 'example', 'enum'
        ],
        'PhpSwag\Attributes\PathParam' => [
            'name', 'type', 'description', 'minimum', 'maximum', 'minLength',
            'maxLength', 'pattern', 'format', 'default', 'example', 'enum'
        ],
        'PhpSwag\Attributes\HeaderParam' => [
            'name', 'type', 'description', 'minimum', 'maximum', 'minLength',
            'maxLength', 'pattern', 'format', 'default', 'example', 'enum'
        ],
        'PhpSwag\Attributes\CookieParam' => [
            'name', 'type', 'description', 'minimum', 'maximum', 'minLength',
            'maxLength', 'pattern', 'format', 'default', 'example', 'enum'
        ],
        'PhpSwag\Attributes\Property' => [
            'name', 'type', 'description', 'minimum', 'maximum', 'minLength',
            'maxLength', 'pattern', 'format', 'default', 'example', 'enum', 'required'
        ],
        'PhpSwag\Attributes\Schema' => ['title', 'description'],
        'Symfony\Component\Routing\Annotation\Route' => ['path'],
        'Symfony\Component\Routing\Attribute\Route' => ['path'],
    ];

    /**
     * @param array<int, AttributeGroup> $attrGroups
     * @return array<int, array{class: string, arguments: array<string, mixed>, line: int, file: string}>
     */
    public function parse(array $attrGroups, NameResolver $nameResolver, string $filePath): array
    {
        $parsed = [];

        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($attr->name instanceof \PhpParser\Node\Name\FullyQualified) {
                    $fqcn = $attr->name->toString();
                } else {
                    $fqcn = $nameResolver->resolve($attr->name->toString());
                }

                if (!isset(self::$parameterMaps[$fqcn])) {
                    // Skip attributes that are not owned or mapped by PhpSwag
                    continue;
                }

                $arguments = [];
                $paramMap = self::$parameterMaps[$fqcn];

                foreach ($attr->args as $index => $arg) {
                    $value = $this->evaluateExpression($arg->value, $nameResolver);
                    if ($arg->name !== null) {
                        // Named argument
                        $arguments[$arg->name->toString()] = $value;
                    } else {
                        // Positional argument
                        $paramName = $paramMap[$index] ?? null;
                        if ($paramName !== null) {
                            $arguments[$paramName] = $value;
                        }
                    }
                }

                $parsed[] = [
                    'class' => $fqcn,
                    'arguments' => $arguments,
                    'line' => $attr->getStartLine(),
                    'file' => $filePath
                ];
            }
        }

        return $parsed;
    }

    private function evaluateExpression(Expr $expr, NameResolver $nameResolver): mixed
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }
        if ($expr instanceof LNumber) {
            return $expr->value;
        }
        if ($expr instanceof DNumber) {
            return $expr->value;
        }
        if ($expr instanceof ConstFetch) {
            $name = strtolower($expr->name->toString());
            if ($name === 'true') {
                return true;
            }
            if ($name === 'false') {
                return false;
            }
            if ($name === 'null') {
                return null;
            }
        }
        if ($expr instanceof Array_) {
            $result = [];
            foreach ($expr->items as $item) {
                // @phpstan-ignore-next-line
                if ($item === null) {
                    continue;
                }
                $val = $this->evaluateExpression($item->value, $nameResolver);
                if ($item->key !== null) {
                    $key = $this->evaluateExpression($item->key, $nameResolver);
                    $result[$key] = $val;
                } else {
                    $result[] = $val;
                }
            }
            return $result;
        }
        if ($expr instanceof ClassConstFetch) {
            $isName = $expr->class instanceof \PhpParser\Node\Name;
            $isClassId = $expr->name instanceof Identifier;
            if ($isName && $isClassId && strtolower($expr->name->toString()) === 'class') {
                return $nameResolver->resolve($expr->class->toString());
            }
        }
        if ($expr instanceof UnaryMinus) {
            return -$this->evaluateExpression($expr->expr, $nameResolver);
        }
        if ($expr instanceof UnaryPlus) {
            return $this->evaluateExpression($expr->expr, $nameResolver);
        }
        return null;
    }
}
