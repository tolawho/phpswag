<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Generator;
use PhpSwag\SchemaRegistry;
use PhpSwag\IR\RouteDefinition;
use PhpSwag\IR\SchemaDefinition;
use PhpSwag\IR\PropertyDefinition;
use Symfony\Component\Yaml\Yaml;

class GeneratorTest extends TestCase
{
    public function testGenerateBasicOpenApi()
    {
        $registry = new SchemaRegistry();
        $generator = new Generator($registry);

        $route = new RouteDefinition(
            method: 'GET',
            path: '/users',
            summary: 'List users',
            responses: ['200' => ['$ref' => '#/components/schemas/User']]
        );
        $generator->addRoute($route);

        $schema = new SchemaDefinition(
            name: 'User',
            properties: [
                new PropertyDefinition('id', ['type' => 'integer'], description: 'User ID'),
                new PropertyDefinition('name', ['type' => 'string'], description: 'User name'),
            ]
        );
        $registry->register($schema);

        $yaml = $generator->generateYaml();
        $spec = Yaml::parse($yaml);

        $this->assertEquals('3.0.0', $spec['openapi']);
        $this->assertArrayHasKey('/users', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/users']);
        $this->assertArrayHasKey('User', $spec['components']['schemas']);
        $this->assertEquals('integer', $spec['components']['schemas']['User']['properties']['id']['type']);
    }
}
