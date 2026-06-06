<?php

namespace PhpSwag;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
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

    /** @var array<string, array{node: Class_|Trait_, nameResolver: NameResolver}> */
    private array $discoveredClasses = [];

    public function __construct()
    {
        $this->scanner = new Scanner();
        $this->parser = new Parser();
        $this->docCollector = new DocBlockCollector();
        $this->schemaRegistry = new SchemaRegistry();
        $this->generator = new Generator($this->schemaRegistry);
    }

    public function setOpenApiVersion(string $version): void
    {
        $this->generator->setVersion($version);
    }

    public function setFilterUnusedSchemas(bool $filter): void
    {
        $this->generator->setFilterUnusedSchemas($filter);
    }

    public function generate(array $paths): string
    {
        $this->scanner->setPaths($paths);
        $files = $this->scanner->scan();

        // Pass 1: Discovery
        foreach ($files as $file) {
            $this->discoverFile($file);
        }

        // Pass 2: Analysis
        foreach ($this->discoveredClasses as $fqcn => $data) {
            $this->analyzeClass($fqcn, $data['node'], $data['nameResolver']);
        }

        return $this->generator->generateYaml();
    }

    private function discoverFile(string $filePath): void
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
                    $this->discoverStatement($innerStmt, $nameResolver);
                }
            } else {
                $this->discoverStatement($stmt, $nameResolver);
            }
        }
    }

    private function discoverStatement(Node $stmt, NameResolver $nameResolver): void
    {
        if ($stmt instanceof Class_ || $stmt instanceof Trait_) {
            $className = $stmt->name->toString();
            $namespace = $nameResolver->getCurrentNamespace();
            $fqcn = ($namespace ? $namespace . '\\' : '') . $className;

            $this->discoveredClasses[$fqcn] = [
                'node' => $stmt,
                'nameResolver' => $nameResolver
            ];

            $docComment = $stmt->getDocComment()?->getText() ?? '';
            $tags = $this->docCollector->collectTags($docComment);

            $templates = [];
            $typeArguments = [];
            $parent = null;

            foreach ($tags as $tag) {
                if ($tag['name'] === '@template') {
                    $parts = preg_split('/\s+/', trim($tag['value']));
                    if (!empty($parts[0])) {
                        $templates[] = $parts[0];
                    }
                }

                if ($tag['name'] === '@extends' || $tag['name'] === '@implements') {
                    $typeNode = $this->docCollector->parseType($tag['value']);
                    if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\GenericTypeNode) {
                        $parent = $nameResolver->resolve($typeNode->type->name);
                    } else {
                        $parent = $nameResolver->resolve($tag['value']);
                    }
                }
            }

            if ($parent === null && $stmt instanceof Class_ && $stmt->extends) {
                $parent = $nameResolver->resolve($stmt->extends->toString());
            }

            $traits = [];
            foreach ($stmt->stmts as $member) {
                if ($member instanceof TraitUse) {
                    foreach ($member->traits as $trait) {
                        $traits[] = $nameResolver->resolve($trait->toString());
                    }
                }
            }

            $this->schemaRegistry->register(new SchemaDefinition(
                name: $fqcn,
                parent: $parent,
                traits: $traits,
                templates: $templates,
                typeArguments: $typeArguments
            ));
        }
    }

    private function analyzeClass(string $fqcn, Class_|Trait_ $stmt, NameResolver $nameResolver): void
    {
        $schema = $this->schemaRegistry->get($fqcn);
        $typeResolver = new TypeResolver($this->schemaRegistry, $nameResolver, $schema->templates);
        $docComment = $stmt->getDocComment()?->getText() ?? '';
        $tags = $this->docCollector->collectTags($docComment);

        foreach ($tags as $tag) {
            if ($tag['name'] === '@extends' || $tag['name'] === '@use') {
                $typeNode = $this->docCollector->parseType($tag['value']);
                if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\GenericTypeNode) {
                    $targetFqcn = $nameResolver->resolve($typeNode->type->name);
                    $targetSchema = $this->schemaRegistry->get($targetFqcn);
                    if ($targetSchema && !empty($targetSchema->templates)) {
                        foreach ($typeNode->genericTypes as $i => $argNode) {
                            $templateName = $targetSchema->templates[$i] ?? "T$i";
                            $schema->typeArguments[$templateName] = $typeResolver->resolve($argNode);
                        }
                    }
                }
            }
        }

        $isSchema = false;
        $properties = [];

        foreach ($tags as $tag) {
            if ($tag['name'] === '@property' && isset($tag['type'])) {
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
                    if ($pTag['name'] === '@var' && isset($pTag['type'])) {
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
                $this->analyzeMethod($member, $nameResolver, $typeResolver);
            }
        }

        if ($isSchema || !empty($schema->templates) || $stmt instanceof Trait_) {
            $schema->properties = $properties;
        }
    }

    private function analyzeMethod(ClassMethod $member, NameResolver $nameResolver, TypeResolver $typeResolver): void
    {
        $methodDoc = $member->getDocComment()?->getText() ?? '';
        $tags = $this->docCollector->collectTags($methodDoc);

        $routeTag = null;
        $summary = null;
        $description = null;
        $tagsList = [];
        $responses = [];
        $parameters = [];

        foreach ($tags as $tag) {
            if ($tag['name'] === '@route') {
                if (preg_match('/^(GET|POST|PUT|DELETE|PATCH)\s+(\S+)/i', $tag['value'], $matches)) {
                    $routeTag = strtoupper($matches[1]) . ' ' . $matches[2];
                }
            } elseif ($tag['name'] === '@summary') {
                $summary = $tag['value'];
            } elseif ($tag['name'] === '@description') {
                $description = $tag['value'];
            } elseif ($tag['name'] === '@tag') {
                $tagsList[] = $tag['value'];
            } elseif ($tag['name'] === '@response') {
                if (preg_match('/^(\d+)\s+(.*)$/', $tag['value'], $matches)) {
                    $code = $matches[1];
                    $typeToParse = trim($matches[2]);
                    $typeNode = $this->docCollector->parseType($typeToParse);
                    if ($typeNode) {
                        $responses[$code] = $typeResolver->resolve($typeNode);
                    }
                }
            } elseif ($tag['name'] === '@param') {
                $parameters[] = [
                    'name' => $tag['propertyName'],
                    'schema' => $typeResolver->resolve($tag['type']),
                    'description' => $tag['description'] ?: null,
                ];
            }
        }

        if ($routeTag) {
            $routeParts = explode(' ', $routeTag);
            $this->generator->addRoute(new RouteDefinition(
                method: $routeParts[0],
                path: $routeParts[1],
                summary: $summary,
                description: $description,
                tags: $tagsList,
                responses: $responses,
                parameters: $parameters
            ));
        }
    }
}
