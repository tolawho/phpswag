<?php

namespace PhpSwag\Tests;

use PhpSwag\Core;
use PHPUnit\Framework\TestCase;

class RouteParamsTest extends TestCase
{
    public function testExplicitParamTags()
    {
        $code = '<?php
        namespace App\Controllers;
        class UserController {
            /**
             * @route GET /users/{id}
             * @path int $id The user ID
             * @query string $status Filter by status enum(active,inactive) default(active)
             * @header string $X-API-KEY API Access Key
             * @cookie string $session_id Session Identifier
             */
            public function show($id) {}
        }';

        $filePath = __DIR__ . "/fixtures/RouteParamsController.php";
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        file_put_contents($filePath, $code);

        $core = new Core();
        $yaml = $core->generate([dirname($filePath)]);
        unlink($filePath);

        $this->assertStringContainsString("name: id", $yaml);
        $this->assertStringContainsString("in: path", $yaml);
        $this->assertStringContainsString("description: 'The user ID'", $yaml);

        $this->assertStringContainsString("name: status", $yaml);
        $this->assertStringContainsString("in: query", $yaml);
        $this->assertStringContainsString("enum:", $yaml);
        $this->assertStringContainsString("- active", $yaml);
        $this->assertStringContainsString("- inactive", $yaml);
        $this->assertStringContainsString("default: active", $yaml);

        $this->assertStringContainsString("in: header", $yaml);

        $this->assertStringContainsString("name: session_id", $yaml);
        $this->assertStringContainsString("in: cookie", $yaml);
    }

    public function testRequestBodyTag()
    {
        $code = '<?php
        namespace App\Controllers;
        /** @property string $name */
        class CreateUserRequest {}

        class UserController {
            /**
             * @route POST /users
             * @body CreateUserRequest User data to create
             */
            public function store() {}
        }';

        $filePath = __DIR__ . "/fixtures/RequestBodyController.php";
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        file_put_contents($filePath, $code);

        $core = new Core();
        $yaml = $core->generate([dirname($filePath)]);
        unlink($filePath);

        $this->assertStringContainsString("requestBody:", $yaml);
        $this->assertStringContainsString("description: 'User data to create'", $yaml);
        $this->assertStringContainsString("\$ref: '#/components/schemas/App_Controllers_CreateUserRequest'", $yaml);
    }

    public function testAutoInference()
    {
        $code = '<?php
        namespace App\Controllers;
        /** @property string $name */
        class UserDTO {}

        class UserController {
            /**
             * @route PUT /users/{id}
             */
            public function update(int $id, UserDTO $data, string $reason) {}
        }';

        $filePath = __DIR__ . "/fixtures/AutoInferenceController.php";
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        file_put_contents($filePath, $code);

        $core = new Core();
        $yaml = $core->generate([dirname($filePath)]);
        unlink($filePath);

        // id should be in path
        $this->assertMatchesRegularExpression("/name: id\s+in: path/s", $yaml);
        // reason should be in query
        $this->assertMatchesRegularExpression("/name: reason\s+in: query/s", $yaml);
        // data should be requestBody
        $this->assertStringContainsString("requestBody:", $yaml);
        $this->assertStringContainsString("\$ref: '#/components/schemas/App_Controllers_UserDTO'", $yaml);
    }

    public function testEnumAndDefaultAttributes()
    {
         $code = '<?php
        namespace App\Controllers;
        class ProductController {
            /**
             * @route GET /products
             * @query string $sort Sort order enum(asc,desc) default(asc)
             */
            public function index($sort) {}
        }';

        $filePath = __DIR__ . "/fixtures/EnumDefaultController.php";
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        file_put_contents($filePath, $code);

        $core = new Core();
        $yaml = $core->generate([dirname($filePath)]);
        unlink($filePath);

        $this->assertStringContainsString("name: sort", $yaml);
        $this->assertStringContainsString("enum:", $yaml);
        $this->assertStringContainsString("- asc", $yaml);
        $this->assertStringContainsString("- desc", $yaml);
        $this->assertStringContainsString("default: asc", $yaml);
    }
}
