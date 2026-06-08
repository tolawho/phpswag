<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\CLI\GenerateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private bool $hasBackup = false;

    protected function setUp(): void
    {
        if (file_exists('phpswag.yaml')) {
            rename('phpswag.yaml', 'phpswag.yaml.bak');
            $this->hasBackup = true;
        }

        $application = new Application();
        $application->add(new GenerateCommand());
        $command = $application->find('generate');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if ($this->hasBackup && file_exists('phpswag.yaml.bak')) {
            rename('phpswag.yaml.bak', 'phpswag.yaml');
        }
    }

    public function testExecuteWithoutPathFails()
    {
        $this->commandTester->execute([]);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('At least one --path is required', $this->commandTester->getDisplay());
    }

    public function testExecuteWithFilterUnusedTrue()
    {
        // Test passing "true" explicitly
        $this->commandTester->execute([
            '--path' => ['examples/App'],
            '--filter-unused' => 'true',
            '--output' => 'test-swagger-true.yaml',
        ]);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Documentation generated to test-swagger-true.yaml', $this->commandTester->getDisplay());

        if (file_exists('test-swagger-true.yaml')) {
            unlink('test-swagger-true.yaml');
        }
    }

    public function testExecuteWithFilterUnusedFalse()
    {
        // Test passing "false" explicitly
        $this->commandTester->execute([
            '--path' => ['examples/App'],
            '--filter-unused' => 'false',
            '--output' => 'test-swagger-false.yaml',
        ]);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Documentation generated to test-swagger-false.yaml', $this->commandTester->getDisplay());

        if (file_exists('test-swagger-false.yaml')) {
            unlink('test-swagger-false.yaml');
        }
    }

    public function testExecuteWithFilterUnusedAsFlag()
    {
        // Test passing option as a flag (no value)
        $this->commandTester->execute([
            '--path' => ['examples/App'],
            '--filter-unused' => null,
            '--output' => 'test-swagger-flag.yaml',
        ]);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Documentation generated to test-swagger-flag.yaml', $this->commandTester->getDisplay());

        if (file_exists('test-swagger-flag.yaml')) {
            unlink('test-swagger-flag.yaml');
        }
    }

    public function testGenerateCommandLoadsFromYamlConfig()
    {
        $configFile = 'phpswag.yaml';
        $config = [
            'paths' => ['examples/App'],
            'openapi_version' => '3.0.0',
            'format' => 'yaml',
            'output' => 'test-swagger-config.yaml',
            'filter_unused' => true,
        ];
        file_put_contents($configFile, \Symfony\Component\Yaml\Yaml::dump($config));

        try {
            $this->commandTester->execute([]);
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('Documentation generated to test-swagger-config.yaml', $this->commandTester->getDisplay());
            $this->assertFileExists('test-swagger-config.yaml');
        } finally {
            if (file_exists($configFile)) {
                unlink($configFile);
            }
            if (file_exists('test-swagger-config.yaml')) {
                unlink('test-swagger-config.yaml');
            }
        }
    }
}
