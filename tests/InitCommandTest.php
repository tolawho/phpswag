<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\CLI\InitCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

class InitCommandTest extends TestCase
{
    private bool $hasBackup = false;

    protected function setUp(): void
    {
        if (file_exists('phpswag.yaml')) {
            rename('phpswag.yaml', 'phpswag.yaml.bak');
            $this->hasBackup = true;
        }
    }

    protected function tearDown(): void
    {
        if (file_exists('phpswag.yaml')) {
            unlink('phpswag.yaml');
        }
        if ($this->hasBackup && file_exists('phpswag.yaml.bak')) {
            rename('phpswag.yaml.bak', 'phpswag.yaml');
        }
    }

    public function testInitCommandGeneratesYamlConfig()
    {
        $application = new Application();
        $application->add(new InitCommand());
        $command = $application->find('init');
        $commandTester = new CommandTester($command);

        // Simulate interactive console inputs:
        // 1. Paths to scan [src]
        // 2. OpenAPI version [3.0.0]
        // 3. Output format [yaml]
        // 4. Output destination path [swagger.yaml]
        // 5. Filter unused schemas [Y/n]
        // 6. Enable caching [y/N]
        $commandTester->setInputs([
            'src/Controllers, src/Models', // Paths to scan
            '1', // Choice index 1 = 3.1.0
            '0', // Choice index 0 = yaml
            'public/docs.yaml', // Output path
            'y', // Filter unused
            'y', // Enable cache
        ]);

        $commandTester->execute([]);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Successfully created configuration file: phpswag.yaml', $commandTester->getDisplay());
        $this->assertFileExists('phpswag.yaml');

        $config = Yaml::parseFile('phpswag.yaml');
        $this->assertEquals(['src/Controllers', 'src/Models'], $config['paths']);
        $this->assertEquals('3.1.0', $config['openapi_version']);
        $this->assertEquals('yaml', $config['format']);
        $this->assertEquals('public/docs.yaml', $config['output']);
        $this->assertTrue($config['filter_unused']);
        $this->assertTrue($config['cache']);
        $this->assertEquals('./.phpswag-cache', $config['cache_file']);
    }
}
