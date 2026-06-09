<?php

namespace PhpSwag\Metadata;

use PhpSwag\DocBlockCollector;
use PhpSwag\TagParser\SecurityTagParser;
use PhpSwag\Exception\DiagnosticException;

class GlobalMetadataDiscoverer
{
    private DocBlockCollector $docCollector;

    public function __construct(DocBlockCollector $docCollector)
    {
        $this->docCollector = $docCollector;
    }

    /**
     * Scans the file content for global metadata comments and returns the extracted structures.
     * @param array<string, mixed> $existingMetadata
     * @param array<string, string> $existingSources
     *
     * @return array{
     *     globalMetadata: array<string, mixed>,
     *     metadataSources: array<string, string>,
     *     securitySchemes: array<string, array<string, mixed>>,
     *     globalSecurity: array<int, array<string, array<int, string>>>,
     *     globalTags: array<string, array{name: string, description?: string}>
     * }
     */
    public function discover(
        string $code,
        string $filePath,
        array $existingMetadata = [],
        array $existingSources = []
    ): array {
        $globalMetadata = [];
        $metadataSources = [];
        $securitySchemes = [];
        $globalSecurity = [];
        $globalTags = [];

        $tokens = token_get_all($code);
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                $docComment = $token[1];
                $startLine = $token[2];
                $tags = $this->docCollector->collectTags($docComment, $startLine, $filePath);

                $isGlobalBlock = false;
                $hasRouteOrProperty = false;
                foreach ($tags as $tag) {
                    if (in_array($tag['name'], ['@route', '@property', '@var'])) {
                        $hasRouteOrProperty = true;
                        break;
                    }
                }
                if (!$hasRouteOrProperty) {
                    foreach ($tags as $tag) {
                        if (
                            in_array($tag['name'], ['@title', '@version', '@description', '@host']) ||
                            str_starts_with($tag['name'], '@contact.') ||
                            str_starts_with($tag['name'], '@license.') ||
                            str_starts_with($tag['name'], '@securityDefinitions.') ||
                            str_starts_with($tag['name'], '@tag.')
                        ) {
                            $isGlobalBlock = true;
                            break;
                        }
                    }
                }

                if (!$isGlobalBlock) {
                    continue;
                }

                foreach ($tags as $tag) {
                    $tagName = $tag['name'];
                    if (
                        in_array($tagName, ['@title', '@version', '@description', '@host']) ||
                        str_starts_with($tagName, '@contact.') ||
                        str_starts_with($tagName, '@license.')
                    ) {
                        $val = $tag['value'] ?? '';

                        // Check duplicates against existing and newly found metadata
                        $currentVal = $globalMetadata[$tagName] ?? $existingMetadata[$tagName] ?? null;
                        $currentSource = $metadataSources[$tagName] ?? $existingSources[$tagName] ?? null;

                        if ($currentVal !== null && $currentVal !== $val) {
                            throw new \Exception(sprintf(
                                "Duplicate global tag '%s' found in %s and %s",
                                $tagName,
                                $currentSource,
                                $filePath
                            ));
                        }
                        $globalMetadata[$tagName] = $val;
                        $metadataSources[$tagName] = $filePath;
                    } elseif ($tagName === '@securityDefinitions.apikey') {
                        if (preg_match('/^(\S+)\s+(header|query|cookie)\s+(\S+)/', $tag['value'], $matches)) {
                            $securitySchemes[$matches[1]] = [
                                'type' => 'apiKey',
                                'in' => $matches[2],
                                'name' => $matches[3]
                            ];
                        } else {
                            throw new DiagnosticException(
                                sprintf(
                                    "Invalid syntax for tag '@securityDefinitions.apikey': "
                                    . "expected format is '@securityDefinitions.apikey NAME IN KEY', got '%s'",
                                    $tag['value']
                                ),
                                0,
                                null,
                                $tag['file'] ?? $filePath,
                                $tag['line'] ?? null
                            );
                        }
                    } elseif ($tagName === '@securityDefinitions.jwt') {
                        if (trim($tag['value']) !== '') {
                            $securitySchemes[$tag['value']] = [
                                'type' => 'http',
                                'scheme' => 'bearer',
                                'bearerFormat' => 'JWT'
                            ];
                        } else {
                            throw new DiagnosticException(
                                "Invalid syntax for tag '@securityDefinitions.jwt': "
                                . "expected format is '@securityDefinitions.jwt NAME', got empty value",
                                0,
                                null,
                                $tag['file'] ?? $filePath,
                                $tag['line'] ?? null
                            );
                        }
                    } elseif ($tagName === '@securityDefinitions.basic') {
                        if (trim($tag['value']) !== '') {
                            $securitySchemes[$tag['value']] = [
                                'type' => 'http',
                                'scheme' => 'basic'
                            ];
                        } else {
                            throw new DiagnosticException(
                                "Invalid syntax for tag '@securityDefinitions.basic': "
                                . "expected format is '@securityDefinitions.basic NAME', got empty value",
                                0,
                                null,
                                $tag['file'] ?? $filePath,
                                $tag['line'] ?? null
                            );
                        }
                    } elseif ($tagName === '@security') {
                        $globalSecurity = array_merge(
                            $globalSecurity,
                            SecurityTagParser::parseSecurityTag($tag['value'])
                        );
                    } elseif ($tagName === '@tag.name') {
                        $parts = preg_split('/\s+/', $tag['value'], 2);
                        if (is_array($parts) && isset($parts[0]) && trim($parts[0]) !== '') {
                            $name = $parts[0];
                            $desc = isset($parts[1]) ? trim($parts[1]) : null;

                            $tagData = ['name' => $name];
                            if ($desc !== null && $desc !== '') {
                                $tagData['description'] = $desc;
                            }
                            $globalTags[$name] = $tagData;
                        } else {
                            throw new DiagnosticException(
                                sprintf(
                                    "Invalid syntax for tag '@tag.name': "
                                    . "expected format is '@tag.name NAME [description]', got '%s'",
                                    $tag['value']
                                ),
                                0,
                                null,
                                $tag['file'] ?? $filePath,
                                $tag['line'] ?? null
                            );
                        }
                    }
                }
            }
        }

        return [
            'globalMetadata' => $globalMetadata,
            'metadataSources' => $metadataSources,
            'securitySchemes' => $securitySchemes,
            'globalSecurity' => $globalSecurity,
            'globalTags' => $globalTags,
        ];
    }
}
