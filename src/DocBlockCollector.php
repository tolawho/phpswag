<?php

namespace PhpSwag;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;

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

        $phpDocNode = $this->parser->parse($docComment);
        $tags = [];

        foreach ($phpDocNode->getTags() as $tag) {
            $tagName = $tag->name;
            $value = $tag->value;

            if ($value instanceof PropertyTagValueNode) {
                $tags[] = [
                    'name' => $tagName,
                    'type' => (string)$value->type,
                    'propertyName' => ltrim($value->propertyName, '$'),
                    'description' => $value->description
                ];
            } elseif ($value instanceof VarTagValueNode) {
                 $tags[] = [
                    'name' => $tagName,
                    'type' => (string)$value->type,
                    'propertyName' => $value->variableName ? ltrim($value->variableName, '$') : null,
                    'description' => $value->description
                ];
            } elseif ($value instanceof GenericTagValueNode) {
                $tags[] = [
                    'name' => $tagName,
                    'value' => $value->value
                ];
            } else {
                $tags[] = [
                    'name' => $tagName,
                    'value' => (string)$value
                ];
            }
        }

        return $tags;
    }
}
