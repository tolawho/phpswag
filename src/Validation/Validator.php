<?php

namespace PhpSwag\Validation;

class Validator
{
    /**
     * Validates the generated OpenAPI spec array.
     *
     * @param array<string, mixed> $spec
     * @return array<int, string> List of validation error/warning messages
     */
    public function validate(array $spec): array
    {
        $errors = [];

        // 1. Basic Spec Integrity Check
        if (empty($spec['openapi'])) {
            $errors[] = "Missing 'openapi' version field.";
        }
        if (!isset($spec['info']) || !is_array($spec['info'])) {
            $errors[] = "Missing 'info' object.";
        } else {
            if (empty($spec['info']['title'])) {
                $errors[] = "Missing 'info.title' field.";
            }
            if (empty($spec['info']['version'])) {
                $errors[] = "Missing 'info.version' field.";
            }
        }

        if (empty($spec['paths'])) {
            $errors[] = "Warning: No paths/endpoints defined in the API specification.";
        }

        // 2. Class Reference Verification ($ref check)
        $refs = $this->collectRefs($spec);
        $definedSchemas = array_keys($spec['components']['schemas'] ?? []);

        foreach ($refs as $ref) {
            if (str_starts_with($ref, '#/components/schemas/')) {
                $schemaName = substr($ref, strlen('#/components/schemas/'));
                if (!in_array($schemaName, $definedSchemas, true)) {
                    $errors[] = sprintf(
                        "Unresolved schema reference: '%s' is referenced but not defined in components/schemas.",
                        $ref
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * Recursively collects all $ref values from the spec array.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function collectRefs(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $refs = [];
        foreach ($value as $k => $v) {
            if ($k === '$ref' && is_string($v)) {
                $refs[] = $v;
            } else {
                $refs = array_merge($refs, $this->collectRefs($v));
            }
        }

        return $refs;
    }
}
