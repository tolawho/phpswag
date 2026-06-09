<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;
use Symfony\Component\Yaml\Yaml;

class AttributesTest extends TestCase
{
    public function testAttributesOnlyGeneration()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

use PhpSwag\Attributes\Get;
use PhpSwag\Attributes\Tag;
use PhpSwag\Attributes\QueryParam;
use PhpSwag\Attributes\Response;
use App\Models\User;

#[Tag("Users", description: "User Operations")]
class UserController {
    #[Get("/users/{id}")]
    #[QueryParam("status", type: "string", description: "Filter by status", enum: ["active", "inactive"])]
    #[Response(200, User::class, description: "Success response")]
    public function show(int $id) {}
}

namespace App\Models;

use PhpSwag\Attributes\Schema;
use PhpSwag\Attributes\Property;

#[Schema(title: "UserModel", description: "User Response Model")]
class User {
    #[Property(description: "User unique ID", minimum: 1, required: true)]
    public int $id;

    #[Property(description: "User email", format: "email")]
    public string $email;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'phpswag-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        // Check path and operations
        $this->assertArrayHasKey('/users/{id}', $spec['paths']);
        $getOp = $spec['paths']['/users/{id}']['get'];
        $this->assertEquals(['Users'], $getOp['tags']);

        // Check query parameter
        $params = $getOp['parameters'];
        $statusParam = array_values(array_filter($params, fn($p) => $p['name'] === 'status'))[0];
        $this->assertEquals('query', $statusParam['in']);
        $this->assertEquals('string', $statusParam['schema']['type']);
        $this->assertEquals(['active', 'inactive'], $statusParam['schema']['enum']);

        // Check response mapping
        $this->assertArrayHasKey('200', $getOp['responses']);
        $this->assertEquals('Success response', $getOp['responses']['200']['description']);
        $this->assertEquals(
            '#/components/schemas/App_Models_User',
            $getOp['responses']['200']['content']['application/json']['schema']['$ref']
        );

        // Check schemas
        $this->assertArrayHasKey('App_Models_User', $spec['components']['schemas']);
        $userSchema = $spec['components']['schemas']['App_Models_User'];
        $this->assertContains('id', $userSchema['required']);

        $props = $userSchema['properties'];
        $this->assertEquals('integer', $props['id']['type']);
        $this->assertEquals(1, $props['id']['minimum']);
        $this->assertEquals('string', $props['email']['type']);
        $this->assertEquals('email', $props['email']['format']);
    }

    public function testAutoInferenceOnMethodSignature()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

use PhpSwag\Attributes\Post;
use PhpSwag\Attributes\PathParam;
use PhpSwag\Attributes\RequestBody;
use App\Models\UserRequest;

class UserController {
    #[Post("/users/{id}")]
    public function update(
        #[PathParam(description: "The path parameter ID")] int $id,
        #[RequestBody(description: "Request body data")] UserRequest $request
    ) {}
}

namespace App\Models;

class UserRequest {
    public string $name;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'phpswag-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        $postOp = $spec['paths']['/users/{id}']['post'];

        // Path param name inferred as 'id', type inferred as 'integer'
        $params = $postOp['parameters'];
        $this->assertCount(1, $params);
        $idParam = $params[0];
        $this->assertEquals('id', $idParam['name']);
        $this->assertEquals('path', $idParam['in']);
        $this->assertEquals('integer', $idParam['schema']['type']);
        $this->assertEquals('The path parameter ID', $idParam['description']);

        // RequestBody type inferred as App_Models_UserRequest
        $this->assertArrayHasKey('requestBody', $postOp);
        $this->assertEquals('Request body data', $postOp['requestBody']['description']);
        $this->assertEquals(
            '#/components/schemas/App_Models_UserRequest',
            $postOp['requestBody']['content']['application/json']['schema']['$ref']
        );
    }

    public function testSmartMergeAndOverride()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

use PhpSwag\Attributes\Get;
use PhpSwag\Attributes\Tag;
use PhpSwag\Attributes\Response;
use App\Models\User;

/**
 * @tag Accounts
 */
class UserController {
    /**
     * @route GET /users
     * @summary Old Summary
     * @description Old description
     * @tag ControllerTag
     * @response 200 \App\Models\OldUser Old response description
     * @response 400 \App\Models\ErrorResponse Bad Request
     */
    #[Get("/users")]
    #[Tag("Users")]
    #[Response(200, User::class, description: "New response description")]
    public function list() {}
}

namespace App\Models;

class User {}
class OldUser {}
class ErrorResponse {}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'phpswag-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        $getOp = $spec['paths']['/users']['get'];

        // Single-value: summary and description are preserved from docblock if not in Attribute,
        // but wait! Summary/description are not declared in attribute, so they fallback to PHPDoc.
        $this->assertEquals('Old Summary', $getOp['summary']);
        $this->assertEquals('Old description', $getOp['description']);

        // Collections: tags are merged
        $this->assertContains('Accounts', $getOp['tags']);
        $this->assertContains('ControllerTag', $getOp['tags']);
        $this->assertContains('Users', $getOp['tags']);

        // Keyed override: response 200 is overridden by Attribute, response 400 is preserved from PHPDoc
        $this->assertArrayHasKey('200', $getOp['responses']);
        $this->assertEquals('New response description', $getOp['responses']['200']['description']);
        $this->assertEquals(
            '#/components/schemas/App_Models_User',
            $getOp['responses']['200']['content']['application/json']['schema']['$ref']
        );

        $this->assertArrayHasKey('400', $getOp['responses']);
        $this->assertEquals('Bad Request', $getOp['responses']['400']['description']);
    }

    public function testSymfonyRouteMapping()
    {
        $core = new Core();
        $code = <<<'PHP'
<?php
namespace App\Controllers;

use Symfony\Component\Routing\Annotation\Route;

class ItemController {
    #[Route("/items", methods: ["GET", "POST"])]
    public function handle() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'phpswag-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);

        $this->assertArrayHasKey('/items', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/items']);
    }
}
