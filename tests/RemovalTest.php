<?php

namespace PhpSwag\Tests;

use PhpSwag\Core;
use PHPUnit\Framework\TestCase;

class RemovalTest extends TestCase
{
    public function testParamTagIsIgnored()
    {
        $code = '<?php
        namespace App\Controllers;
        class UserController {
            /**
             * @route GET /users
             * @param string $legacy This should be ignored
             */
            public function index() {}
        }';

        $filePath = __DIR__ . "/fixtures/LegacyController.php";
        if (!is_dir(dirname($filePath))) mkdir(dirname($filePath), 0777, true);
        file_put_contents($filePath, $code);

        $core = new Core();
        $yaml = $core->generateYaml([dirname($filePath)]);
        unlink($filePath);

        $this->assertStringNotContainsString("name: legacy", $yaml);
    }

    public function testRequestTagIsIgnored()
    {
        $code = '<?php
        namespace App\Controllers;
        class UserDTO {}
        class UserController {
            /**
             * @route POST /users
             * @request UserDTO This should be ignored
             */
            public function store() {}
        }';

        $filePath = __DIR__ . "/fixtures/LegacyRequestController.php";
        if (!is_dir(dirname($filePath))) mkdir(dirname($filePath), 0777, true);
        file_put_contents($filePath, $code);

        $core = new Core();
        $yaml = $core->generateYaml([dirname($filePath)]);
        unlink($filePath);

        $this->assertStringNotContainsString("requestBody:", $yaml);
    }
}
