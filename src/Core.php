<?php

namespace PhpSwag;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpSwag\IR\PropertyDefinition;
use PhpSwag\IR\RouteDefinition;
use PhpSwag\IR\SchemaDefinition;

class Core
{
    private Scanner $scanner;
    private Parser $parser;
    private DocBlockCollector $docCollector;
    private Generator $generator;
    private SchemaRegistry $schemaRegistry;

    public function __construct()
    {
        $this->scanner = new Scanner();
        $this->parser = new Parser();
        $this->docCollector = new DocBlockCollector();
        $this->schemaRegistry = new SchemaRegistry();
        $this->generator = new Generator($this->schemaRegistry);
    }

    public function generate(array $paths): string
    {
        $this->scanner->setPaths($paths);
        $files = $this->scanner->scan();

        foreach ($files as $file) {
            $this->processFile($file);
        }

        return $this->generator->generateYaml();
    }

    private function processFile(string $filePath): void
    {
        $code = file_get_contents($filePath);
        $stmts = $this->parser->parse($code);

        $nameResolver = new NameResolver();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($nameResolver);
        $traverser->traverse($stmts);

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                foreach ($stmt->stmts as $innerStmt) {
                    $this->processStatement($innerStmt, $nameResolver);
                }
            } else {
                $this->processStatement($stmt, $nameResolver);
            }
        }
    }

    private function processStatement(Node $stmt, NameResolver $nameResolver): void
    {
        if ($stmt instanceof Class_) {
            $typeResolver = new TypeResolver($this->schemaRegistry, $nameResolver);
            $docComment = $stmt->getDocComment()?->getText() ?? '';
            $tags = $this->docCollector->collectTags($docComment);

            $isSchema = false;
            $properties = [];

            foreach ($tags as $tag) {
                if ($tag['name'] === '@property') {
                    $isSchema = true;
                    $propertySchema = $typeResolver->resolve($tag['type']);

                    $properties[] = new PropertyDefinition(
                        $tag['propertyName'],
                        $propertySchema,
                        $tag['description']
                    );
                }
            }

            foreach ($stmt->stmts as $member) {
                if ($member instanceof Property) {
                    $isSchema = true;
                    $propDoc = $member->getDocComment()?->getText() ?? '';
                    $propTags = $this->docCollector->collectTags($propDoc);
                    foreach ($propTags as $pTag) {
                        if ($pTag['name'] === '@var') {
                            $propertySchema = $typeResolver->resolve($pTag['type']);

                            $properties[] = new PropertyDefinition(
                                $member->props[0]->name->toString(),
                                $propertySchema,
                                $pTag['description']
                            );
                        }
                    }
                }

                if ($member instanceof ClassMethod) {
                    $methodDoc = $member->getDocComment()?->getText() ?? '';
                    $methodTags = $this->docCollector->collectTags($methodDoc);

                    $routeTag = null;
                    $summary = null;
                    $description = null;
                    $tagsList = [];
                    $responses = [];

                    foreach ($methodTags as $mTag) {
                        switch ($mTag['name']) {
                            case '@route':
                                $routeTag = $mTag['value'];
                                break;
                            case '@summary':
                                $summary = $mTag['value'];
                                break;
                            case '@description':
                                $description = $mTag['value'];
                                break;
                            case '@tag':
                                $tagsList[] = $mTag['value'];
                                break;
                            case '@response':
                                $parts = preg_split('/\s+/', trim($mTag['value']), 2);
                                if (count($parts) >= 2) {
                                    // Parse response type using DocBlockParser/TypeResolver
                                    $responseDoc = '/** @var ' . $parts[1] . ' */';
                                    $responseTags = $this->docCollector->collectTags($responseDoc);
                                    if (isset($responseTags[0]['type'])) {
                                        $responses[$parts[0]] = $typeResolver->resolve($responseTags[0]['type']);
                                    }
                                }
                                break;
                        }
                    }

                    if ($routeTag) {
                        $routeParts = preg_split('/\s+/', trim($routeTag), 2);
                        if (count($routeParts) === 2) {
                            $this->generator->addRoute(new RouteDefinition(
                                method: $routeParts[0],
                                path: $routeParts[1],
                                summary: $summary,
                                description: $description,
                                tags: $tagsList,
                                responses: $responses
                            ));
                        }
                    }
                }
            }

            if ($isSchema) {
                $className = $stmt->name->toString();
                $fqcn = ($nameResolver->getCurrentNamespace() ? $nameResolver->getCurrentNamespace() . '\\' : '') . $className;
                $this->schemaRegistry->register(new SchemaDefinition($fqcn, $properties));
            }
        }
    }
}
