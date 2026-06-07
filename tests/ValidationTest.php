<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;

class ValidationTest extends TestCase
{
    public function testValidationTags()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

/**
 * @title Validation API
 * @version 1.0.0
 */
class UserController {
    /**
     * @route GET /users
     * @query int $page Page number minimum(1) default(1) example(5)
     * @query string $search Search query minLength(3) maxLength(20) pattern(^[a-z]+$)
     * @response 200 \App\Models\User
     */
    public function list($page, $search) {}
}

namespace App\Models;

class User {
    /** @var int $id User ID example(123) */
    public $id;

    /** @var string $email User email format(email) example(user@example.com) */
    public $email;

    /**
     * @var float $score User score minimum(0.0) maximum(100.0)
     */
    public $score;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = \Symfony\Component\Yaml\Yaml::parse($yaml);

        // Check parameters
        $params = $spec['paths']['/users']['get']['parameters'];

        $pageParam = array_values(array_filter($params, fn($p) => $p['name'] === 'page'))[0];
        $this->assertEquals(1, $pageParam['schema']['minimum']);
        $this->assertEquals(1, $pageParam['schema']['default']);
        $this->assertEquals(5, $pageParam['schema']['example']);
        $this->assertIsInt($pageParam['schema']['minimum']);
        $this->assertIsInt($pageParam['schema']['default']);
        $this->assertIsInt($pageParam['schema']['example']);

        $searchParam = array_values(array_filter($params, fn($p) => $p['name'] === 'search'))[0];
        $this->assertEquals(3, $searchParam['schema']['minLength']);
        $this->assertEquals(20, $searchParam['schema']['maxLength']);
        $this->assertEquals('^[a-z]+$', $searchParam['schema']['pattern']);

        // Check Schema
        $userSchema = $spec['components']['schemas']['App_Models_User'];
        $props = $userSchema['properties'];

        $this->assertEquals(123, $props['id']['example']);
        $this->assertIsInt($props['id']['example']);
        $this->assertEquals('email', $props['email']['format']);
        $this->assertEquals('user@example.com', $props['email']['example']);

        $this->assertEquals(0.0, $props['score']['minimum']);
        $this->assertEquals(100.0, $props['score']['maximum']);
        $this->assertIsFloat($props['score']['minimum']);
        $this->assertIsFloat($props['score']['maximum']);
    }
}
