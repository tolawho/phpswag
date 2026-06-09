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

    public function testEmptyPropertiesAndSecuritySerialization()
    {
        $registry = new SchemaRegistry();
        $generator = new Generator($registry);

        // Add a route with an empty security scope (api_key) and a response with void type
        $route = new RouteDefinition(
            method: 'GET',
            path: '/users',
            summary: 'List users',
            responses: ['200' => []],
            security: [['api_key' => []]]
        );
        $generator->addRoute($route);

        // Add a schema with no properties (like a controller)
        $schema = new SchemaDefinition(
            name: 'EmptyController',
            properties: []
        );
        $registry->register($schema);

        // 1. Verify YAML output
        $yaml = $generator->generateYaml();

        // Assert yaml contains properties: {  } or properties: {} (should be object mapping)
        $this->assertStringContainsString('properties: {  }', $yaml);
        // Assert yaml contains api_key: [  ] (should be list/sequence)
        $this->assertStringContainsString('api_key: [  ]', $yaml);

        // 2. Verify JSON output
        $json = $generator->generateJson();
        $decodedJson = json_decode($json, true);

        // Properties must be an object
        $this->assertArrayHasKey('EmptyController', $decodedJson['components']['schemas']);
        $this->assertEquals([], $decodedJson['components']['schemas']['EmptyController']['properties']);
        $this->assertIsArray($decodedJson['components']['schemas']['EmptyController']['properties']);
        // But in raw JSON it should be {} (PHP Decodes empty object as empty array unless cast/flag, so let's check raw JSON string)
        $this->assertStringContainsString('"properties": {}', $json);

        // Schema must be an object
        $this->assertStringContainsString('"schema": {}', $json);

        // Security scope must be an array
        $this->assertStringContainsString('"api_key": []', $json);
    }
}
