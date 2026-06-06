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

    public function __construct(SchemaRegistry $schemaRegistry)
    {
        $this->schemaRegistry = $schemaRegistry;
    }

    public function setVersion(string $version): void
    {
        $this->openApiVersion = $version;
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
                'description' => $route->description,
                'responses' => []
            ];

            if (!empty($route->tags)) {
                $routeSpec['tags'] = $route->tags;
            }

            if (!empty($route->responses)) {
                foreach ($route->responses as $code => $schema) {
                    $routeSpec['responses'][$code] = [
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

        foreach ($this->schemaRegistry->getAll() as $schema) {
            if (!empty($schema->templates) && empty($schema->typeArguments)) {
                continue; // Don't generate base generic schemas
            }

            $properties = $this->resolveAllProperties($schema);
            $propSpecs = [];
            foreach ($properties as $prop) {
                $propSchema = $this->applyTypeArguments($prop->schema, $schema->typeArguments);
                if ($prop->description) {
                    $propSchema['description'] = $prop->description;
                }
                $propSpecs[$prop->name] = $this->processSchemaOutput($propSchema);
            }

            $spec['components']['schemas'][$this->schemaRegistry->getSchemaId($schema->name)] = [
                'type' => 'object',
                'properties' => $propSpecs
            ];
        }

        return Yaml::dump($spec, 10, 2);
    }

    private function processSchemaOutput(array $schema): array
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

        if ($schema->parent && $parentSchema = $this->schemaRegistry->get($schema->parent)) {
            $parentProps = $this->resolveAllProperties($parentSchema);
            foreach ($parentProps as $p) {
                $properties[$p->name] = $p;
            }
        }

        foreach ($schema->traits as $traitFqcn) {
            if ($traitSchema = $this->schemaRegistry->get($traitFqcn)) {
                $traitProps = $this->resolveAllProperties($traitSchema);
                foreach ($traitProps as $p) {
                    $properties[$p->name] = $p;
                }
            }
        }

        foreach ($schema->properties as $p) {
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
}
