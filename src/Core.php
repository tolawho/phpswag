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
    private ?Cache\CacheInterface $cache = null;

    /** @var array<string, array{node: Class_|Trait_, nameResolver: NameResolver, filePath: string}> */
    private array $discoveredClasses = [];

    private bool $isAnalyzed = false;
    private ?string $currentlyAnalyzingFile = null;

    /** @var array<string, mixed> */
    private array $globalMetadata = [];
    /** @var array<string, string> */
    private array $metadataSources = [];
    /** @var array<string, mixed> */
    private array $cliOverrides = [];

    /** @var array<string, array<string, mixed>> */
    private array $securitySchemes = [];
    /** @var array<int, array<string, array<int, string>>> */
    private array $globalSecurity = [];

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

    /**
     * @param array<int, string> $paths
     */
    public function generate(array $paths): string
    {
        return $this->generateYaml($paths);
    }

    /**
     * @param array<int, string> $paths
     */
    public function generateYaml(array $paths): string
    {
        $this->analyze($paths);
        return $this->generator->generateYaml();
    }

    /**
     * @param array<int, string> $paths
     */
    public function generateJson(array $paths): string
    {
        $this->analyze($paths);
        return $this->generator->generateJson();
    }

    /**
     * @param array<int, string> $paths
     */
    private function analyze(array $paths): void
    {
        if ($this->isAnalyzed) {
            return;
        }

        $this->scanner->setPaths($paths);
        $files = $this->scanner->scan();

        $cachedFilesData = [];
        $filesToDiscover = [];

        foreach ($files as $file) {
            $hash = md5_file($file);
            $isCached = $this->cache !== null
                && ($cached = $this->cache->get($file)) !== null
                && isset($cached['hash'])
                && $cached['hash'] === $hash;

            if ($isCached) {
                $cachedFilesData[$file] = $cached;
            } else {
                $filesToDiscover[] = $file;
            }
        }

        // 1. Process cached files first
        foreach ($cachedFilesData as $file => $cached) {
            // Restore global metadata
            $this->globalMetadata = array_merge($this->globalMetadata, $cached['globalMetadata']);
            $this->securitySchemes = array_merge($this->securitySchemes, $cached['securitySchemes']);
            $this->globalSecurity = array_merge($this->globalSecurity, $cached['globalSecurity']);
            $this->metadataSources = array_merge($this->metadataSources, $cached['metadataSources']);

            // Restore schemas
            foreach ($cached['schemas'] as $schema) {
                $this->schemaRegistry->register($schema);
            }

            // Restore custom schema IDs
            foreach ($cached['customSchemaIds'] as $fqcn => $schemaId) {
                $this->schemaRegistry->setCustomSchemaId($fqcn, $schemaId);
            }

            // Restore routes
            foreach ($cached['routes'] as $route) {
                $this->generator->addRoute($route);
            }
        }

        // 2. Discover non-cached files
        $fileDiscoverResults = [];
        foreach ($filesToDiscover as $file) {
            $this->currentlyAnalyzingFile = $file;

            $schemasBefore = $this->schemaRegistry->getAll();
            $metadataBefore = $this->globalMetadata;
            $securitySchemesBefore = $this->securitySchemes;
            $globalSecurityBefore = $this->globalSecurity;
            $metadataSourcesBefore = $this->metadataSources;

            $this->discoverFile($file);

            $schemasAfter = $this->schemaRegistry->getAll();
            $newSchemas = array_diff_key($schemasAfter, $schemasBefore);

            $newMetadata = array_diff_key($this->globalMetadata, $metadataBefore);
            $newSecuritySchemes = array_diff_key($this->securitySchemes, $securitySchemesBefore);
            $newGlobalSecurity = array_slice($this->globalSecurity, count($globalSecurityBefore));
            $newMetadataSources = array_diff_key($this->metadataSources, $metadataSourcesBefore);

            $fileDiscoverResults[$file] = [
                'hash' => md5_file($file),
                'schemas' => $newSchemas,
                'globalMetadata' => $newMetadata,
                'securitySchemes' => $newSecuritySchemes,
                'globalSecurity' => $newGlobalSecurity,
                'metadataSources' => $newMetadataSources,
                'customSchemaIds' => [],
                'routes' => [],
            ];

            $this->currentlyAnalyzingFile = null;
        }

        $this->applyGlobalMetadata();

        // 3. Analyze classes from non-cached files
        foreach ($this->discoveredClasses as $fqcn => $data) {
            $file = $data['filePath'];
            $this->currentlyAnalyzingFile = $file;

            $schemasBefore = $this->schemaRegistry->getAll();
            $customIdsBefore = $this->schemaRegistry->getCustomSchemaIds();
            $routesCountBefore = count($this->generator->getRoutes());

            $this->analyzeClass($fqcn, $data['node'], $data['nameResolver']);

            $schemasAfter = $this->schemaRegistry->getAll();
            $customIdsAfter = $this->schemaRegistry->getCustomSchemaIds();
            $routesAfter = $this->generator->getRoutes();

            $newSchemas = array_diff_key($schemasAfter, $schemasBefore);
            $newCustomIds = array_diff_key($customIdsAfter, $customIdsBefore);
            $newRoutes = array_slice($routesAfter, $routesCountBefore);

            if (isset($fileDiscoverResults[$file])) {
                $fileDiscoverResults[$file]['schemas'] = array_merge(
                    $fileDiscoverResults[$file]['schemas'],
                    $newSchemas
                );
                $fileDiscoverResults[$file]['customSchemaIds'] = array_merge(
                    $fileDiscoverResults[$file]['customSchemaIds'],
                    $newCustomIds
                );
                $fileDiscoverResults[$file]['routes'] = array_merge(
                    $fileDiscoverResults[$file]['routes'],
                    $newRoutes
                );
            }

            $this->currentlyAnalyzingFile = null;
        }

        // 4. Save cache
        if ($this->cache !== null) {
            foreach ($fileDiscoverResults as $file => $result) {
                $this->cache->set($file, $result);
            }
        }

        $this->isAnalyzed = true;
    }

    private function discoverFile(string $filePath): void
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }
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

                $isGlobalBlock = false;
                foreach ($tags as $tag) {
                    if (
                        in_array($tag['name'], ['@title', '@version', '@description', '@host']) ||
                        str_starts_with($tag['name'], '@contact.') ||
                        str_starts_with($tag['name'], '@license.') ||
                        str_starts_with($tag['name'], '@securityDefinitions.')
                    ) {
                        $isGlobalBlock = true;
                        break;
                    }
                }

                if (!$isGlobalBlock) {
                    continue;
                }

                foreach ($tags as $tag) {
                    $tagName = $tag['name'];
                    if (
                        in_array($tagName, ['@title', '@version', '@description', '@host']) ||
                        str_starts_with($tagName, '@contact.') ||
                        str_starts_with($tagName, '@license.')
                    ) {
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
                    } elseif ($tagName === '@securityDefinitions.apikey') {
                        if (preg_match('/^(\S+)\s+(header|query|cookie)\s+(\S+)/', $tag['value'], $matches)) {
                            $this->securitySchemes[$matches[1]] = [
                                'type' => 'apiKey',
                                'in' => $matches[2],
                                'name' => $matches[3]
                            ];
                        }
                    } elseif ($tagName === '@securityDefinitions.jwt') {
                        $this->securitySchemes[$tag['value']] = [
                            'type' => 'http',
                            'scheme' => 'bearer',
                            'bearerFormat' => 'JWT'
                        ];
                    } elseif ($tagName === '@security') {
                        $this->globalSecurity = array_merge(
                            $this->globalSecurity,
                            $this->parseSecurityTag($tag['value'])
                        );
                    }
                }
            }
        }
    }

    /**
     * @return array<int, array<string, array<int, string>>>
     */
    private function parseSecurityTag(string $value): array
    {
        if (trim($value) === '') {
            return [[]]; // Represents an empty security requirement object, which means "no security"
        }

        $requirements = [];
        $parts = $this->splitCommasOutsideBrackets($value);
        $currentGroup = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (preg_match('/^([^\[]+)(?:\[(.*)\])?$/', $part, $matches)) {
                $name = trim($matches[1]);
                $scopes = isset($matches[2]) ? array_map('trim', explode(',', trim($matches[2]))) : [];
                $currentGroup[$name] = $scopes;
            }
        }
        if (!empty($currentGroup)) {
            $requirements[] = $currentGroup;
        }
        return $requirements;
    }

    /**
     * @return array<int, string>
     */
    private function splitCommasOutsideBrackets(string $str): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $parts[] = $current;
        return $parts;
    }

    private function applyGlobalMetadata(): void
    {
        $title = $this->cliOverrides['title'] ?? $this->globalMetadata['@title'] ?? null;
        if ($title) {
            $this->generator->setTitle($title);
        }

        $apiVersion = $this->cliOverrides['api-version'] ?? $this->globalMetadata['@version'] ?? null;
        if ($apiVersion) {
            $this->generator->setApiVersion($apiVersion);
        }

        $description = $this->cliOverrides['description'] ?? $this->globalMetadata['@description'] ?? null;
        if ($description) {
            $this->generator->setDescription($description);
        }

        $contact = [];
        if (isset($this->globalMetadata['@contact.name'])) {
            $contact['name'] = $this->globalMetadata['@contact.name'];
        }
        if (isset($this->globalMetadata['@contact.email'])) {
            $contact['email'] = $this->globalMetadata['@contact.email'];
        }
        if (isset($this->globalMetadata['@contact.url'])) {
            $contact['url'] = $this->globalMetadata['@contact.url'];
        }
        if (!empty($contact)) {
            $this->generator->setContact($contact);
        }

        $license = [];
        if (isset($this->globalMetadata['@license.name'])) {
            $license['name'] = $this->globalMetadata['@license.name'];
        }
        if (isset($this->globalMetadata['@license.url'])) {
            $license['url'] = $this->globalMetadata['@license.url'];
        }
        if (!empty($license)) {
            $this->generator->setLicense($license);
        }

        $host = $this->cliOverrides['host'] ?? $this->globalMetadata['@host'] ?? null;
        if ($host) {
            $this->generator->setServers([['url' => $host]]);
        }

        if (!empty($this->securitySchemes)) {
            $this->generator->setSecuritySchemes($this->securitySchemes);
        }

        if (!empty($this->globalSecurity)) {
            $this->generator->setGlobalSecurity($this->globalSecurity);
        }
    }

    private function discoverStatement(Node $stmt, NameResolver $nameResolver): void
    {
        if ($stmt instanceof Class_ || $stmt instanceof Trait_) {
            $fqcn = $nameResolver->resolve($stmt->name->toString());
            $this->discoveredClasses[$fqcn] = [
                'node' => $stmt,
                'nameResolver' => $nameResolver,
                'filePath' => $this->currentlyAnalyzingFile
            ];

            $templates = [];
            $typeArguments = [];
            $parent = null;

            $docComment = $stmt->getDocComment()?->getText() ?? '';
            $tags = $this->docCollector->collectTags($docComment);
            foreach ($tags as $tag) {
                if ($tag['name'] === '@template') {
                    $templates[] = $tag['value'];
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

                $desc = is_array($tag['description'])
                    ? ($tag['description']['description'] ?? null)
                    : ($tag['description'] ?? null);

                                $extra = is_array($tag['description']) ? $tag['description'] : [];
                unset($extra['description']);

                $properties[] = new PropertyDefinition(
                    $tag['propertyName'],
                    $propertySchema,
                    $desc,
                    $extra
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

                        $desc = is_array($pTag['description'])
                            ? ($pTag['description']['description'] ?? null)
                            : ($pTag['description'] ?? null);

                                                $extra = is_array($pTag['description']) ? $pTag['description'] : [];
                        unset($extra['description']);

                        $properties[] = new PropertyDefinition(
                            $member->props[0]->name->toString(),
                            $propertySchema,
                            $desc,
                            $extra
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
        $responseDescriptions = [];
        $parameters = [];
        $requestBody = null;
        $security = [];
        $accept = null;
        $produce = null;
        $operationId = null;
        $deprecated = false;
        $extensions = [];

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
            } elseif ($tag['name'] === '@accept' || $tag['name'] === '@consume') {
                $accept = $tag['value'];
            } elseif ($tag['name'] === '@produce') {
                $produce = $tag['value'];
            } elseif ($tag['name'] === '@operationId' || $tag['name'] === '@operationid') {
                $operationId = $tag['value'];
            } elseif ($tag['name'] === '@deprecated') {
                $deprecated = true;
            } elseif (str_starts_with($tag['name'], '@x-')) {
                $extName = substr($tag['name'], 1);
                $val = $tag['value'];
                if (str_starts_with($val, '{') || str_starts_with($val, '[')) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $val = $decoded;
                    }
                }
                $extensions[$extName] = $val;
            } elseif (in_array($tag['name'], ['@response', '@success', '@failure'])) {
                if (preg_match('/^(\d+)\s+(.*)$/', $tag['value'], $matches)) {
                    $code = $matches[1];
                    $typeAndDesc = trim($matches[2]);
                    [$typeToParse, $respDesc] = $this->splitTypeAndDescription($typeAndDesc);

                    if ($respDesc === '') {
                        if ($tag['name'] === '@success') {
                            $respDesc = 'Success';
                        } elseif ($tag['name'] === '@failure') {
                            $respDesc = 'Failure';
                        } else {
                            $respDesc = 'OK';
                        }
                    }

                    $typeNode = $this->docCollector->parseType($typeToParse);
                    $responses[$code] = $typeResolver->resolve($typeNode);
                    $responseDescriptions[$code] = $respDesc;
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
                    'description' => is_array($tag['description'])
                        ? ($tag['description']['description'] ?? null)
                        : ($tag['description'] ?? null)
                ];
            } elseif ($tag['name'] === '@security') {
                $security = array_merge($security, $this->parseSecurityTag($tag['value']));
            }
        }

        if ($routeTag) {
            $routeParts = explode(' ', $routeTag);
            $path = $routeParts[1];

            // Auto-inference from method parameters
            foreach ($member->params as $param) {
                if (!$param->var instanceof Node\Expr\Variable || !is_string($param->var->name)) {
                    continue;
                }
                $paramName = $param->var->name;

                // Skip if already defined by explicit tags
                $exists = false;
                foreach ($parameters as $p) {
                    if ($p['name'] === $paramName) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) {
                    continue;
                }

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
                requestBody: $requestBody,
                security: $security,
                responseDescriptions: $responseDescriptions,
                accept: $accept,
                produce: $produce,
                operationId: $operationId,
                deprecated: $deprecated,
                extensions: $extensions
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

    /**
     * @param array<string, mixed>|null $contact
     */
    public function setContact(?array $contact): void
    {
        $this->generator->setContact($contact);
    }

    /**
     * @param array<string, mixed>|null $license
     */
    public function setLicense(?array $license): void
    {
        $this->generator->setLicense($license);
    }

    /**
     * @param array<int, array<string, mixed>> $servers
     */
    public function setServers(array $servers): void
    {
        if (isset($servers[0]['url'])) {
            $this->cliOverrides['host'] = $servers[0]['url'];
        } else {
            $this->generator->setServers($servers);
        }
    }

    public function setCache(Cache\CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function enableCache(string $cacheFilePath): void
    {
        $this->cache = new Cache\FileCache($cacheFilePath);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitTypeAndDescription(string $str): array
    {
        $str = trim($str);
        if (preg_match('/^([a-zA-Z0-9_\\\\]+)</', $str, $matches)) {
            $base = $matches[1];
            $depth = 0;
            $typeLen = 0;
            $started = false;
            for ($i = 0; $i < strlen($str); $i++) {
                $char = $str[$i];
                if ($char === '<') {
                    $depth++;
                    $started = true;
                } elseif ($char === '>') {
                    $depth--;
                }
                if ($started && $depth === 0) {
                    $typeLen = $i + 1;
                    break;
                }
            }
            if ($typeLen > 0) {
                $type = substr($str, 0, $typeLen);
                $desc = trim(substr($str, $typeLen));
                if (str_starts_with($desc, '[]')) {
                    $type .= '[]';
                    $desc = trim(substr($desc, 2));
                }
                return [$type, $desc];
            }
        }

        $parts = preg_split('/\s+/', $str, 2);
        $type = $parts[0] ?? '';
        $desc = $parts[1] ?? '';
        return [$type, $desc];
    }
}
