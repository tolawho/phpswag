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
        $factory = new ParserFactory();
        // @phpstan-ignore-next-line
        if (method_exists($factory, 'createForNewestSupportedVersion')) {
            $this->parser = $factory->createForNewestSupportedVersion();
        } else {
            // @phpstan-ignore-next-line
            $this->parser = $factory->create(ParserFactory::PREFER_PHP7);
        }
    }

    /**
     * @return array<int, \PhpParser\Node\Stmt>
     */
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
            /** @var array<int, \PhpParser\Node\Stmt> $resolvedStmts */
            $resolvedStmts = $traverser->traverse($stmts);
            return $resolvedStmts;
        } catch (Error $e) {
            // Handle parse error
            return [];
        }
    }
}
