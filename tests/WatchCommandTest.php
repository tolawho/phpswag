<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\CLI\WatchCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class WatchCommandTest extends TestCase
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
        $application->add(new WatchCommand());
        $command = $application->find('watch');
        $this->commandTester = new CommandTester($command);

        putenv('PHPSWAG_TEST_LOOP=1');
    }

    protected function tearDown(): void
    {
        putenv('PHPSWAG_TEST_LOOP');

        if ($this->hasBackup && file_exists('phpswag.yaml.bak')) {
            rename('phpswag.yaml.bak', 'phpswag.yaml');
        }

        if (file_exists('test-watch-swagger.yaml')) {
            unlink('test-watch-swagger.yaml');
        }
    }

    public function testWatchCommandRunsAndGeneratesInitialSpec()
    {
        $this->commandTester->execute([
            '--path' => ['examples/App'],
            '--output' => 'test-watch-swagger.yaml',
            '--port' => '8999'
        ]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Generating initial specification...', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Starting Live Preview Server', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Watcher started', $this->commandTester->getDisplay());
        $this->assertFileExists('test-watch-swagger.yaml');
    }

    public function testWatchCommandLoadsFromYamlConfig()
    {
        $configFile = 'phpswag.yaml';
        $config = [
            'paths' => ['examples/App'],
            'openapi_version' => '3.0.0',
            'format' => 'yaml',
            'output' => 'test-watch-config.yaml',
            'watch_host' => '127.0.0.2',
            'watch_port' => 9999,
        ];
        file_put_contents($configFile, \Symfony\Component\Yaml\Yaml::dump($config));

        try {
            $this->commandTester->execute([]);
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('Starting Live Preview Server on http://127.0.0.2:9999...', $this->commandTester->getDisplay());
            $this->assertFileExists('test-watch-config.yaml');
        } finally {
            if (file_exists($configFile)) {
                unlink($configFile);
            }
            if (file_exists('test-watch-config.yaml')) {
                unlink('test-watch-config.yaml');
            }
        }
    }
}
