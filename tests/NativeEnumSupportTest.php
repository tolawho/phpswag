<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;
use Symfony\Component\Yaml\Yaml;

/**
 * @requires PHP 8.1
 */
class NativeEnumSupportTest extends TestCase
{
    public function testBackedStringEnumSupport()
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
     * @route GET /test-string
     * @response 200 \PhpSwag\Tests\fixtures\enums\BackedStringEnum
     */
    public function getTest() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-enum-test');
        file_put_contents($tempFile, $code);

        // We also need the generator to scan the Enum fixture file so that it is registered in the SchemaRegistry!
        $yaml = $core->generateYaml([
            $tempFile,
            __DIR__ . '/fixtures/enums/BackedStringEnum.php'
        ]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);
        $schema = $spec['components']['schemas']['PhpSwag_Tests_fixtures_enums_BackedStringEnum'];

        $this->assertEquals('string', $schema['type']);
        $this->assertEquals(['H', 'S'], $schema['enum']);
    }

    public function testBackedIntEnumSupport()
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
     * @route GET /test-int
     * @response 200 \PhpSwag\Tests\fixtures\enums\BackedIntEnum
     */
    public function getTest() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-enum-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([
            $tempFile,
            __DIR__ . '/fixtures/enums/BackedIntEnum.php'
        ]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);
        $schema = $spec['components']['schemas']['PhpSwag_Tests_fixtures_enums_BackedIntEnum'];

        $this->assertEquals('integer', $schema['type']);
        $this->assertEquals([1, 0], $schema['enum']);
    }

    public function testPureUnitEnumSupport()
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
     * @route GET /test-unit
     * @response 200 \PhpSwag\Tests\fixtures\enums\PureUnitEnum
     */
    public function getTest() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-enum-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([
            $tempFile,
            __DIR__ . '/fixtures/enums/PureUnitEnum.php'
        ]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);
        $schema = $spec['components']['schemas']['PhpSwag_Tests_fixtures_enums_PureUnitEnum'];

        $this->assertEquals('string', $schema['type']);
        $this->assertEquals(['Pending', 'Approved', 'Rejected'], $schema['enum']);
    }
}
