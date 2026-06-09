<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Bridges\Symfony\DependencyInjection\Configuration;
use PhpSwag\Bridges\Symfony\DependencyInjection\PhpSwagExtension;
use PhpSwag\Bridges\Symfony\Command\GenerateCommand as SymfonyGenerateCommand;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Tester\CommandTester;

class FrameworkBridgesTest extends TestCase
{
    public function testSymfonyConfigurationTree()
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $configs = [
            'phpswag' => [
                'paths' => ['src/'],
                'output' => 'web/swagger.yaml',
                'title' => 'Test Symfony Title'
            ]
        ];

        $processed = $processor->processConfiguration($configuration, $configs);

        $this->assertEquals(['src/'], $processed['paths']);
        $this->assertEquals('web/swagger.yaml', $processed['output']);
        $this->assertEquals('Test Symfony Title', $processed['title']);
        $this->assertEquals('yaml', $processed['format']);
        $this->assertFalse($processed['cache']);
    }

    public function testSymfonyExtensionLoadsParameters()
    {
        $container = new ContainerBuilder();
        $extension = new PhpSwagExtension();

        $configs = [
            'phpswag' => [
                'paths' => ['src/Controllers'],
                'output' => 'public/spec.json',
                'format' => 'json'
            ]
        ];

        $extension->load($configs, $container);

        $this->assertTrue($container->hasParameter('phpswag.paths'));
        $this->assertEquals(['src/Controllers'], $container->getParameter('phpswag.paths'));
        $this->assertEquals('public/spec.json', $container->getParameter('phpswag.output'));
        $this->assertEquals('json', $container->getParameter('phpswag.format'));
    }

    public function testSymfonyGenerateCommand()
    {
        $bag = $this->createMock(ParameterBagInterface::class);
        $bag->method('has')->willReturnMap([
            ['phpswag.paths', true],
            ['phpswag.title', true],
            ['phpswag.version', true],
            ['phpswag.description', true],
            ['phpswag.host', true]
        ]);
        $bag->method('get')->willReturnMap([
            ['phpswag.paths', ['examples/App']],
            ['phpswag.output', 'test-symfony-spec.yaml'],
            ['phpswag.format', 'yaml'],
            ['phpswag.title', 'Symfony Spec'],
            ['phpswag.version', '2.0.0'],
            ['phpswag.description', 'Generated in tests'],
            ['phpswag.host', 'http://localhost:8000'],
            ['phpswag.cache', false]
        ]);

        $command = new SymfonyGenerateCommand($bag);
        $application = new SymfonyApplication();
        $application->add($command);

        $tester = new CommandTester($application->find('phpswag:generate'));
        $tester->execute([]);

        $this->assertEquals(0, $tester->getStatusCode());
        $this->assertStringContainsString('Documentation generated successfully to test-symfony-spec.yaml', $tester->getDisplay());

        if (file_exists('test-symfony-spec.yaml')) {
            unlink('test-symfony-spec.yaml');
        }
    }

    public function testSymfonyGenerateCommandWithValidation()
    {
        $bag = $this->createMock(ParameterBagInterface::class);
        $bag->method('has')->willReturnMap([
            ['phpswag.paths', true],
            ['phpswag.title', true],
            ['phpswag.version', true],
            ['phpswag.description', true],
            ['phpswag.host', true]
        ]);
        $bag->method('get')->willReturnMap([
            ['phpswag.paths', ['examples/App']],
            ['phpswag.output', 'test-symfony-spec-val.yaml'],
            ['phpswag.format', 'yaml'],
            ['phpswag.title', 'Symfony Spec'],
            ['phpswag.version', '2.0.0'],
            ['phpswag.description', 'Generated in tests'],
            ['phpswag.host', 'http://localhost:8000'],
            ['phpswag.cache', false]
        ]);

        $command = new SymfonyGenerateCommand($bag);
        $application = new SymfonyApplication();
        $application->add($command);

        $tester = new CommandTester($application->find('phpswag:generate'));
        $tester->execute(['--validate' => true]);

        // Should pass since examples/App is a valid OpenAPI specification
        $this->assertEquals(0, $tester->getStatusCode());
        $this->assertStringContainsString('OpenAPI Validation Passed!', $tester->getDisplay());

        if (file_exists('test-symfony-spec-val.yaml')) {
            unlink('test-symfony-spec-val.yaml');
        }
    }

    public function testLaravelServiceProviderInstantiation()
    {
        // Mock Laravel application container
        $app = $this->createMock(\Illuminate\Contracts\Foundation\Application::class);
        $app->method('runningInConsole')->willReturn(true);

        $provider = new \PhpSwag\Bridges\Laravel\PhpSwagServiceProvider($app);

        $this->assertInstanceOf(\Illuminate\Support\ServiceProvider::class, $provider);
    }
}
