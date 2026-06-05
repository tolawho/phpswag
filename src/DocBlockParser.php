<?php

namespace PhpSwag;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Parser\ConstExprParser;

class DocBlockParser
{
    private Lexer $lexer;
    private PhpDocParser $parser;

    public function __construct()
    {
        $this->lexer = new Lexer();
        $constExprParser = new ConstExprParser();
        $typeParser = new TypeParser($constExprParser);
        $this->parser = new PhpDocParser($typeParser, $constExprParser);
    }

    public function parse(string $docBlock): PhpDocNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($docBlock));
        return $this->parser->parse($tokens);
    }
}
