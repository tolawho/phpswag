<?php

namespace PhpSwag\TagParser;

use PhpSwag\TypeResolver;

class SecurityTagParser implements TagParserInterface
{
    public function getSupportedTags(): array
    {
        return ['@security'];
    }

    public function parse(array $tagData, RouteContext $context, TypeResolver $typeResolver): void
    {
        $context->hasMethodSecurity = true;
        $context->security = array_merge($context->security, self::parseSecurityTag($tagData['value'] ?? ''));
    }

    /**
     * @return array<int, array<string, array<int, string>>>
     */
    public static function parseSecurityTag(string $value): array
    {
        if (trim($value) === '') {
            return [[]]; // Represents an empty security requirement object, which means "no security"
        }

        $requirements = [];
        $parts = self::splitCommasOutsideBrackets($value);
        $currentGroup = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (preg_match('/^([^\[]+)(?:\[(.*)\])?$/', $part, $matches)) {
                $name = trim($matches[1]);
                $scopes = isset($matches[2]) ? array_map('trim', explode(',', trim($matches[2]))) : [];
                $currentGroup[$name] = $scopes;
            }
        }
        if (!empty($currentGroup)) {
            $requirements[] = $currentGroup;
        }
        return $requirements;
    }

    /**
     * @return array<int, string>
     */
    private static function splitCommasOutsideBrackets(string $str): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $parts[] = $current;
        return $parts;
    }
}
