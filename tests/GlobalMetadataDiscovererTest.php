<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\DocBlockCollector;
use PhpSwag\Metadata\GlobalMetadataDiscoverer;
use PhpSwag\Exception\DiagnosticException;

class GlobalMetadataDiscovererTest extends TestCase
{
    private DocBlockCollector $docCollector;
    private GlobalMetadataDiscoverer $discoverer;

    protected function setUp(): void
    {
        $this->docCollector = new DocBlockCollector();
        $this->discoverer = new GlobalMetadataDiscoverer($this->docCollector);
    }

    public function testDiscoverGlobalFields()
    {
        $code = <<<'PHP'
<?php
/**
 * @title Test Title
 * @version 1.0.0
 * @description API description
 * @host test.host.com
 * @contact.name Admin
 * @license.name MIT
 */
PHP;

        $res = $this->discoverer->discover($code, 'file.php');

        $this->assertEquals('Test Title', $res['globalMetadata']['@title']);
        $this->assertEquals('1.0.0', $res['globalMetadata']['@version']);
        $this->assertEquals('API description', $res['globalMetadata']['@description']);
        $this->assertEquals('test.host.com', $res['globalMetadata']['@host']);
        $this->assertEquals('Admin', $res['globalMetadata']['@contact.name']);
        $this->assertEquals('MIT', $res['globalMetadata']['@license.name']);
        $this->assertEquals('file.php', $res['metadataSources']['@title']);
    }

    public function testDiscoverSecurityDefinitionsApiKey()
    {
        $code = <<<'PHP'
<?php
/**
 * @securityDefinitions.apikey ApiKeyAuth header X-API-KEY
 */
PHP;

        $res = $this->discoverer->discover($code, 'file.php');

        $this->assertArrayHasKey('ApiKeyAuth', $res['securitySchemes']);
        $this->assertEquals([
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-KEY',
        ], $res['securitySchemes']['ApiKeyAuth']);
    }

    public function testDiscoverInvalidApiKeySyntaxThrows()
    {
        $code = <<<'PHP'
<?php
/**
 * @securityDefinitions.apikey ApiKeyAuth invalidFormat
 */
PHP;

        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage("Invalid syntax for tag '@securityDefinitions.apikey'");

        $this->discoverer->discover($code, 'file.php');
    }

    public function testDiscoverSecurityDefinitionsJwt()
    {
        $code = <<<'PHP'
<?php
/**
 * @securityDefinitions.jwt BearerAuth
 */
PHP;

        $res = $this->discoverer->discover($code, 'file.php');

        $this->assertArrayHasKey('BearerAuth', $res['securitySchemes']);
        $this->assertEquals([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ], $res['securitySchemes']['BearerAuth']);
    }

    public function testDiscoverInvalidJwtSyntaxThrows()
    {
        $code = <<<'PHP'
<?php
/**
 * @securityDefinitions.jwt
 */
PHP;

        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage("Invalid syntax for tag '@securityDefinitions.jwt'");

        $this->discoverer->discover($code, 'file.php');
    }

    public function testDiscoverSecurityDefinitionsBasic()
    {
        $code = <<<'PHP'
<?php
/**
 * @securityDefinitions.basic BasicAuth
 */
PHP;

        $res = $this->discoverer->discover($code, 'file.php');

        $this->assertArrayHasKey('BasicAuth', $res['securitySchemes']);
        $this->assertEquals([
            'type' => 'http',
            'scheme' => 'basic',
        ], $res['securitySchemes']['BasicAuth']);
    }

    public function testDiscoverInvalidBasicSyntaxThrows()
    {
        $code = <<<'PHP'
<?php
/**
 * @securityDefinitions.basic
 */
PHP;

        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage("Invalid syntax for tag '@securityDefinitions.basic'");

        $this->discoverer->discover($code, 'file.php');
    }

    public function testDiscoverSecurity()
    {
        $code = <<<'PHP'
<?php
/**
 * @title Test API
 * @security ApiKeyAuth[read,write]
 */
PHP;

        $res = $this->discoverer->discover($code, 'file.php');

        $this->assertCount(1, $res['globalSecurity']);
        $this->assertEquals(['ApiKeyAuth' => ['read', 'write']], $res['globalSecurity'][0]);
    }

    public function testDiscoverTagName()
    {
        $code = <<<'PHP'
<?php
/**
 * @tag.name users Manage users endpoint
 */
PHP;

        $res = $this->discoverer->discover($code, 'file.php');

        $this->assertArrayHasKey('users', $res['globalTags']);
        $this->assertEquals([
            'name' => 'users',
            'description' => 'Manage users endpoint',
        ], $res['globalTags']['users']);
    }

    public function testDiscoverInvalidTagNameSyntaxThrows()
    {
        $code = <<<'PHP'
<?php
/**
 * @tag.name
 */
PHP;

        $this->expectException(DiagnosticException::class);
        $this->expectExceptionMessage("Invalid syntax for tag '@tag.name'");

        $this->discoverer->discover($code, 'file.php');
    }

    public function testDiscoverDuplicateThrows()
    {
        $code = <<<'PHP'
<?php
/**
 * @title First Title
 */
PHP;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Duplicate global tag '@title' found");

        $this->discoverer->discover($code, 'file.php', ['@title' => 'Existing Title'], ['@title' => 'existing.php']);
    }
}
