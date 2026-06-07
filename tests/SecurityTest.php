<?php

namespace PhpSwag\Tests;

use PhpSwag\Core;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/fixtures/security';
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
        if (!is_dir($path)) return;
        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        rmdir($path);
    }

    public function testSecurityDefinitionsAndGlobalSecurity(): void
    {
        $code = <<<'PHP'
<?php
/**
 * @title Security API
 * @securityDefinitions.apikey MyApiKey header X-API-KEY
 * @securityDefinitions.jwt MyJwtAuth
 * @security MyJwtAuth
 */

namespace App;

class Controller {
    /**
     * @route GET /public
     * @security
     */
    public function public() {}

    /**
     * @route GET /private
     */
    public function private() {}

    /**
     * @route GET /dual
     * @security MyApiKey, MyJwtAuth
     */
    public function dual() {}

    /**
     * @route GET /or
     * @security MyApiKey
     * @security MyJwtAuth
     */
    public function or() {}

    /**
     * @route GET /scoped
     * @security MyJwtAuth[read, write]
     */
    public function scoped() {}
}
PHP;
        file_put_contents($this->fixtureDir . '/api.php', $code);

        $core = new Core();
        $yaml = $core->generateYaml([$this->fixtureDir]);

        // Check Security Schemes
        $this->assertStringContainsString('securitySchemes:', $yaml);
        $this->assertStringContainsString('MyApiKey:', $yaml);
        $this->assertStringContainsString('type: apiKey', $yaml);
        $this->assertStringContainsString('in: header', $yaml);
        $this->assertStringContainsString('name: X-API-KEY', $yaml);

        $this->assertStringContainsString('MyJwtAuth:', $yaml);
        $this->assertStringContainsString('type: http', $yaml);
        $this->assertStringContainsString('scheme: bearer', $yaml);
        $this->assertStringContainsString('bearerFormat: JWT', $yaml);

        // Check Global Security
        $this->assertStringContainsString('security:', $yaml);
        $this->assertStringContainsString('MyJwtAuth: {  }', $yaml);

        // Check Scoped Security
        $this->assertStringContainsString('- read', $yaml);
        $this->assertStringContainsString('- write', $yaml);
    }
}
