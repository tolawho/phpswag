<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;
use Symfony\Component\Yaml\Yaml;

class IntelligentSchemaInferenceTest extends TestCase
{
    public function testNullableBasedRequiredDetection()
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
     * @response 200 \App\Models\UserModel
     */
    public function getTest() {}
}

namespace App\Models;

class UserModel {
    /** @var string $requiredProp */
    public string $requiredProp;

    /** @var string $optionalProp */
    public ?string $optionalProp;

    /** @var string $optionalUnionProp */
    public string|null $optionalUnionProp;

    /** @var mixed $optionalMixedProp */
    public mixed $optionalMixedProp;

    /** @var string $docRequiredProp */
    public $docRequiredProp;

    /** @var string|null $docOptionalProp */
    public $docOptionalProp;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-inference-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);
        $userSchema = $spec['components']['schemas']['App_Models_UserModel'];

        $this->assertArrayHasKey('required', $userSchema);
        $required = $userSchema['required'];

        // Should contain requiredProp and docRequiredProp
        $this->assertContains('requiredProp', $required);
        $this->assertContains('docRequiredProp', $required);

        // Should NOT contain optionalProp, optionalUnionProp, optionalMixedProp, docOptionalProp
        $this->assertNotContains('optionalProp', $required);
        $this->assertNotContains('optionalUnionProp', $required);
        $this->assertNotContains('optionalMixedProp', $required);
        $this->assertNotContains('docOptionalProp', $required);
    }

    public function testDefaultValueInference()
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
     * @response 200 \App\Models\DefaultModel
     */
    public function getTest() {}
}

namespace App\Models;

class DefaultModel {
    /** @var string $propWithNativeDefault */
    public string $propWithNativeDefault = 'default';

    /** @var string $propWithDocDefault default("doc") */
    public string $propWithDocDefault;

    /** @var string $propWithNoDefault */
    public string $propWithNoDefault;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-default-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);
        $schema = $spec['components']['schemas']['App_Models_DefaultModel'];

        $this->assertArrayHasKey('required', $schema);
        $required = $schema['required'];

        // Only propWithNoDefault should be required
        $this->assertContains('propWithNoDefault', $required);
        $this->assertNotContains('propWithNativeDefault', $required);
        $this->assertNotContains('propWithDocDefault', $required);
    }

    public function testExplicitRequiredTagOnProperties()
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
     * @response 200 \App\Models\ExplicitModel
     */
    public function getTest() {}
}

namespace App\Models;

class ExplicitModel {
    /**
     * @var string $propWithStandaloneRequired
     * @required
     */
    public ?string $propWithStandaloneRequired;

    /**
     * @var string $propWithStandaloneRequiredFalse
     * @required false
     */
    public string $propWithStandaloneRequiredFalse;

    /** @var string $propWithInlineRequired This is inline @required */
    public ?string $propWithInlineRequired;

    /** @var string $normalProp */
    public ?string $normalProp;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-explicit-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);
        $schema = $spec['components']['schemas']['App_Models_ExplicitModel'];

        $this->assertArrayHasKey('required', $schema);
        $required = $schema['required'];

        // propWithStandaloneRequired and propWithInlineRequired should be required
        $this->assertContains('propWithStandaloneRequired', $required);
        $this->assertContains('propWithInlineRequired', $required);

        // propWithStandaloneRequiredFalse and normalProp should NOT be required
        $this->assertNotContains('propWithStandaloneRequiredFalse', $required);
        $this->assertNotContains('normalProp', $required);

        // Cleaned description check: "@required" should be stripped from description
        $props = $schema['properties'];
        $this->assertEquals('This is inline', $props['propWithInlineRequired']['description']);
    }

    public function testClassLevelPropertyRequiredTags()
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
     * @response 200 \App\Models\ClassLevelModel
     */
    public function getTest() {}
}

namespace App\Models;

/**
 * @property string $propInlineRequired Inline property description @required
 * @property string $propStandaloneRequired Target property
 * @property string $propStandaloneRequiredFalse Target property false
 * @property string $propNormal Normal property
 * @property ?string $propNullable Nullable property
 * @required $propStandaloneRequired
 * @required $propStandaloneRequiredFalse false
 */
class ClassLevelModel {}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-class-level-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);
        $schema = $spec['components']['schemas']['App_Models_ClassLevelModel'];

        $this->assertArrayHasKey('required', $schema);
        $required = $schema['required'];

        // propInlineRequired, propStandaloneRequired and propNormal should be required
        // (propNormal is required because its type-hint 'string' is not nullable and it has no default value)
        $this->assertContains('propInlineRequired', $required);
        $this->assertContains('propStandaloneRequired', $required);
        $this->assertContains('propNormal', $required);

        // propStandaloneRequiredFalse and propNullable should NOT be required
        $this->assertNotContains('propStandaloneRequiredFalse', $required);
        $this->assertNotContains('propNullable', $required);

        // Cleaned description check: "@required" should be stripped from description
        $props = $schema['properties'];
        $this->assertEquals('Inline property description', $props['propInlineRequired']['description']);
    }
}
