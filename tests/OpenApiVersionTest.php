<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;

class OpenApiVersionTest extends TestCase
{
    public function testOpenApi30Nullable()
    {
        $dir = __DIR__ . '/fixtures/openapi30';
        @mkdir($dir, 0777, true);
        file_put_contents($dir . '/User.php', '<?php namespace App; class User { /** @var ?string */ public $email; }');

        $core = new Core();
        $core->setOpenApiVersion('3.0.0');
        $yaml = $core->generate([$dir]);

        $this->assertStringContainsString("nullable: true", $yaml);
    }

    public function testOpenApi31Nullable()
    {
        $dir = __DIR__ . '/fixtures/openapi31';
        @mkdir($dir, 0777, true);
        file_put_contents($dir . '/User.php', '<?php namespace App; class User { /** @var ?string */ public $email; }');

        $core = new Core();
        $core->setOpenApiVersion('3.1.0');
        $yaml = $core->generate([$dir]);

        $this->assertStringNotContainsString("nullable: true", $yaml);
        $this->assertStringContainsString("type:\n            - string\n            - 'null'", $yaml);
    }
}
