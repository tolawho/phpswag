<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpSwag\NameResolver;

class NameResolverTest extends TestCase
{
    public function testResolveClassName()
    {
        $code = '<?php
        namespace App\Controllers;
        use App\Models\User;
        use App\Resources as Res;
        class UserController {}
        ';

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $stmts = $parser->parse($code);

        $resolver = new NameResolver();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($resolver);
        $traverser->traverse($stmts);

        $this->assertEquals('App\\Controllers', $resolver->getCurrentNamespace());
        $this->assertEquals('App\\Models\\User', $resolver->resolve('User'));
        $this->assertEquals('App\\Resources\\UserResource', $resolver->resolve('Res\\UserResource'));
        $this->assertEquals('App\\Controllers\\LocalClass', $resolver->resolve('LocalClass'));
        $this->assertEquals('Absolute\\Class', $resolver->resolve('\\Absolute\\Class'));
    }
}
