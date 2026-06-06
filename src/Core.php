<?php

namespace PhpSwag;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\ClassMethod;
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

    private bool $isAnalyzed = false;

    private array $globalMetadata = [];
    private array $metadataSources = [];
    private array $cliOverrides = [];

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

    private function analyze(array $paths): void
    {
        if ($this->isAnalyzed) {
            return;
        }

        $this->scanner->setPaths($paths);
        $files = $this->scanner->scan();

        // Pass 1: Discovery
        foreach ($files as $file) {
            $this->discoverFile($file);
        }

        $this->applyGlobalMetadata();

        // Pass 2: Analysis
        foreach ($this->discoveredClasses as $fqcn => $data) {
            $this->analyzeClass($fqcn, $data['node'], $data['nameResolver']);
        }

        $this->isAnalyzed = true;
    }

    public function generate(array $paths): string
    {
        $this->analyze($paths);
        return $this->generator->generateYaml();
    }

    public function generateYaml(array $paths): string
    {
        $this->analyze($paths);
        return $this->generator->generateYaml();
    }

    public function generateJson(array $paths): string
    {
        $this->analyze($paths);
        return $this->generator->generateJson();
    }

    private function discoverFile(string $filePath): void
    {
        $code = file_get_contents($filePath);
        $stmts = $this->parser->parse($code);

        // Check for global metadata in comments
        $this->discoverGlobalMetadata($code, $filePath);

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

    private function discoverGlobalMetadata(string $code, string $filePath): void
    {
        $tokens = token_get_all($code);
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                $docComment = $token[1];
                $tags = $this->docCollector->collectTags($docComment);
                foreach ($tags as $tag) {
                    $tagName = $tag['name'];
                    if (in_array($tagName, ['@title', '@version', '@description', '@host']) ||
                        str_starts_with($tagName, '@contact.') ||
                        str_starts_with($tagName, '@license.')) {

                        $val = $tag['value'] ?? '';
                        if (isset($this->globalMetadata[$tagName]) && $this->globalMetadata[$tagName] !== $val) {
                            throw new \Exception(sprintf(
                                "Duplicate global tag '%s' found in %s and %s",
                                $tagName,
                                $this->metadataSources[$tagName],
                                $filePath
                            ));
                        }
                        $this->globalMetadata[$tagName] = $val;
                        $this->metadataSources[$tagName] = $filePath;
                    }
                }
            }
        }
    }

    private function applyGlobalMetadata(): void
    {
        $title = $this->cliOverrides['title'] ?? $this->globalMetadata['@title'] ?? null;
        if ($title !== null) {
            $this->generator->setTitle($title);
        }

        $apiVersion = $this->cliOverrides['api-version'] ?? $this->globalMetadata['@version'] ?? null;
        if ($apiVersion !== null) {
            $this->generator->setApiVersion($apiVersion);
        }

        $description = $this->cliOverrides['description'] ?? $this->globalMetadata['@description'] ?? null;
        if ($description !== null) {
            $this->generator->setDescription($description);
        }

        $contact = [];
        if (isset($this->globalMetadata['@contact.name'])) $contact['name'] = $this->globalMetadata['@contact.name'];
        if (isset($this->globalMetadata['@contact.email'])) $contact['email'] = $this->globalMetadata['@contact.email'];
        if (isset($this->globalMetadata['@contact.url'])) $contact['url'] = $this->globalMetadata['@contact.url'];
        if (!empty($contact)) {
            $this->generator->setContact($contact);
        }

        $license = [];
        if (isset($this->globalMetadata['@license.name'])) $license['name'] = $this->globalMetadata['@license.name'];
        if (isset($this->globalMetadata['@license.url'])) $license['url'] = $this->globalMetadata['@license.url'];
        if (!empty($license)) {
            $this->generator->setLicense($license);
        }

        $host = $this->cliOverrides['host'] ?? $this->globalMetadata['@host'] ?? null;
        if ($host !== null) {
            $this->generator->setServers([['url' => $host]]);
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

                $desc = is_array($tag['description']) ? ($tag['description']['description'] ?? null) : ($tag['description'] ?? null);

                $properties[] = new PropertyDefinition(
                    $tag['propertyName'],
                    $propertySchema,
                    $desc
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

                        $desc = is_array($pTag['description']) ? ($pTag['description']['description'] ?? null) : ($pTag['description'] ?? null);

                        $properties[] = new PropertyDefinition(
                            $member->props[0]->name->toString(),
                            $propertySchema,
                            $desc
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
        $requestBody = null;

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
            } elseif (in_array($tag['name'], ['@path', '@query', '@header', '@cookie'])) {
                $in = substr($tag['name'], 1);
                $parameters[] = array_merge($tag, [
                    'in' => $in,
                    'schema' => $typeResolver->resolve($tag['type']),
                    'name' => $tag['propertyName']
                ]);
            } elseif ($tag['name'] === '@body') {
                $requestBody = [
                    'schema' => $typeResolver->resolve($tag['type']),
                    'description' => is_array($tag['description']) ? ($tag['description']['description'] ?? null) : ($tag['description'] ?? null)
                ];
            }
        }

        if ($routeTag) {
            $routeParts = explode(' ', $routeTag);
            $path = $routeParts[1];

            // Auto-inference from method parameters
            foreach ($member->params as $param) {
                $paramName = $param->var->name;

                // Skip if already defined by explicit tags
                $exists = false;
                foreach ($parameters as $p) {
                    if ($p['name'] === $paramName) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) continue;

                $type = 'mixed';
                if ($param->type instanceof Node\Identifier) {
                    $type = $param->type->toString();
                } elseif ($param->type instanceof Node\Name) {
                    $resolved = $param->type->getAttribute('resolvedName');
                    if ($resolved) {
                        $type = '\\' . $resolved->toString();
                    } else {
                        $type = $param->type->toString();
                    }
                }

                $schema = $typeResolver->resolve($this->docCollector->parseType($type));

                // If it's a class and not primitive, infer as requestBody if not already set
                $isPrimitive = in_array(ltrim($type, '\\'), ['int', 'string', 'bool', 'float', 'array', 'mixed']);

                if (!$isPrimitive && $requestBody === null) {
                    $requestBody = [
                        'schema' => $schema,
                        'description' => 'Auto-inferred from method parameter $' . $paramName
                    ];
                } else {
                    $in = 'query';
                    if (strpos($path, '{' . $paramName . '}') !== false) {
                        $in = 'path';
                    }

                    $parameters[] = [
                        'name' => $paramName,
                        'in' => $in,
                        'schema' => $schema,
                        'description' => 'Auto-inferred from method parameter'
                    ];
                }
            }

            $this->generator->addRoute(new RouteDefinition(
                method: $routeParts[0],
                path: $path,
                summary: $summary,
                description: $description,
                tags: $tagsList,
                responses: $responses,
                parameters: $parameters,
                requestBody: $requestBody
            ));
        }
    }

    public function setTitle(string $title): void
    {
        $this->cliOverrides['title'] = $title;
    }

    public function setApiVersion(string $version): void
    {
        $this->cliOverrides['api-version'] = $version;
    }

    public function setDescription(?string $description): void
    {
        $this->cliOverrides['description'] = $description;
    }

    public function setContact(?array $contact): void
    {
        $this->generator->setContact($contact);
    }

    public function setLicense(?array $license): void
    {
        $this->generator->setLicense($license);
    }

    public function setServers(array $servers): void
    {
        if (isset($servers[0]['url'])) {
            $this->cliOverrides['host'] = $servers[0]['url'];
        } else {
            $this->generator->setServers($servers);
        }
    }
}
