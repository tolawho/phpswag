<?php

namespace PhpSwag;

use Symfony\Component\Yaml\Yaml;
use PhpSwag\IR\RouteDefinition;
use PhpSwag\IR\SchemaDefinition;
use PhpSwag\IR\PropertyDefinition;

class Generator
{
    private array $routes = [];
    private SchemaRegistry $schemaRegistry;
    private string $openApiVersion = '3.0.0';
    private bool $filterUnusedSchemas = false;

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

    public function addRoute(RouteDefinition $route): void
    {
        $this->routes[] = $route;
    }

    public function generateYaml(): string
    {
        $spec = [
            'openapi' => $this->openApiVersion,
            'info' => [
                'title' => 'API Documentation',
                'version' => '1.0.0'
            ],
            'paths' => [],
            'components' => [
                'schemas' => []
            ]
        ];

        foreach ($this->routes as $route) {
            $path = $route->path;
            $method = strtolower($route->method);

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

            if (!empty($route->parameters)) {
                $routeSpec['parameters'] = [];
                foreach ($route->parameters as $param) {
                    $in = 'query';
                    if (strpos($path, '{' . $param['name'] . '}') !== false) {
                        $in = 'path';
                    }

                    $paramSpec = [
                        'name' => $param['name'],
                        'in' => $in,
                        'required' => $in === 'path',
                        'schema' => $this->processSchemaOutput($param['schema'])
                    ];

                    if (!empty($param['description'])) {
                        $paramSpec['description'] = (string)$param['description'];
                    }

                    $routeSpec['parameters'][] = $paramSpec;
                }
            }

            if (!empty($route->responses)) {
                foreach ($route->responses as $code => $schema) {
                    $routeSpec['responses'][(string)$code] = [
                        'description' => 'OK',
                        'content' => [
                            'application/json' => [
                                'schema' => $this->processSchemaOutput($schema)
                            ]
                        ]
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

            $properties = $this->resolveAllProperties($schema);
            $propSpecs = [];
            foreach ($properties as $prop) {
                $propSchema = $this->applyTypeArguments($prop->schema, $schema->typeArguments);
                $propSpecs[$prop->name] = $this->processSchemaOutput($propSchema, $prop->description);
            }

            $spec['components']['schemas'][$this->schemaRegistry->getSchemaId($schema->name)] = [
                'type' => 'object',
                'properties' => $propSpecs
            ];
        }

        return Yaml::dump($spec, 10, 2, Yaml::DUMP_NUMERIC_KEY_AS_STRING);
    }

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

    private function applyTypeArguments(array $schema, array $typeArgs): array
    {
        if (empty($typeArgs)) {
            return $schema;
        }

        if (isset($schema['type']) && is_string($schema['type']) && isset($typeArgs[$schema['type']])) {
             $substituted = $typeArgs[$schema['type']];
            if (isset($schema['nullable']) && $schema['nullable']) {
                $substituted['nullable'] = true;
            }
             return $substituted;
        }

        if (isset($schema['type']) && $schema['type'] === 'array' && isset($schema['items'])) {
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

                // Parent and traits are NOT included in $usedFqcns automatically
                // because we flatten them. Only include them if they are explicitly
                // referenced via $ref in some property or response.
            }
        }

        return array_values($usedSchemas);
    }

    private function collectFqcnsFromSchema(array $schema, array &$usedFqcns): void
    {
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            $prefix = '#/components/schemas/';
            if (strpos($ref, $prefix) === 0) {
                $schemaId = substr($ref, strlen($prefix));
                // We need to find the FQCN by schema ID.
                // Let's optimize this by looking at all registered schemas.
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
}
