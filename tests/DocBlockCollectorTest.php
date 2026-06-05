<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\DocBlockCollector;

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
}
