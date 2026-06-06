<?php

namespace PhpSwag;

use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;

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

            if (preg_match('/^(@[a-zA-Z0-9_.]+)(?:\s+(.*))?$/', $line, $matches)) {
                $tagName = $matches[1];
                $value = isset($matches[2]) ? trim($matches[2]) : '';

                if (in_array($tagName, ['@property', '@var', '@return', '@path', '@query', '@header', '@cookie'])) {
                    try {
                        // For @path, @query, etc., we treat them similarly to @param for parsing convenience
                        $parseTagName = in_array($tagName, ['@path', '@query', '@header', '@cookie']) ? '@param' : $tagName;
                        $doc = "/** $parseTagName $value */";
                        $node = $this->parser->parse($doc);
                        foreach ($node->getTags() as $tag) {
                             $v = $tag->value;
                            if ($v instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode) {
                                $tags[] = [
                                   'name' => $tagName,
                                   'type' => $v->type,
                                   'propertyName' => ltrim($v->propertyName, '$'),
                                   'description' => $this->parseExtraAttributes($v->description)
                                ];
                            } elseif ($v instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode) {
                                $tags[] = [
                                   'name' => $tagName,
                                   'type' => $v->type,
                                   'propertyName' => $v->variableName ? ltrim($v->variableName, '$') : null,
                                   'description' => $this->parseExtraAttributes($v->description)
                                ];
                            } elseif ($v instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode) {
                                $res = [
                                   'name' => $tagName,
                                   'type' => $v->type,
                                   'propertyName' => ltrim($v->parameterName, '$'),
                                ];
                                $parsedDesc = $this->parseExtraAttributes($v->description);
                                $res = array_merge($res, $parsedDesc);
                                $tags[] = $res;
                            }
                        }
                    } catch (\Exception $e) {
                         $tags[] = ['name' => $tagName, 'value' => $value];
                    }
                } elseif ($tagName === '@body') {
                    // @body [Type] [Description]
                    if (preg_match('/^([a-zA-Z0-9_\\<>|\[\]]+)(?:\s+(.*))?$/', $value, $m)) {
                        $typeString = $m[1];
                        $desc = isset($m[2]) ? $m[2] : '';

                        try {
                            $type = $this->parseType($typeString);
                        } catch (\Exception $e) {
                            $type = new IdentifierTypeNode($typeString);
                        }

                        $tags[] = [
                            'name' => '@body',
                            'type' => $type,
                            'description' => $desc
                        ];
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

    private function parseExtraAttributes(string $description): array
    {
        $res = ['description' => $description];

        // Parse enum(a,b,c)
        if (preg_match('/enum\(([^)]+)\)/', $description, $matches)) {
            $res['enum'] = array_map('trim', explode(',', $matches[1]));
            $res['description'] = trim(str_replace($matches[0], '', $res['description']));
        }

        // Parse default(value)
        if (preg_match('/default\(([^)]+)\)/', $description, $matches)) {
            $res['default'] = trim($matches[1]);
            $res['description'] = trim(str_replace($matches[0], '', $res['description']));
        }

        return $res;
    }

    public function parseType(string $typeString): \PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        if (str_ends_with($typeString, '[]')) {
            $inner = substr($typeString, 0, -2);
            return new ArrayTypeNode($this->parseType($inner));
        }

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
