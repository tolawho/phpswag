<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\TypeResolver;
use PhpSwag\SchemaRegistry;
use PhpSwag\NameResolver;
use PhpSwag\DocBlockParser;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;

class TypeResolverTest extends TestCase
{
    public function testResolveSimpleTypes()
    {
        $registry = new SchemaRegistry();
        $nameResolver = new NameResolver();
        $typeResolver = new TypeResolver($registry, $nameResolver);
        $parser = new DocBlockParser();

        $doc = '/** @var string */';
        $node = $parser->parse($doc);
        /** @var VarTagValueNode $varTag */
        $varTag = $node->getVarTagValues()[0];

        $resolved = $typeResolver->resolve($varTag->type);
        $this->assertEquals(['type' => 'string'], $resolved);

        $doc = '/** @var int */';
        $node = $parser->parse($doc);
        $varTag = $node->getVarTagValues()[0];
        $resolved = $typeResolver->resolve($varTag->type);
        $this->assertEquals(['type' => 'integer'], $resolved);
    }

    public function testResolveNullableAndArray()
    {
        $registry = new SchemaRegistry();
        $nameResolver = new NameResolver();
        $typeResolver = new TypeResolver($registry, $nameResolver);
        $parser = new DocBlockParser();

        $doc = '/** @var string|null */';
        $node = $parser->parse($doc);
        $varTag = $node->getVarTagValues()[0];
        $resolved = $typeResolver->resolve($varTag->type);
        $this->assertEquals(['type' => 'string', 'nullable' => true], $resolved);

        $doc = '/** @var User[] */';
        $node = $parser->parse($doc);
        $varTag = $node->getVarTagValues()[0];
        $resolved = $typeResolver->resolve($varTag->type);
        $this->assertEquals([
            'type' => 'array',
            'items' => ['$ref' => '#/components/schemas/User']
        ], $resolved);
    }
}
