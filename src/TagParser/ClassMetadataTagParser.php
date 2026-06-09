<?php

namespace PhpSwag\TagParser;

use PhpSwag\TypeResolver;

class ClassMetadataTagParser implements SchemaTagParserInterface
{
    public function getSupportedTags(): array
    {
        return ['@tag', '@security', '@accept', '@consume', '@produce', '@required'];
    }

    public function parse(array $tagData, SchemaContext $context, TypeResolver $typeResolver): void
    {
        $tagName = $tagData['name'];
        $value = $tagData['value'] ?? '';

        switch ($tagName) {
            case '@tag':
                $splitTags = array_filter(array_map('trim', explode(',', $value)), fn($t) => $t !== '');
                $context->classTags = array_merge($context->classTags, $splitTags);
                break;
            case '@security':
                $context->classSecurity = array_merge(
                    $context->classSecurity,
                    SecurityTagParser::parseSecurityTag($value)
                );
                break;
            case '@accept':
            case '@consume':
                $context->classAccept = $value;
                break;
            case '@produce':
                $context->classProduce = $value;
                break;
            case '@required':
                $val = trim($value);
                if ($val !== '') {
                    if (preg_match('/^([^\s]+)(?:\s+(.*))?$/', $val, $matches)) {
                        $propName = ltrim($matches[1], '$');
                        $optVal = isset($matches[2]) ? trim($matches[2]) : '';
                        if (strtolower($optVal) === 'false') {
                            $context->classExplicitRequired[$propName] = false;
                        } else {
                            $context->classExplicitRequired[$propName] = true;
                        }
                    }
                }
                break;
        }
    }
}
