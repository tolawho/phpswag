<?php

namespace PhpSwag;

use Symfony\Component\Yaml\Yaml;
use PhpSwag\IR\RouteDefinition;
use PhpSwag\IR\SchemaDefinition;

class Generator
{
    private array $routes = [];
    private array $schemas = [];

    public function addRoute(RouteDefinition $route): void
    {
        $this->routes[] = $route;
    }

    public function addSchema(SchemaDefinition $schema): void
    {
        $this->schemas[$schema->name] = $schema;
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

            $spec['paths'][$path][$method] = [
                'summary' => $route->summary,
                'description' => $route->description,
                'tags' => $route->tags,
                'responses' => []
            ];

            if (!empty($route->responses)) {
                foreach ($route->responses as $code => $ref) {
                    $spec['paths'][$path][$method]['responses'][$code] = [
                        'description' => 'OK',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/' . str_replace('\\', '_', $ref)
                                ]
                            ]
                        ]
                    ];
                }
            } else {
                $spec['paths'][$path][$method]['responses']['200'] = [
                    'description' => 'OK'
                ];
            }
        }

        foreach ($this->schemas as $schema) {
            $properties = [];
            foreach ($schema->properties as $prop) {
                $properties[$prop->name] = [
                    'type' => $this->mapType($prop->type),
                    'description' => $prop->description
                ];
                if ($prop->isNullable) {
                    $properties[$prop->name]['nullable'] = true;
                }
            }

            $spec['components']['schemas'][str_replace('\\', '_', $schema->name)] = [
                'type' => 'object',
                'properties' => $properties
            ];
        }

        return Yaml::dump($spec, 10, 2);
    }

    private function mapType(string $type): string
    {
        return match ($type) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }
}
