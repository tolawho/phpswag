<?php

namespace PhpSwag;

use PhpSwag\IR\RouteDefinition;
use PhpSwag\IR\SchemaDefinition;
use Symfony\Component\Yaml\Yaml;

class Generator
{
    private array $routes = [];
    private SchemaRegistry $schemaRegistry;
    private string $openApiVersion = '3.0.0';
    private bool $filterUnusedSchemas = false;
    private string $title = 'API Documentation';
    private string $apiVersion = '1.0.0';
    private ?string $description = null;
    private ?array $contact = null;
    private ?array $license = null;
    private array $servers = [];
    private array $securitySchemes = [];
    private array $globalSecurity = [];

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

    public function setContact(?array $contact): void
    {
        $this->contact = $contact;
    }

    public function setLicense(?array $license): void
    {
        $this->license = $license;
    }

    public function setServers(array $servers): void
    {
        $this->servers = $servers;
    }

    public function setSecuritySchemes(array $schemes): void
    {
        $this->securitySchemes = $schemes;
    }

    public function setGlobalSecurity(array $security): void
    {
        $this->globalSecurity = $security;
    }

    public function addRoute(RouteDefinition $route): void
    {
        $this->routes[] = $route;
    }

    public function generateYaml(): string
    {
        return Yaml::dump($this->generateSpec(), 10, 2);
    }

    public function generateJson(): string
    {
        return json_encode($this->generateSpec(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

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

                    // Handle enum and default from extra metadata if present
                    if (isset($param['enum'])) {
                        $schema['enum'] = $param['enum'];
                    }
                    if (isset($param['default'])) {
                        $schema['default'] = $param['default'];
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
                $routeSpec['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => $this->processSchemaOutput($route->requestBody['schema'])
                        ]
                    ]
                ];
                if (!empty($route->requestBody['description'])) {
                    $routeSpec['requestBody']['description'] = $route->requestBody['description'];
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

        return $spec;
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
}
