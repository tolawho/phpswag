<?php

namespace PhpSwag\Tests;

use PhpSwag\Core;
use PHPUnit\Framework\TestCase;

class GlobalMetadataTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/fixtures/global_metadata';
        if (!is_dir($this->fixtureDir)) {
            mkdir($this->fixtureDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
    }

    private function removeDirectory($path): void
    {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        rmdir($path);
    }

    public function testGlobalMetadataDiscovery(): void
    {
        $code = <<<'PHP'
<?php
/**
 * @title My Awesome API
 * @version 2.1.0
 * @description This is a sample API for testing global metadata.
 * @contact.name John Doe
 * @contact.email john@example.com
 * @license.name MIT
 * @license.url https://opensource.org/licenses/MIT
 * @host https://api.example.com
 */

namespace App;

class Controller {
    /**
     * @route GET /hello
     */
    public function hello() {}
}
PHP;
        file_put_contents($this->fixtureDir . '/api.php', $code);

        $core = new Core();
        $yaml = $core->generateYaml([$this->fixtureDir]);

        $this->assertStringContainsString('title: My Awesome API', str_replace("'", "", $yaml));
        $this->assertStringContainsString('version: 2.1.0', str_replace("'", "", $yaml));
        $this->assertStringContainsString('description: This is a sample API for testing global metadata.', str_replace("'", "", $yaml));
        $this->assertStringContainsString('name: John Doe', str_replace("'", "", $yaml));
        $this->assertStringContainsString('email: john@example.com', str_replace("'", "", $yaml));
        $this->assertStringContainsString('name: MIT', str_replace("'", "", $yaml));
        $this->assertStringContainsString('url: https://opensource.org/licenses/MIT', str_replace("'", "", $yaml));
        $this->assertStringContainsString('servers:', $yaml);
        $this->assertStringContainsString('url: https://api.example.com', str_replace("'", "", $yaml));
    }

    public function testDuplicateGlobalMetadataThrowsException(): void
    {
        $code1 = <<<'PHP'
<?php
/**
 * @title First Title
 */
PHP;
        $code2 = <<<'PHP'
<?php
/**
 * @title Second Title
 */
PHP;
        file_put_contents($this->fixtureDir . '/file1.php', $code1);
        file_put_contents($this->fixtureDir . '/file2.php', $code2);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Duplicate global tag '@title' found");

        $core = new Core();
        $core->generateYaml([$this->fixtureDir]);
    }

    public function testCliOverridePrioritized(): void
    {
         $code = <<<'PHP'
<?php
/**
 * @title Doc Title
 */
PHP;
        file_put_contents($this->fixtureDir . '/api.php', $code);

        $core = new Core();
        $core->setTitle('CLI Title');
        $yaml = $core->generateYaml([$this->fixtureDir]);

        $this->assertStringContainsString('title: CLI Title', str_replace("'", "", $yaml));
        $this->assertStringNotContainsString('title: Doc Title', str_replace("'", "", $yaml));
    }

    public function testMultipleServersDiscovery(): void
    {
        $code = <<<'PHP'
<?php
/**
 * @title API with multiple servers
 * @server https://api.production.com Production Server
 * @server https://api.staging.com Staging Server
 */
PHP;
        file_put_contents($this->fixtureDir . '/api.php', $code);

        $core = new Core();
        $yaml = $core->generateYaml([$this->fixtureDir]);

        $yamlClean = str_replace("'", "", $yaml);
        $this->assertStringContainsString('servers:', $yamlClean);
        $this->assertStringContainsString('url: https://api.production.com', $yamlClean);
        $this->assertStringContainsString('description: Production Server', $yamlClean);
        $this->assertStringContainsString('url: https://api.staging.com', $yamlClean);
        $this->assertStringContainsString('description: Staging Server', $yamlClean);
    }

    public function testProgrammaticMultipleServers(): void
    {
        $code = <<<'PHP'
<?php
/**
 * @title API Title
 */
PHP;
        file_put_contents($this->fixtureDir . '/api.php', $code);

        $core = new Core();
        $core->setServers([
            ['url' => 'https://api.dev.com', 'description' => 'Development Server'],
            ['url' => 'https://api.test.com', 'description' => 'Testing Server']
        ]);
        $yaml = $core->generateYaml([$this->fixtureDir]);

        $yamlClean = str_replace("'", "", $yaml);
        $this->assertStringContainsString('servers:', $yamlClean);
        $this->assertStringContainsString('url: https://api.dev.com', $yamlClean);
        $this->assertStringContainsString('description: Development Server', $yamlClean);
        $this->assertStringContainsString('url: https://api.test.com', $yamlClean);
        $this->assertStringContainsString('description: Testing Server', $yamlClean);
    }
}
