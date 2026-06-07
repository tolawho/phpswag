<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;
use PhpSwag\Exception\DiagnosticException;

class DiagnosticsTest extends TestCase
{
    public function testSourceLocationTracking()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

/**
 * @title Test API
 * @version 1.0.0
 */
class TestController {
    /**
     * @route GET /test
     * @response 200 \App\Models\TestModel
     */
    public function getTest() {}
}

namespace App\Models;

class TestModel {
    /** @var string $name Name of test */
    public $name;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-diagnostics-test');
        file_put_contents($tempFile, $code);

        $core->generateYaml([$tempFile]);

        // Use Reflection to inspect private components
        $reflection = new \ReflectionClass($core);

        $registryProp = $reflection->getProperty('schemaRegistry');
        $registryProp->setAccessible(true);
        $registry = $registryProp->getValue($core);

        $generatorProp = $reflection->getProperty('generator');
        $generatorProp->setAccessible(true);
        $generator = $generatorProp->getValue($core);

        // Verify Schema Location
        $schema = $registry->get('App\Models\TestModel');
        $this->assertNotNull($schema);
        $this->assertEquals($tempFile, $schema->file);
        $this->assertEquals(18, $schema->line);

        // Verify Property Location
        $this->assertCount(1, $schema->properties);
        $prop = $schema->properties[0];
        $this->assertEquals('name', $prop->name);
        $this->assertEquals($tempFile, $prop->file);
        // Let's count line: /** @var string $name Name of test */ is on line 19
        $this->assertEquals(19, $prop->line);

        // Verify Route Location
        $routes = $generator->getRoutes();
        $this->assertCount(1, $routes);
        $route = $routes[0];
        $this->assertEquals($tempFile, $route->file);
        $this->assertEquals(13, $route->line); // line of public function getTest() {}

        unlink($tempFile);
    }

    public function testUnresolvedClassThrowsException()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

class TestController {
    /**
     * @route GET /test
     * @response 200 \App\Models\NonExistentModel
     */
    public function getTest() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-unresolved-test');
        file_put_contents($tempFile, $code);

        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage("Unresolved class 'App\Models\NonExistentModel'");
        $this->expectExceptionMessage("on line 7");

        try {
            $core->generateYaml([$tempFile]);
        } finally {
            unlink($tempFile);
        }
    }

    public function testInvalidRouteSyntaxThrowsException()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

class TestController {
    /**
     * @route /invalid-route-no-method
     */
    public function getTest() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-invalid-route');
        file_put_contents($tempFile, $code);

        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage("Invalid syntax for tag '@route'");
        $this->expectExceptionMessage("/invalid-route-no-method");

        try {
            $core->generateYaml([$tempFile]);
        } finally {
            unlink($tempFile);
        }
    }

    public function testInvalidResponseSyntaxThrowsException()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

class TestController {
    /**
     * @route GET /test
     * @response 200
     */
    public function getTest() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-invalid-response');
        file_put_contents($tempFile, $code);

        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage("Invalid syntax for tag '@response'");
        $this->expectExceptionMessage("on line 7");

        try {
            $core->generateYaml([$tempFile]);
        } finally {
            unlink($tempFile);
        }
    }

    public function testInvalidTagSyntaxThrowsException()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

class TestController {
    /**
     * @route GET /test
     * @query string
     */
    public function getTest($name) {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-invalid-tag');
        file_put_contents($tempFile, $code);

        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage("Invalid syntax for tag '@query'");
        $this->expectExceptionMessage("expected format is '@query TYPE \$name'");

        try {
            $core->generateYaml([$tempFile]);
        } finally {
            unlink($tempFile);
        }
    }
}
