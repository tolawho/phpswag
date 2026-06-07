<?php

namespace PhpSwag;

use PhpSwag\IR\PropertyDefinition;
use PhpSwag\IR\RouteDefinition;
use PhpSwag\IR\SchemaDefinition;
use Symfony\Component\Yaml\Yaml;

class Generator
{
    /** @var array<int, RouteDefinition> */
    private array $routes = [];
    private SchemaRegistry $schemaRegistry;
    private string $openApiVersion = '3.0.0';
    private bool $filterUnusedSchemas = false;
    private string $title = 'API Documentation';
    private string $apiVersion = '1.0.0';
    private ?string $description = null;
    /** @var array<string, mixed>|null */
    private ?array $contact = null;
    /** @var array<string, mixed>|null */
    private ?array $license = null;
    /** @var array<int, array<string, mixed>> */
    private array $servers = [];
    /** @var array<string, array<string, mixed>> */
    private array $securitySchemes = [];
    /** @var array<int, array<string, array<int, string>>> */
    private array $globalSecurity = [];
    /** @var array<string, array{name: string, description?: string}> */
    private array $globalTags = [];

    public function __construct(SchemaRegistry $schemaRegistry)
    {
        $this->schemaRegistry = $schemaRegistry;
    }

    public function setVersion(string $version): void
    {
        $this->openApiVersion = $version;
    }

    public function setFilterUnusedSchemas(bool $filter): void
    {
        $this->filterUnusedSchemas = $filter;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setApiVersion(string $version): void
    {
        $this->apiVersion = $version;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @param array<string, mixed>|null $contact
     */
    public function setContact(?array $contact): void
    {
        $this->contact = $contact;
    }

    /**
     * @param array<string, mixed>|null $license
     */
    public function setLicense(?array $license): void
    {
        $this->license = $license;
    }

    /**
     * @param array<int, array<string, mixed>> $servers
     */
    public function setServers(array $servers): void
    {
        $this->servers = $servers;
    }

    /**
     * @param array<string, array<string, mixed>> $schemes
     */
    public function setSecuritySchemes(array $schemes): void
    {
        $this->securitySchemes = $schemes;
    }

    /**
     * @param array<int, array<string, array<int, string>>> $security
     */
    public function setGlobalSecurity(array $security): void
    {
        $this->globalSecurity = $security;
    }

    /**
     * @param array<string, array{name: string, description?: string}> $globalTags
     */
    public function setGlobalTags(array $globalTags): void
    {
        $this->globalTags = $globalTags;
    }

    public function addRoute(RouteDefinition $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @return array<int, RouteDefinition>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function generateYaml(): string
    {
        $yaml = Yaml::dump($this->generateSpec(), 10, 2, Yaml::DUMP_NUMERIC_KEY_AS_STRING);
        return preg_replace(
            '/(?<=\n)(\s+)(?!(?:schema|properties|paths|schemas|responses|headers|examples|requestBodies|securitySchemes|additionalProperties|items|components|info|contact|license|externalDocs|xml)\b)([a-zA-Z0-9_-]+):\s*\{\s*\}\s*(?=\n)/',
            '$1$2: [  ]',
            $yaml
        );
    }

    public function generateJson(): string
    {
        $spec = $this->forceObjectsForJson($this->generateSpec());
        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : '{}';
    }

    /**
     * Recursively forces specified keys to be objects instead of empty arrays in JSON output.
     */
    private function forceObjectsForJson(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $objectKeys = [
            'properties',
            'schemas',
            'paths',
            'responses',
            'schema',
            'additionalProperties',
            'headers',
            'examples',
            'requestBodies',
            'securitySchemes',
            'items',
            'components',
            'info',
            'contact',
            'license',
            'externalDocs',
            'xml',
        ];

        $res = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $objectKeys, true) && is_array($value) && empty($value)) {
                $res[$key] = (object)[];
            } else {
                $res[$key] = $this->forceObjectsForJson($value);
            }
        }
        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateSpec(): array
    {
        $spec = [
            'openapi' => $this->openApiVersion,
            'info' => [
                'title' => $this->title,
                'version' => $this->apiVersion,
            ],
            'paths' => [],
            'components' => [
                'schemas' => []
            ]
        ];

        if ($this->description) {
            $spec['info']['description'] = $this->description;
        }
        if ($this->contact) {
            $spec['info']['contact'] = $this->contact;
        }
        if ($this->license) {
            $spec['info']['license'] = $this->license;
        }
        if (!empty($this->servers)) {
            $spec['servers'] = $this->servers;
        }

        if (!empty($this->securitySchemes)) {
            $spec['components']['securitySchemes'] = $this->securitySchemes;
        }

        if (!empty($this->globalSecurity)) {
            $spec['security'] = $this->globalSecurity;
        }

        $routeTags = [];
        foreach ($this->routes as $route) {
            foreach ($route->tags as $tag) {
                if (!in_array($tag, $routeTags)) {
                    $routeTags[] = $tag;
                }
            }
        }

        $orderedTags = [];
        $addedTagNames = [];

        // 1. Add explicitly defined global tags first
        foreach ($this->globalTags as $tagName => $tagObj) {
            $orderedTags[] = $tagObj;
            $addedTagNames[] = $tagName;
        }

        // 2. Add used tags that are not in global tags, sorted alphabetically
        $remainingTags = [];
        foreach ($routeTags as $tag) {
            if (!in_array($tag, $addedTagNames)) {
                $remainingTags[] = $tag;
            }
        }
        if (!empty($remainingTags)) {
            sort($remainingTags, SORT_STRING);
            foreach ($remainingTags as $tag) {
                $orderedTags[] = ['name' => $tag];
            }
        }

        if (!empty($orderedTags)) {
            $spec['tags'] = $orderedTags;
        }

        foreach ($this->routes as $route) {
            $method = strtolower($route->method);
            $path = $route->path;

            if (!isset($spec['paths'][$path])) {
                $spec['paths'][$path] = [];
            }

            $routeSpec = [
                'summary' => $route->summary,
                'responses' => []
            ];

            if ($route->description !== null && $route->description !== '') {
                $routeSpec['description'] = (string)$route->description;
            }

            if (!empty($route->tags)) {
                $routeSpec['tags'] = $route->tags;
            }

            if (!empty($route->security)) {
                $routeSpec['security'] = $route->security;
            }

            if ($route->operationId !== null && $route->operationId !== '') {
                $routeSpec['operationId'] = $route->operationId;
            }

            if ($route->deprecated) {
                $routeSpec['deprecated'] = true;
            }

            if (!empty($route->extensions)) {
                foreach ($route->extensions as $extName => $extVal) {
                    $routeSpec[$extName] = $extVal;
                }
            }

            if (!empty($route->parameters)) {
                $routeSpec['parameters'] = [];
                foreach ($route->parameters as $param) {
                    if (isset($param['in'])) {
                        $in = $param['in'];
                    } else {
                        $in = 'query';
                        if (strpos($path, '{' . $param['name'] . '}') !== false) {
                            $in = 'path';
                        }
                    }

                    $schema = $this->processSchemaOutput($param['schema']);

                    // Handle validation tags
                    $validationTags = [
                        'enum',
                        'default',
                        'minimum',
                        'maximum',
                        'minLength',
                        'maxLength',
                        'pattern',
                        'format',
                        'example',
                    ];
                    foreach ($validationTags as $vTag) {
                        if (isset($param[$vTag])) {
                            $val = $param[$vTag];
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

                    $paramSpec = [
                        'name' => $param['name'],
                        'in' => $in,
                        'required' => ($in === 'path' || (isset($param['required']) && $param['required'])),
                        'schema' => $schema
                    ];

                    if (!empty($param['description'])) {
                        $paramSpec['description'] = (string)$param['description'];
                    }

                    $routeSpec['parameters'][] = $paramSpec;
                }
            }

            if ($route->requestBody) {
                $contentTypes = ['application/json'];
                if ($route->accept !== null && $route->accept !== '') {
                    $contentTypes = $this->resolveMimeTypes($route->accept);
                }

                $contentSpec = [];
                foreach ($contentTypes as $contentType) {
                    $contentSpec[$contentType] = [
                        'schema' => $this->processSchemaOutput($route->requestBody['schema'])
                    ];
                }

                $routeSpec['requestBody'] = [
                    'required' => true,
                    'content' => $contentSpec
                ];
                if (!empty($route->requestBody['description'])) {
                    $routeSpec['requestBody']['description'] = $route->requestBody['description'];
                }
            }

            if (!empty($route->responses)) {
                foreach ($route->responses as $code => $schema) {
                    $contentTypes = ['application/json'];
                    if ($route->produce !== null && $route->produce !== '') {
                        $contentTypes = $this->resolveMimeTypes($route->produce);
                    }

                    $contentSpec = [];
                    foreach ($contentTypes as $contentType) {
                        $contentSpec[$contentType] = [
                            'schema' => $this->processSchemaOutput($schema)
                        ];
                    }

                    $description = $route->responseDescriptions[(string)$code]
                        ?? $route->responseDescriptions[$code]
                        ?? 'OK';
                    if ($description === '') {
                        $description = 'OK';
                    }

                    $routeSpec['responses'][(string)$code] = [
                        'description' => $description,
                        'content' => $contentSpec
                    ];
                }
            } else {
                $routeSpec['responses']['200'] = [
                    'description' => 'OK'
                ];
            }

            $spec['paths'][$path][$method] = $routeSpec;
        }

        $schemasToGenerate = $this->schemaRegistry->getAll();
        if ($this->filterUnusedSchemas) {
            $schemasToGenerate = $this->getUsedSchemas();
        }

        foreach ($schemasToGenerate as $schema) {
            if (!empty($schema->templates) && empty($schema->typeArguments)) {
                continue; // Don't generate base generic schemas
            }

            if ($schema->enum !== null) {
                $schemaSpec = [
                    'type' => $schema->enumType ?? 'string',
                    'enum' => $schema->enum
                ];
                $spec['components']['schemas'][$this->schemaRegistry->getSchemaId($schema->name)] = $schemaSpec;
                continue;
            }

            $properties = $this->resolveAllProperties($schema);
            $propSpecs = [];
            $requiredProps = [];
            foreach ($properties as $prop) {
                $propSchema = $this->applyTypeArguments($prop->schema, $schema->typeArguments);

                // Apply extra validation attributes to property schema
                $validationTags = [
                    'enum',
                    'default',
                    'minimum',
                    'maximum',
                    'minLength',
                    'maxLength',
                    'pattern',
                    'format',
                    'example',
                ];
                foreach ($validationTags as $vTag) {
                    if (isset($prop->extra[$vTag])) {
                        $val = $prop->extra[$vTag];
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
                        $propSchema[$vTag] = $val;
                    }
                }

                $propSpecs[$prop->name] = $this->processSchemaOutput($propSchema, $prop->description);
                if ($prop->required) {
                    $requiredProps[] = $prop->name;
                }
            }

            $schemaSpec = [
                'type' => 'object',
                'properties' => $propSpecs
            ];
            if (!empty($requiredProps)) {
                $schemaSpec['required'] = $requiredProps;
            }

            $spec['components']['schemas'][$this->schemaRegistry->getSchemaId($schema->name)] = $schemaSpec;
        }

        return $spec;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function processSchemaOutput(array $schema, ?string $description = null): array
    {
        if ($this->openApiVersion === '3.1.0') {
            if (isset($schema['nullable']) && $schema['nullable'] === true) {
                unset($schema['nullable']);
                if (isset($schema['type'])) {
                    if (is_array($schema['type'])) {
                        if (!in_array('null', $schema['type'])) {
                            $schema['type'][] = 'null';
                        }
                    } else {
                        $schema['type'] = [$schema['type'], 'null'];
                    }
                } elseif (isset($schema['oneOf'])) {
                     $schema['oneOf'][] = ['type' => 'null'];
                }
            }
        }

        if (isset($schema['$ref'])) {
            // Omit description when $ref is present as per OpenAPI 3.0 rules
            return ['$ref' => $schema['$ref']];
        }

        if ($description !== null && $description !== '') {
            $schema['description'] = (string)$description;
        }

        if (isset($schema['items'])) {
            $schema['items'] = $this->processSchemaOutput($schema['items']);
        }
        foreach (['oneOf', 'anyOf', 'allOf'] as $key) {
            if (isset($schema[$key])) {
                foreach ($schema[$key] as $i => $sub) {
                    $schema[$key][$i] = $this->processSchemaOutput($sub);
                }
            }
        }

        return $schema;
    }

    /**
     * @return array<int, PropertyDefinition>
     */
    private function resolveAllProperties(SchemaDefinition $schema): array
    {
        $properties = [];

        $targetSchema = $schema;
        if ($schema->base && $baseSchema = $this->schemaRegistry->get($schema->base)) {
            $targetSchema = $baseSchema;
        }

        if ($targetSchema->parent && $parentSchema = $this->schemaRegistry->get($targetSchema->parent)) {
            $parentProps = $this->resolveAllProperties($parentSchema);
            foreach ($parentProps as $p) {
                $properties[$p->name] = $p;
            }
        }

        foreach ($targetSchema->traits as $traitFqcn) {
            if ($traitSchema = $this->schemaRegistry->get($traitFqcn)) {
                $traitProps = $this->resolveAllProperties($traitSchema);
                foreach ($traitProps as $p) {
                    $properties[$p->name] = $p;
                }
            }
        }

        foreach ($targetSchema->properties as $p) {
            $properties[$p->name] = $p;
        }

        return array_values($properties);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $typeArgs
     * @return array<string, mixed>
     */
    private function applyTypeArguments(array $schema, array $typeArgs): array
    {
        if (empty($typeArgs)) {
            return $schema;
        }

        $type = $schema['type'] ?? null;

        if (is_string($type) && isset($typeArgs[$type])) {
             $substituted = $typeArgs[$type];
            if (isset($schema['nullable']) && $schema['nullable']) {
                $substituted['nullable'] = true;
            }
             return $substituted;
        }

        if ($type === 'array' && isset($schema['items'])) {
            $schema['items'] = $this->applyTypeArguments($schema['items'], $typeArgs);
        }

        foreach (['oneOf', 'anyOf', 'allOf'] as $key) {
            if (isset($schema[$key])) {
                foreach ($schema[$key] as $i => $subSchema) {
                    $schema[$key][$i] = $this->applyTypeArguments($subSchema, $typeArgs);
                }
            }
        }

        return $schema;
    }

    /**
     * @return array<int, SchemaDefinition>
     */
    private function getUsedSchemas(): array
    {
        $usedFqcns = [];

        foreach ($this->routes as $route) {
            foreach ($route->responses as $schema) {
                $this->collectFqcnsFromSchema($schema, $usedFqcns);
            }
            foreach ($route->parameters as $param) {
                $this->collectFqcnsFromSchema($param['schema'], $usedFqcns);
            }
            if ($route->requestBody) {
                $this->collectFqcnsFromSchema($route->requestBody['schema'], $usedFqcns);
            }
        }

        $usedSchemas = [];
        $processed = [];

        while (!empty($usedFqcns)) {
            $fqcn = array_shift($usedFqcns);
            if (isset($processed[$fqcn])) {
                continue;
            }
            $processed[$fqcn] = true;

            $schema = $this->schemaRegistry->get($fqcn);
            if ($schema) {
                $usedSchemas[$fqcn] = $schema;

                // Also collect FQCNs from properties
                foreach ($this->resolveAllProperties($schema) as $prop) {
                    $propSchema = $this->applyTypeArguments($prop->schema, $schema->typeArguments);
                    $this->collectFqcnsFromSchema($propSchema, $usedFqcns);
                }
            }
        }

        return array_values($usedSchemas);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<int, string> $usedFqcns
     */
    private function collectFqcnsFromSchema(array $schema, array &$usedFqcns): void
    {
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            $prefix = '#/components/schemas/';
            if (strpos($ref, $prefix) === 0) {
                $schemaId = substr($ref, strlen($prefix));
                foreach ($this->schemaRegistry->getAll() as $s) {
                    if ($this->schemaRegistry->getSchemaId($s->name) === $schemaId) {
                        $usedFqcns[] = $s->name;
                        break;
                    }
                }
            }
        }

        if (isset($schema['items'])) {
            $this->collectFqcnsFromSchema($schema['items'], $usedFqcns);
        }

        foreach (['oneOf', 'anyOf', 'allOf'] as $key) {
            if (isset($schema[$key])) {
                foreach ($schema[$key] as $sub) {
                    $this->collectFqcnsFromSchema($sub, $usedFqcns);
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveMimeTypes(string $mimeTypesString): array
    {
        $parts = preg_split('/[\s,]+/', trim($mimeTypesString));
        if ($parts === false) {
            return ['application/json'];
        }
        $resolved = [];
        $map = [
            'json' => 'application/json',
            'xml' => 'application/xml',
            'plain' => 'text/plain',
            'html' => 'text/html',
            'mpfd' => 'multipart/form-data',
            'x-www-form-urlencoded' => 'application/x-www-form-urlencoded',
        ];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $resolved[] = $map[strtolower($part)] ?? $part;
        }

        return !empty($resolved) ? $resolved : ['application/json'];
    }
}
