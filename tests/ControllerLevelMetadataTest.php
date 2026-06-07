<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;
use Symfony\Component\Yaml\Yaml;

class ControllerLevelMetadataTest extends TestCase
{
    public function testControllerLevelTags(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Controllers;

/**
 * @tag ClassTag1
 * @tag ClassTag2
 * @tag SharedTag
 */
class Controller {
    /**
     * @route GET /endpoint1
     * @tag MethodTag1
     * @tag SharedTag
     */
    public function endpoint1() {}

    /**
     * @route GET /endpoint2
     */
    public function endpoint2() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-controller-tags');
        file_put_contents($tempFile, $code);

        $core = new Core();
        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        // endpoint1 should have both ClassTags, SharedTag and MethodTag1 (uniquely)
        $route1 = $spec['paths']['/endpoint1']['get'];
        $this->assertEquals(['ClassTag1', 'ClassTag2', 'SharedTag', 'MethodTag1'], $route1['tags']);

        // endpoint2 should have only ClassTags and SharedTag
        $route2 = $spec['paths']['/endpoint2']['get'];
        $this->assertEquals(['ClassTag1', 'ClassTag2', 'SharedTag'], $route2['tags']);

        // Global tags should be collected and sorted alphabetically
        $this->assertArrayHasKey('tags', $spec);
        $expectedGlobalTags = [
            ['name' => 'ClassTag1'],
            ['name' => 'ClassTag2'],
            ['name' => 'MethodTag1'],
            ['name' => 'SharedTag']
        ];
        $this->assertEquals($expectedGlobalTags, $spec['tags']);
    }

    public function testCommaSeparatedTags(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Controllers;

/**
 * @tag ClassTag1, ClassTag2, SharedTag
 */
class Controller {
    /**
     * @route GET /endpoint1
     * @tag MethodTag1, SharedTag
     */
    public function endpoint1() {}

    /**
     * @route GET /endpoint2
     */
    public function endpoint2() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-controller-comma-tags');
        file_put_contents($tempFile, $code);

        $core = new Core();
        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        // endpoint1 should have both ClassTags, SharedTag and MethodTag1 (uniquely)
        $route1 = $spec['paths']['/endpoint1']['get'];
        $this->assertEquals(['ClassTag1', 'ClassTag2', 'SharedTag', 'MethodTag1'], $route1['tags']);

        // endpoint2 should have only ClassTags and SharedTag
        $route2 = $spec['paths']['/endpoint2']['get'];
        $this->assertEquals(['ClassTag1', 'ClassTag2', 'SharedTag'], $route2['tags']);
    }

    public function testControllerLevelSecurity(): void
    {
        $code = <<<'PHP'
<?php
/**
 * @securityDefinitions.apikey MyApiKey header X-API-KEY
 * @securityDefinitions.jwt MyJwtAuth
 */

namespace App\Controllers;

/**
 * @security MyApiKey
 */
class Controller {
    /**
     * @route GET /secure-default
     */
    public function secureDefault() {}

    /**
     * @route GET /secure-override
     * @security MyJwtAuth
     */
    public function secureOverride() {}

    /**
     * @route GET /no-security-override
     * @security
     */
    public function noSecurityOverride() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-controller-security');
        file_put_contents($tempFile, $code);

        $core = new Core();
        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        // secure-default should inherit MyApiKey
        $route1 = $spec['paths']['/secure-default']['get'];
        $this->assertEquals([['MyApiKey' => []]], $route1['security']);

        // secure-override should override to MyJwtAuth
        $route2 = $spec['paths']['/secure-override']['get'];
        $this->assertEquals([['MyJwtAuth' => []]], $route2['security']);

        // no-security-override should override to no security (empty array or [] in yaml)
        $route3 = $spec['paths']['/no-security-override']['get'];
        $this->assertEquals([[]], $route3['security']);
    }

    public function testControllerLevelAcceptProduce(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Controllers;

/**
 * @accept json
 * @produce xml
 */
class Controller {
    /**
     * @route POST /default-mimes
     * @body \App\Models\ReqBody Request Body
     * @response 200 \App\Models\RespBody Response Body
     */
    public function defaultMimes() {}

    /**
     * @route POST /override-mimes
     * @accept xml
     * @produce json
     * @body \App\Models\ReqBody Request Body
     * @response 200 \App\Models\RespBody Response Body
     */
    public function overrideMimes() {}
}

namespace App\Models;

class ReqBody {
    /** @var string */
    public $name;
}

class RespBody {
    /** @var string */
    public $message;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-controller-mimes');
        file_put_contents($tempFile, $code);

        $core = new Core();
        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        // default-mimes
        $route1 = $spec['paths']['/default-mimes']['post'];
        $this->assertArrayHasKey('application/json', $route1['requestBody']['content']);
        $this->assertArrayNotHasKey('application/xml', $route1['requestBody']['content']);
        $this->assertArrayHasKey('application/xml', $route1['responses']['200']['content']);
        $this->assertArrayNotHasKey('application/json', $route1['responses']['200']['content']);

        // override-mimes
        $route2 = $spec['paths']['/override-mimes']['post'];
        $this->assertArrayHasKey('application/xml', $route2['requestBody']['content']);
        $this->assertArrayNotHasKey('application/json', $route2['requestBody']['content']);
        $this->assertArrayHasKey('application/json', $route2['responses']['200']['content']);
        $this->assertArrayNotHasKey('application/xml', $route2['responses']['200']['content']);
    }

    public function testGlobalTagOrdering(): void
    {
        $code = <<<'PHP'
<?php
/**
 * @title Global Tag Ordering Test
 * @version 1.0.0
 * @tag.name Users User management endpoints
 * @tag.name Auth Authentication endpoints
 */

namespace App\Controllers;

class Controller {
    /**
     * @route GET /auth
     * @tag Auth
     */
    public function auth() {}

    /**
     * @route GET /users
     * @tag Users
     */
    public function users() {}

    /**
     * @route GET /extra
     * @tag Zebra
     * @tag Apple
     */
    public function extra() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-global-tags-order');
        file_put_contents($tempFile, $code);

        $core = new Core();
        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        $this->assertArrayHasKey('tags', $spec);
        $expectedGlobalTags = [
            ['name' => 'Users', 'description' => 'User management endpoints'],
            ['name' => 'Auth', 'description' => 'Authentication endpoints'],
            ['name' => 'Apple'],
            ['name' => 'Zebra']
        ];
        $this->assertEquals($expectedGlobalTags, $spec['tags']);
    }
}
