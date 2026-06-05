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

    public function __construct()
    {
        $this->scanner = new Scanner();
        $this->parser = new Parser();
        $this->docCollector = new DocBlockCollector();
        $this->generator = new Generator();
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
            $docComment = $stmt->getDocComment()?->getText() ?? '';
            $tags = $this->docCollector->collectTags($docComment);

            $isSchema = false;
            $properties = [];

            foreach ($tags as $tag) {
                if ($tag['name'] === '@property') {
                    $isSchema = true;
                    $isNullable = str_contains($tag['type'], '|null') || str_starts_with($tag['type'], '?');
                    $cleanType = str_replace(['|null', '?'], '', $tag['type']);

                    $properties[] = new PropertyDefinition(
                        $tag['propertyName'],
                        $cleanType,
                        $isNullable,
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
                            $isNullable = str_contains($pTag['type'], '|null') || str_starts_with($pTag['type'], '?');
                            $cleanType = str_replace(['|null', '?'], '', $pTag['type']);

                            $properties[] = new PropertyDefinition(
                                $member->props[0]->name->toString(),
                                $cleanType,
                                $isNullable,
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
                    $responses = [];

                    foreach ($methodTags as $mTag) {
                        if ($mTag['name'] === '@route') {
                            $routeTag = $mTag['value'];
                        } elseif ($mTag['name'] === '@summary') {
                            $summary = $mTag['value'];
                        } elseif ($mTag['name'] === '@response') {
                            $parts = preg_split('/\s+/', trim($mTag['value']), 2);
                            if (count($parts) === 2) {
                                $responses[$parts[0]] = $nameResolver->resolve($parts[1]);
                            }
                        }
                    }

                    if ($routeTag) {
                        $routeParts = preg_split('/\s+/', trim($routeTag), 2);
                        if (count($routeParts) === 2) {
                            $this->generator->addRoute(new RouteDefinition(
                                method: $routeParts[0],
                                path: $routeParts[1],
                                summary: $summary,
                                responses: $responses
                            ));
                        }
                    }
                }
            }

            if ($isSchema) {
                $className = $stmt->name->toString();
                $fqcn = ($nameResolver->getCurrentNamespace() ? $nameResolver->getCurrentNamespace() . '\\' : '') . $className;
                $this->generator->addSchema(new SchemaDefinition($fqcn, $properties));
            }
        }
    }
}
