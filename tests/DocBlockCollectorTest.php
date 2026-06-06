<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\DocBlockCollector;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;

class DocBlockCollectorTest extends TestCase
{
    public function testCollectTags()
    {
        $docComment = '/**
         * @route GET /users
         * @summary List all users
         */';

        $collector = new DocBlockCollector();
        $tags = $collector->collectTags($docComment);

        $this->assertCount(2, $tags);
        $this->assertEquals('@route', $tags[0]['name']);
        $this->assertEquals('GET /users', $tags[0]['value']);
        $this->assertEquals('@summary', $tags[1]['name']);
        $this->assertEquals('List all users', $tags[1]['value']);
    }

    public function testCollectPropertyTags()
    {
        $docComment = '/**
         * @property string $name User name
         */';

        $collector = new DocBlockCollector();
        $tags = $collector->collectTags($docComment);

        $this->assertCount(1, $tags);
        $this->assertEquals('@property', $tags[0]['name']);
        $this->assertInstanceOf(IdentifierTypeNode::class, $tags[0]['type']);
        $this->assertEquals('string', (string)$tags[0]['type']);
        $this->assertEquals('name', $tags[0]['propertyName']);
        $this->assertEquals('User name', $tags[0]['description']);
    }

    public function testParseTypeWithArrays()
    {
        $collector = new DocBlockCollector();

        $node = $collector->parseType('User[]');
        $this->assertInstanceOf(\PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode::class, $node);
        $this->assertInstanceOf(IdentifierTypeNode::class, $node->type);
        $this->assertEquals('User', $node->type->name);

        $nestedNode = $collector->parseType('User[][]');
        $this->assertInstanceOf(\PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode::class, $nestedNode);
        $this->assertInstanceOf(\PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode::class, $nestedNode->type);
        $this->assertInstanceOf(IdentifierTypeNode::class, $nestedNode->type->type);
        $this->assertEquals('User', $nestedNode->type->type->name);
    }
}
