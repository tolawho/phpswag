<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PhpSwag\TypeAnalyzer;

class TypeAnalyzerTest extends TestCase
{
    private TypeAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new TypeAnalyzer();
    }

    public function testIsNativeTypeNullableWithNull()
    {
        $this->assertTrue($this->analyzer->isNativeTypeNullable(null));
    }

    public function testIsNativeTypeNullableWithNullableType()
    {
        $nullableType = new Node\NullableType(new Node\Identifier('string'));
        $this->assertTrue($this->analyzer->isNativeTypeNullable($nullableType));
    }

    public function testIsNativeTypeNullableWithMixed()
    {
        $mixedType = new Node\Identifier('mixed');
        $this->assertTrue($this->analyzer->isNativeTypeNullable($mixedType));
    }

    public function testIsNativeTypeNullableWithUnionTypeContainingNull()
    {
        $unionType = new Node\UnionType([
            new Node\Identifier('string'),
            new Node\Identifier('null')
        ]);
        $this->assertTrue($this->analyzer->isNativeTypeNullable($unionType));
    }

    public function testIsNativeTypeNullableWithUnionTypeNotContainingNull()
    {
        $unionType = new Node\UnionType([
            new Node\Identifier('string'),
            new Node\Identifier('int')
        ]);
        $this->assertFalse($this->analyzer->isNativeTypeNullable($unionType));
    }

    public function testIsDocTypeNullableWithNullableTypeNode()
    {
        $nullableTypeNode = new NullableTypeNode(new IdentifierTypeNode('string'));
        $this->assertTrue($this->analyzer->isDocTypeNullable($nullableTypeNode));
    }

    public function testIsDocTypeNullableWithMixed()
    {
        $mixedTypeNode = new IdentifierTypeNode('mixed');
        $this->assertTrue($this->analyzer->isDocTypeNullable($mixedTypeNode));
    }

    public function testIsDocTypeNullableWithUnionTypeContainingNull()
    {
        $unionTypeNode = new UnionTypeNode([
            new IdentifierTypeNode('string'),
            new IdentifierTypeNode('null')
        ]);
        $this->assertTrue($this->analyzer->isDocTypeNullable($unionTypeNode));
    }

    public function testIsDocTypeNullableWithUnionTypeNotContainingNull()
    {
        $unionTypeNode = new UnionTypeNode([
            new IdentifierTypeNode('string'),
            new IdentifierTypeNode('int')
        ]);
        $this->assertFalse($this->analyzer->isDocTypeNullable($unionTypeNode));
    }

    public function testDetermineRequiredExplicitOverrides()
    {
        $this->assertTrue($this->analyzer->determineRequired('prop', false, true, false, null));
        $this->assertFalse($this->analyzer->determineRequired('prop', false, false, false, null));
    }

    public function testDetermineRequiredHasDefaultIsFalse()
    {
        $this->assertFalse($this->analyzer->determineRequired('prop', false, null, true, null));
    }

    public function testDetermineRequiredUsesNativeTypeHintNullability()
    {
        // Nullable native type hint -> optional (not required)
        $nullableType = new Node\NullableType(new Node\Identifier('string'));
        $this->assertFalse($this->analyzer->determineRequired('prop', false, null, false, $nullableType));

        // Non-nullable native type hint -> required
        $nonNullableType = new Node\Identifier('string');
        $this->assertTrue($this->analyzer->determineRequired('prop', true, null, false, $nonNullableType));
    }

    public function testDetermineRequiredUsesDocNullabilityAsFallback()
    {
        // Nullable doc type -> optional (not required)
        $this->assertFalse($this->analyzer->determineRequired('prop', true, null, false, null));

        // Non-nullable doc type -> required
        $this->assertTrue($this->analyzer->determineRequired('prop', false, null, false, null));
    }
}
