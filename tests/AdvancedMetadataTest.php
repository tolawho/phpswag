<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;

class AdvancedMetadataTest extends TestCase
{
    public function testAdvancedMetadata()
    {
        $code = <<<'PHP'
<?php
namespace App\Controllers;

class UserController {
    /**
     * @route GET /users
     * @operationId fetchUsersList
     * @deprecated
     * @x-code-samples [{"lang": "PHP", "source": "$api->getUsers();"}]
     * @response 200 string
     */
    public function list() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'phpswag-metadata-test');
        file_put_contents($tempFile, $code);

        $core = new Core();
        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = \Symfony\Component\Yaml\Yaml::parse($yaml);
        $routeSpec = $spec['paths']['/users']['get'];

        $this->assertEquals('fetchUsersList', $routeSpec['operationId']);
        $this->assertTrue($routeSpec['deprecated']);
        $this->assertArrayHasKey('x-code-samples', $routeSpec);
        $this->assertIsArray($routeSpec['x-code-samples']);
        $this->assertEquals('PHP', $routeSpec['x-code-samples'][0]['lang']);
        $this->assertEquals('$api->getUsers();', $routeSpec['x-code-samples'][0]['source']);
    }
}
