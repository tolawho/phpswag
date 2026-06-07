<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;

class MimeTypesAndAliasesTest extends TestCase
{
    public function testMimeTypesAndAliases()
    {
        $code = <<<'PHP'
<?php
namespace App\Controllers;

class UserController {
    /**
     * @route POST /users
     * @accept json
     * @produce json, xml
     * @body \App\Models\UserCreateRequest Create request
     * @success 201 \App\Models\UserCreatedResponse Created successfully
     * @failure 400 \App\Models\ErrorResponse Client side validation failure
     */
    public function create() {}
}

namespace App\Models;

class UserCreateRequest {
    /** @var string */
    public $name;
}

class UserCreatedResponse {
    /** @var int */
    public $id;
}

class ErrorResponse {
    /** @var string */
    public $message;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-mimes-test');
        file_put_contents($tempFile, $code);

        $core = new Core();
        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = \Symfony\Component\Yaml\Yaml::parse($yaml);

        // Check accept / requestBody MIME types
        $requestBody = $spec['paths']['/users']['post']['requestBody'];
        $this->assertArrayHasKey('application/json', $requestBody['content']);

        // Check produce / response MIME types
        $responses = $spec['paths']['/users']['post']['responses'];
        $this->assertArrayHasKey('201', $responses);
        $this->assertEquals('Created successfully', $responses['201']['description']);
        $this->assertArrayHasKey('application/json', $responses['201']['content']);
        $this->assertArrayHasKey('application/xml', $responses['201']['content']);

        $this->assertArrayHasKey('400', $responses);
        $this->assertEquals('Client side validation failure', $responses['400']['description']);
        $this->assertArrayHasKey('application/json', $responses['400']['content']);
        $this->assertArrayHasKey('application/xml', $responses['400']['content']);
    }
}
