<?php

namespace PhpSwag\TagParser;

use PhpSwag\TypeResolver;

class BodyTagParser implements TagParserInterface
{
    public function getSupportedTags(): array
    {
        return ['@body'];
    }

    public function parse(array $tagData, RouteContext $context, TypeResolver $typeResolver): void
    {
        $schema = $typeResolver->resolve(
            $tagData['type'],
            $tagData['line'] ?? null,
            $tagData['file'] ?? null
        );
        $extra = is_array($tagData['description']) ? $tagData['description'] : [];
        $desc = $extra['description'] ?? null;
        unset($extra['description']);
        $validationTags = [
            'enum', 'default', 'minimum', 'maximum', 'minLength',
            'maxLength', 'pattern', 'format', 'example'
        ];
        foreach ($validationTags as $vTag) {
            if (isset($extra[$vTag])) {
                $val = $extra[$vTag];
                if (
                    in_array(
                        $vTag,
                        ['minimum', 'maximum', 'minLength', 'maxLength', 'default', 'example']
                    )
                ) {
                    $val = is_numeric($val)
                        ? (strpos((string)$val, '.') !== false ? (float)$val : (int)$val)
                        : $val;
                }
                $schema[$vTag] = $val;
            }
        }
        $context->requestBody = [
            'schema' => $schema,
            'description' => $desc
        ];
    }
}
