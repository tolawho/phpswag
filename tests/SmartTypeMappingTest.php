<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;
use PhpSwag\SchemaRegistry;
use PhpSwag\NameResolver;
use PhpSwag\TypeResolver;
use PhpSwag\TypeMappingRegistry;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Symfony\Component\Yaml\Yaml;

class SmartTypeMappingTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Declare dummy external classes to test class_exists/interface_exists logic
        if (!class_exists('Ramsey\Uuid\Uuid')) {
            eval('namespace Ramsey\Uuid { class Uuid {} interface UuidInterface {} }');
        }
        if (!class_exists('Symfony\Component\Uid\Uuid')) {
            eval('namespace Symfony\Component\Uid { class Uuid {} }');
        }
    }

    public function testBuiltInDateTimeMapping()
    {
        $registry = new SchemaRegistry();
        $nameResolver = new NameResolver();
        $typeResolver = new TypeResolver($registry, $nameResolver);

        $node = new IdentifierTypeNode('DateTime');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'date-time'], $resolved);

        $node = new IdentifierTypeNode('DateTimeImmutable');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'date-time'], $resolved);

        $node = new IdentifierTypeNode('DateTimeInterface');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'date-time'], $resolved);
    }

    public function testUploadedFileMapping()
    {
        $registry = new SchemaRegistry();
        $nameResolver = new NameResolver();
        $typeResolver = new TypeResolver($registry, $nameResolver);

        $node = new IdentifierTypeNode('Symfony\Component\HttpFoundation\File\UploadedFile');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'binary'], $resolved);

        $node = new IdentifierTypeNode('Psr\Http\Message\UploadedFileInterface');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'binary'], $resolved);

        $node = new IdentifierTypeNode('Illuminate\Http\UploadedFile');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'binary'], $resolved);
    }

    public function testUuidMapping()
    {
        $registry = new SchemaRegistry();
        $nameResolver = new NameResolver();
        $typeResolver = new TypeResolver($registry, $nameResolver);

        $node = new IdentifierTypeNode('Ramsey\Uuid\Uuid');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'uuid'], $resolved);

        $node = new IdentifierTypeNode('Ramsey\Uuid\UuidInterface');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'uuid'], $resolved);

        $node = new IdentifierTypeNode('Symfony\Component\Uid\Uuid');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'string', 'format' => 'uuid'], $resolved);
    }

    public function testCustomMapping()
    {
        $registry = new SchemaRegistry();
        $nameResolver = new NameResolver();
        $mappingRegistry = new TypeMappingRegistry();
        $mappingRegistry->register('App\ValueObjects\Money', ['type' => 'number', 'format' => 'money']);

        $typeResolver = new TypeResolver($registry, $nameResolver, [], $mappingRegistry);

        $node = new IdentifierTypeNode('App\ValueObjects\Money');
        $resolved = $typeResolver->resolve($node);
        $this->assertEquals(['type' => 'number', 'format' => 'money'], $resolved);
    }

    public function testIntegrationWithGenerator()
    {
        $core = new Core();

        // Register custom mapping in core's registry
        $core->getTypeMappingRegistry()->register('App\Models\CustomToken', ['type' => 'string', 'format' => 'jwt']);

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
     * @response 200 \App\Models\ModelWithMappedTypes
     */
    public function getTest() {}
}

namespace App\Models;

class ModelWithMappedTypes {
    /** @var \DateTime $createdAt */
    public \DateTime $createdAt;

    /** @var \Ramsey\Uuid\Uuid $id */
    public $id;

    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $avatar */
    public $avatar;

    /** @var \App\Models\CustomToken $token */
    public $token;
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'php-swag-mapping-test');
        file_put_contents($tempFile, $code);

        $yaml = $core->generateYaml([$tempFile]);
        unlink($tempFile);

        $spec = Yaml::parse($yaml);
        $schema = $spec['components']['schemas']['App_Models_ModelWithMappedTypes'];

        $this->assertEquals(['type' => 'string', 'format' => 'date-time'], $schema['properties']['createdAt']);
        $this->assertEquals(['type' => 'string', 'format' => 'uuid'], $schema['properties']['id']);
        $this->assertEquals(['type' => 'string', 'format' => 'binary'], $schema['properties']['avatar']);
        $this->assertEquals(['type' => 'string', 'format' => 'jwt'], $schema['properties']['token']);
    }
}
