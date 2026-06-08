<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;
use PhpSwag\Scanner;
use PhpSwag\Parser;
use PhpSwag\DocBlockCollector;
use PhpSwag\SchemaRegistry;
use PhpSwag\TypeMappingRegistry;
use PhpSwag\Generator;

class CoreDependencyInjectionTest extends TestCase
{
    public function testConstructorInjectionSetsProperties()
    {
        $scanner = $this->createMock(Scanner::class);
        $parser = $this->createMock(Parser::class);
        $docCollector = $this->createMock(DocBlockCollector::class);
        $schemaRegistry = $this->createMock(SchemaRegistry::class);
        $typeMappingRegistry = $this->createMock(TypeMappingRegistry::class);
        $generator = $this->createMock(Generator::class);

        $core = new Core(
            $scanner,
            $parser,
            $docCollector,
            $schemaRegistry,
            $typeMappingRegistry,
            $generator
        );

        $reflection = new \ReflectionClass($core);

        $scannerProp = $reflection->getProperty('scanner');
        $scannerProp->setAccessible(true);
        $this->assertSame($scanner, $scannerProp->getValue($core));

        $parserProp = $reflection->getProperty('parser');
        $parserProp->setAccessible(true);
        $this->assertSame($parser, $parserProp->getValue($core));

        $docCollectorProp = $reflection->getProperty('docCollector');
        $docCollectorProp->setAccessible(true);
        $this->assertSame($docCollector, $docCollectorProp->getValue($core));

        $schemaRegistryProp = $reflection->getProperty('schemaRegistry');
        $schemaRegistryProp->setAccessible(true);
        $this->assertSame($schemaRegistry, $schemaRegistryProp->getValue($core));

        $typeMappingRegistryProp = $reflection->getProperty('typeMappingRegistry');
        $typeMappingRegistryProp->setAccessible(true);
        $this->assertSame($typeMappingRegistry, $typeMappingRegistryProp->getValue($core));

        $generatorProp = $reflection->getProperty('generator');
        $generatorProp->setAccessible(true);
        $this->assertSame($generator, $generatorProp->getValue($core));
    }

    public function testCreateDefaultReturnsCoreWithDefaults()
    {
        $core = Core::createDefault();
        $this->assertInstanceOf(Core::class, $core);

        $reflection = new \ReflectionClass($core);

        $scannerProp = $reflection->getProperty('scanner');
        $scannerProp->setAccessible(true);
        $this->assertInstanceOf(Scanner::class, $scannerProp->getValue($core));
    }

    public function testRegisterTagParser()
    {
        $core = Core::createDefault();
        $parser = $this->createMock(\PhpSwag\TagParser\TagParserInterface::class);
        $parser->method('getSupportedTags')->willReturn(['@customTag']);

        $core->registerTagParser($parser);
        $parsers = $core->getTagParsers();

        $this->assertArrayHasKey('@customTag', $parsers);
        $this->assertSame($parser, $parsers['@customTag']);
    }

    public function testRegisterSchemaTagParser()
    {
        $core = Core::createDefault();
        $parser = $this->createMock(\PhpSwag\TagParser\SchemaTagParserInterface::class);
        $parser->method('getSupportedTags')->willReturn(['@customSchemaTag']);

        $core->registerSchemaTagParser($parser);
        $parsers = $core->getSchemaTagParsers();

        $this->assertArrayHasKey('@customSchemaTag', $parsers);
        $this->assertSame($parser, $parsers['@customSchemaTag']);
    }
}
