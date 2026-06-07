<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\CLI\GenerateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateCommandTest extends TestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new GenerateCommand());
        $command = $application->find('generate');
        $this->commandTester = new CommandTester($command);
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
}
