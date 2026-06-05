<?php

namespace PhpSwag;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver as PhpParserNameResolver;

class Parser
{
    private \PhpParser\Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    public function parse(string $code): array
    {
        try {
            $stmts = $this->parser->parse($code);
            if ($stmts === null) {
                return [];
            }

            // We use our custom NameResolver to handle manual FQCN resolution later,
            // but we can also use the built-in one for general node resolution.
            $traverser = new NodeTraverser();
            $nameResolver = new PhpParserNameResolver();
            $traverser->addVisitor($nameResolver);
            return $traverser->traverse($stmts);
        } catch (Error $e) {
            // Handle parse error
            return [];
        }
    }
}
