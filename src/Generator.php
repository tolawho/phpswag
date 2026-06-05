<?php

namespace PhpSwag;

use Symfony\Component\Yaml\Yaml;
use PhpSwag\IR\RouteDefinition;
use PhpSwag\IR\SchemaDefinition;

class Generator
{
    private array $routes = [];
    private SchemaRegistry $schemaRegistry;

    public function __construct(SchemaRegistry $schemaRegistry)
    {
        $this->schemaRegistry = $schemaRegistry;
    }

    public function addRoute(RouteDefinition $route): void
    {
        $this->routes[] = $route;
    }

    public function generateYaml(): string
    {
        $spec = [
            'openapi' => '3.0.0',
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
                                'schema' => $schema
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
            $properties = [];
            foreach ($schema->properties as $prop) {
                $propSchema = $prop->schema;
                if ($prop->description) {
                    $propSchema['description'] = $prop->description;
                }
                $properties[$prop->name] = $propSchema;
            }

            $spec['components']['schemas'][$this->schemaRegistry->getSchemaId($schema->name)] = [
                'type' => 'object',
                'properties' => $properties
            ];
        }

        return Yaml::dump($spec, 10, 2);
    }
}
