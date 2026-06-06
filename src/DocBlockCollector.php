<?php

namespace PhpSwag;

use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;

class DocBlockCollector
{
    private DocBlockParser $parser;

    public function __construct()
    {
        $this->parser = new DocBlockParser();
    }

    public function collectTags(string $docComment): array
    {
        if (empty($docComment)) {
            return [];
        }

        $tags = [];
        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*/");
            if (empty($line)) {
                continue;
            }

            if (preg_match('/^(@[a-zA-Z0-9_]+)(?:\s+(.*))?$/', $line, $matches)) {
                $tagName = $matches[1];
                $value = isset($matches[2]) ? trim($matches[2]) : '';

                if (in_array($tagName, ['@property', '@var', '@param', '@return'])) {
                    try {
                        $doc = "/** $line */";
                        $node = $this->parser->parse($doc);
                        foreach ($node->getTags() as $tag) {
                             $v = $tag->value;
                            if ($v instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode) {
                                $tags[] = [
                                   'name' => $tagName,
                                   'type' => $v->type,
                                   'propertyName' => ltrim($v->propertyName, '$'),
                                   'description' => $v->description
                                ];
                            } elseif ($v instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode) {
                                $tags[] = [
                                   'name' => $tagName,
                                   'type' => $v->type,
                                   'propertyName' => $v->variableName ? ltrim($v->variableName, '$') : null,
                                   'description' => $v->description
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                         $tags[] = ['name' => $tagName, 'value' => $value];
                    }
                } else {
                    $tags[] = [
                        'name' => $tagName,
                        'value' => $value
                    ];
                }
            }
        }

        return $tags;
    }

    public function parseType(string $typeString): \PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        if (preg_match('/^([a-zA-Z0-9_\\\\]+)<(.*)>$/', $typeString, $matches)) {
            $base = $matches[1];
            $inner = $matches[2];

            $innerNodes = [];
            $parts = $this->splitByComma($inner);
            foreach ($parts as $part) {
                $node = $this->parseType(trim($part));
                if ($node) {
                    $innerNodes[] = $node;
                }
            }

            return new GenericTypeNode(
                new IdentifierTypeNode($base),
                $innerNodes
            );
        }

        return new IdentifierTypeNode($typeString);
    }

    private function splitByComma(string $str): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            if ($char === '<') {
                $depth++;
            } elseif ($char === '>') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if ($current !== '') {
            $parts[] = $current;
        }
        return $parts;
    }
}
