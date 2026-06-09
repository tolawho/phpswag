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
        if (class_exists('PHPStan\PhpDocParser\ParserConfig')) {
            $configRef = new \ReflectionClass('PHPStan\PhpDocParser\ParserConfig');
            $config = $configRef->newInstance([]);

            $lexerRef = new \ReflectionClass(Lexer::class);
            $this->lexer = $lexerRef->newInstance($config);

            $constExprRef = new \ReflectionClass(ConstExprParser::class);
            $constExprParser = $constExprRef->newInstance($config);

            $typeRef = new \ReflectionClass(TypeParser::class);
            $typeParser = $typeRef->newInstance($config, $constExprParser);

            $phpDocRef = new \ReflectionClass(PhpDocParser::class);
            $this->parser = $phpDocRef->newInstance($config, $typeParser, $constExprParser);
        } else {
            $lexerRef = new \ReflectionClass(Lexer::class);
            $this->lexer = $lexerRef->newInstance();

            $constExprRef = new \ReflectionClass(ConstExprParser::class);
            $constExprParser = $constExprRef->newInstance();

            $typeRef = new \ReflectionClass(TypeParser::class);
            $typeParser = $typeRef->newInstance($constExprParser);

            $phpDocRef = new \ReflectionClass(PhpDocParser::class);
            $this->parser = $phpDocRef->newInstance($typeParser, $constExprParser);
        }
    }

    public function parse(string $docBlock): PhpDocNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($docBlock));
        return $this->parser->parse($tokens);
    }
}
