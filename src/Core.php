<?php

namespace PhpSwag;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Enum_;
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
    private TypeMappingRegistry $typeMappingRegistry;
    private ?Cache\CacheInterface $cache = null;

    /** @var array<string, array{node: Class_|Trait_|Enum_, nameResolver: NameResolver, filePath: string}> */
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
    /** @var array<string, array{name: string, description?: string}> */
    private array $globalTags = [];

    public function __construct()
    {
        $this->scanner = new Scanner();
        $this->parser = new Parser();
        $this->docCollector = new DocBlockCollector();
        $this->schemaRegistry = new SchemaRegistry();
        $this->typeMappingRegistry = new TypeMappingRegistry();
        $this->generator = new Generator($this->schemaRegistry);
    }

    public function getTypeMappingRegistry(): TypeMappingRegistry
    {
        return $this->typeMappingRegistry;
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
            if (isset($cached['globalTags'])) {
                $this->globalTags = array_merge($this->globalTags, $cached['globalTags']);
            }

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
            $globalTagsBefore = $this->globalTags;

            $this->discoverFile($file);

            $schemasAfter = $this->schemaRegistry->getAll();
            $newSchemas = array_diff_key($schemasAfter, $schemasBefore);

            $newMetadata = array_diff_key($this->globalMetadata, $metadataBefore);
            $newSecuritySchemes = array_diff_key($this->securitySchemes, $securitySchemesBefore);
            $newGlobalSecurity = array_slice($this->globalSecurity, count($globalSecurityBefore));
            $newMetadataSources = array_diff_key($this->metadataSources, $metadataSourcesBefore);
            $newGlobalTags = array_diff_key($this->globalTags, $globalTagsBefore);

            $fileDiscoverResults[$file] = [
                'hash' => md5_file($file),
                'schemas' => $newSchemas,
                'globalMetadata' => $newMetadata,
                'securitySchemes' => $newSecuritySchemes,
                'globalSecurity' => $newGlobalSecurity,
                'metadataSources' => $newMetadataSources,
                'globalTags' => $newGlobalTags,
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
                $startLine = $token[2];
                $tags = $this->docCollector->collectTags($docComment, $startLine, $filePath);

                $isGlobalBlock = false;
                $hasRouteOrProperty = false;
                foreach ($tags as $tag) {
                    if (in_array($tag['name'], ['@route', '@property', '@var'])) {
                        $hasRouteOrProperty = true;
                        break;
                    }
                }
                if (!$hasRouteOrProperty) {
                    foreach ($tags as $tag) {
                        if (
                            in_array($tag['name'], ['@title', '@version', '@description', '@host']) ||
                            str_starts_with($tag['name'], '@contact.') ||
                            str_starts_with($tag['name'], '@license.') ||
                            str_starts_with($tag['name'], '@securityDefinitions.') ||
                            str_starts_with($tag['name'], '@tag.')
                        ) {
                            $isGlobalBlock = true;
                            break;
                        }
                    }
                }

                if (!$isGlobalBlock) {
                    continue;
                }

                $currentTagName = null;
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
                        } else {
                            throw new \PhpSwag\Exception\DiagnosticException(sprintf(
                                "Invalid syntax for tag '@securityDefinitions.apikey' in %s%s: "
                                . "expected format is '@securityDefinitions.apikey NAME IN KEY', got '%s'",
                                $tag['file'] ?? $filePath,
                                isset($tag['line']) ? " on line " . $tag['line'] : "",
                                $tag['value']
                            ));
                        }
                    } elseif ($tagName === '@securityDefinitions.jwt') {
                        if (trim($tag['value']) !== '') {
                            $this->securitySchemes[$tag['value']] = [
                                'type' => 'http',
                                'scheme' => 'bearer',
                                'bearerFormat' => 'JWT'
                            ];
                        } else {
                            throw new \PhpSwag\Exception\DiagnosticException(sprintf(
                                "Invalid syntax for tag '@securityDefinitions.jwt' in %s%s: "
                                . "expected format is '@securityDefinitions.jwt NAME', got empty value",
                                $tag['file'] ?? $filePath,
                                isset($tag['line']) ? " on line " . $tag['line'] : ""
                            ));
                        }
                    } elseif ($tagName === '@securityDefinitions.basic') {
                        if (trim($tag['value']) !== '') {
                            $this->securitySchemes[$tag['value']] = [
                                'type' => 'http',
                                'scheme' => 'basic'
                            ];
                        } else {
                            throw new \PhpSwag\Exception\DiagnosticException(sprintf(
                                "Invalid syntax for tag '@securityDefinitions.basic' in %s%s: "
                                . "expected format is '@securityDefinitions.basic NAME', got empty value",
                                $tag['file'] ?? $filePath,
                                isset($tag['line']) ? " on line " . $tag['line'] : ""
                            ));
                        }
                    } elseif ($tagName === '@security') {
                        $this->globalSecurity = array_merge(
                            $this->globalSecurity,
                            $this->parseSecurityTag($tag['value'])
                        );
                    } elseif ($tagName === '@tag.name') {
                        $parts = preg_split('/\s+/', $tag['value'], 2);
                        if (is_array($parts) && isset($parts[0]) && trim($parts[0]) !== '') {
                            $name = $parts[0];
                            $desc = isset($parts[1]) ? trim($parts[1]) : null;

                            $tagData = ['name' => $name];
                            if ($desc !== null && $desc !== '') {
                                $tagData['description'] = $desc;
                            }
                            $this->globalTags[$name] = $tagData;
                        } else {
                            throw new \PhpSwag\Exception\DiagnosticException(sprintf(
                                "Invalid syntax for tag '@tag.name' in %s%s: "
                                . "expected format is '@tag.name NAME [description]', got '%s'",
                                $tag['file'] ?? $filePath,
                                isset($tag['line']) ? " on line " . $tag['line'] : "",
                                $tag['value']
                            ));
                        }
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

        if (!empty($this->globalTags)) {
            $this->generator->setGlobalTags($this->globalTags);
        }
    }

    private function discoverStatement(Node $stmt, NameResolver $nameResolver): void
    {
        if ($stmt instanceof Class_ || $stmt instanceof Trait_ || $stmt instanceof Enum_) {
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
            $docStartLine = $stmt->getDocComment()?->getStartLine();
            $tags = $this->docCollector->collectTags($docComment, $docStartLine, $this->currentlyAnalyzingFile);
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
                typeArguments: $typeArguments,
                file: $this->currentlyAnalyzingFile,
                line: $stmt->getStartLine()
            ));
        }
    }

    private function analyzeClass(string $fqcn, Class_|Trait_|Enum_ $stmt, NameResolver $nameResolver): void
    {
        $schema = $this->schemaRegistry->get($fqcn);
        if (function_exists('enum_exists') && enum_exists($fqcn)) {
            $reflection = new \ReflectionEnum($fqcn);
            $isBacked = $reflection->isBacked();
            $cases = $reflection->getCases();
            $enumType = 'string';
            $enumValues = [];

            if ($isBacked) {
                $backingType = $reflection->getBackingType();
                $backingTypeName = $backingType->getName();
                $enumType = $backingTypeName === 'int' ? 'integer' : 'string';
                foreach ($cases as $case) {
                    if ($case instanceof \ReflectionEnumBackedCase) {
                        $enumValues[] = $case->getBackingValue();
                    }
                }
            } else {
                $enumType = 'string';
                foreach ($cases as $case) {
                    $enumValues[] = $case->getName();
                }
            }

            if ($schema !== null) {
                $schema->enum = $enumValues;
                $schema->enumType = $enumType;
            }
            return;
        }
        $typeResolver = new TypeResolver(
            $this->schemaRegistry,
            $nameResolver,
            $schema->templates,
            $this->typeMappingRegistry
        );
        $docComment = $stmt->getDocComment()?->getText() ?? '';
        $docStartLine = $stmt->getDocComment()?->getStartLine();
        $tags = $this->docCollector->collectTags($docComment, $docStartLine, $this->currentlyAnalyzingFile);

        $classTags = [];
        $classSecurity = [];
        $classAccept = null;
        $classProduce = null;

        foreach ($tags as $tag) {
            if ($tag['name'] === '@extends' || $tag['name'] === '@use') {
                $typeNode = $this->docCollector->parseType($tag['value']);
                if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\GenericTypeNode) {
                    $targetFqcn = $nameResolver->resolve($typeNode->type->name);
                    $targetSchema = $this->schemaRegistry->get($targetFqcn);
                    if ($targetSchema && !empty($targetSchema->templates)) {
                        foreach ($typeNode->genericTypes as $i => $argNode) {
                            $templateName = $targetSchema->templates[$i] ?? "T$i";
                            $schema->typeArguments[$templateName] = $typeResolver->resolve(
                                $argNode,
                                $tag['line'] ?? $docStartLine,
                                $this->currentlyAnalyzingFile
                            );
                        }
                    }
                }
            } elseif ($tag['name'] === '@tag') {
                $splitTags = array_filter(array_map('trim', explode(',', $tag['value'])), fn($t) => $t !== '');
                $classTags = array_merge($classTags, $splitTags);
            } elseif ($tag['name'] === '@security') {
                $classSecurity = array_merge($classSecurity, $this->parseSecurityTag($tag['value']));
            } elseif ($tag['name'] === '@accept' || $tag['name'] === '@consume') {
                $classAccept = $tag['value'];
            } elseif ($tag['name'] === '@produce') {
                $classProduce = $tag['value'];
            }
        }

        $isSchema = false;
        $properties = [];

        // Parse any class-level explicit @required tags targeting properties, e.g. @required $name or @required name
        $classExplicitRequired = [];
        foreach ($tags as $t) {
            if ($t['name'] === '@required') {
                $val = trim($t['value'] ?? '');
                if ($val !== '') {
                    if (preg_match('/^([^\s]+)(?:\s+(.*))?$/', $val, $matches)) {
                        $propName = ltrim($matches[1], '$');
                        $optVal = isset($matches[2]) ? trim($matches[2]) : '';
                        if (strtolower($optVal) === 'false') {
                            $classExplicitRequired[$propName] = false;
                        } else {
                            $classExplicitRequired[$propName] = true;
                        }
                    }
                }
            }
        }

        foreach ($tags as $tag) {
            if ($tag['name'] === '@property' && isset($tag['type'])) {
                $isSchema = true;
                $propertySchema = $typeResolver->resolve(
                    $tag['type'],
                    $tag['line'] ?? $docStartLine,
                    $this->currentlyAnalyzingFile
                );

                $desc = is_array($tag['description'])
                    ? ($tag['description']['description'] ?? null)
                    : ($tag['description'] ?? null);

                $extra = is_array($tag['description']) ? $tag['description'] : [];
                unset($extra['description']);

                $explicitRequired = $classExplicitRequired[$tag['propertyName']] ?? null;
                if ($desc !== null && stripos($desc, '@required') !== false) {
                    $explicitRequired = true;
                    $desc = preg_replace('/@required\s*/i', '', $desc);
                    $desc = trim($desc);
                }

                $hasDefault = isset($extra['default']);
                $isNullable = $this->isDocTypeNullable($tag['type']);
                $required = $this->determineRequired(
                    $tag['propertyName'],
                    $isNullable,
                    $explicitRequired,
                    $hasDefault,
                    null
                );

                $properties[] = new PropertyDefinition(
                    $tag['propertyName'],
                    $propertySchema,
                    $desc,
                    $extra,
                    $this->currentlyAnalyzingFile,
                    $tag['line'] ?? $docStartLine,
                    $required
                );
            }
        }

        foreach ($stmt->stmts as $member) {
            if ($member instanceof Property) {
                $isSchema = true;
                $propDoc = $member->getDocComment()?->getText() ?? '';
                $propStartLine = $member->getDocComment()?->getStartLine();
                $propTags = $this->docCollector->collectTags($propDoc, $propStartLine, $this->currentlyAnalyzingFile);
                foreach ($propTags as $pTag) {
                    if ($pTag['name'] === '@var' && isset($pTag['type'])) {
                        $propertySchema = $typeResolver->resolve(
                            $pTag['type'],
                            $pTag['line'] ?? $propStartLine,
                            $this->currentlyAnalyzingFile
                        );

                        $desc = is_array($pTag['description'])
                            ? ($pTag['description']['description'] ?? null)
                            : ($pTag['description'] ?? null);

                        $extra = is_array($pTag['description']) ? $pTag['description'] : [];
                        unset($extra['description']);

                        // Explicit required tag in property docblock
                        $explicitRequired = null;
                        foreach ($propTags as $t) {
                            if ($t['name'] === '@required') {
                                $val = trim($t['value'] ?? '');
                                if (strtolower($val) === 'false') {
                                    $explicitRequired = false;
                                } else {
                                    $explicitRequired = true;
                                }
                            }
                        }

                        // Also check if @required is inline in the @var tag's description
                        if ($desc !== null && stripos($desc, '@required') !== false) {
                            $explicitRequired = true;
                            $desc = preg_replace('/@required\s*/i', '', $desc);
                            $desc = trim($desc);
                        }

                        $hasDefault = ($member->props[0]->default !== null) || isset($extra['default']);
                        $isNullable = $this->isDocTypeNullable($pTag['type']);
                        $required = $this->determineRequired(
                            $member->props[0]->name->toString(),
                            $isNullable,
                            $explicitRequired,
                            $hasDefault,
                            $member->type
                        );

                        $properties[] = new PropertyDefinition(
                            $member->props[0]->name->toString(),
                            $propertySchema,
                            $desc,
                            $extra,
                            $this->currentlyAnalyzingFile,
                            $pTag['line'] ?? $propStartLine,
                            $required
                        );
                    }
                }
            }

            if ($member instanceof ClassMethod) {
                $this->analyzeMethod(
                    $member,
                    $typeResolver,
                    $classTags,
                    $classSecurity,
                    $classAccept,
                    $classProduce
                );
            }
        }

        if ($isSchema || !empty($schema->templates) || $stmt instanceof Trait_) {
            $schema->properties = $properties;
        }
    }

    /**
     * @param array<int, string> $classTags
     * @param array<int, array<string, array<int, string>>> $classSecurity
     */
    private function analyzeMethod(
        ClassMethod $member,
        TypeResolver $typeResolver,
        array $classTags = [],
        array $classSecurity = [],
        ?string $classAccept = null,
        ?string $classProduce = null
    ): void {
        $methodDoc = $member->getDocComment()?->getText() ?? '';
        $methodStartLine = $member->getDocComment()?->getStartLine();
        $tags = $this->docCollector->collectTags($methodDoc, $methodStartLine, $this->currentlyAnalyzingFile);

        $routeTag = null;
        $summary = null;
        $description = null;
        $tagsList = $classTags;
        $responses = [];
        $responseDescriptions = [];
        $parameters = [];
        $requestBody = null;
        $security = [];
        $hasMethodSecurity = false;
        $accept = null;
        $hasMethodAccept = false;
        $produce = null;
        $hasMethodProduce = false;
        $operationId = null;
        $deprecated = false;
        $extensions = [];

        foreach ($tags as $tag) {
            if ($tag['name'] === '@route') {
                if (preg_match('/^(GET|POST|PUT|DELETE|PATCH)\s+(\S+)/i', $tag['value'], $matches)) {
                    $routeTag = strtoupper($matches[1]) . ' ' . $matches[2];
                } else {
                    throw new \PhpSwag\Exception\DiagnosticException(sprintf(
                        "Invalid syntax for tag '@route' in %s%s: expected format is '@route METHOD PATH', got '%s'",
                        $tag['file'] ?? $this->currentlyAnalyzingFile ?? 'unknown',
                        isset($tag['line']) ? " on line " . $tag['line'] : "",
                        $tag['value']
                    ));
                }
            } elseif ($tag['name'] === '@summary') {
                $summary = $tag['value'];
            } elseif ($tag['name'] === '@description') {
                $description = $tag['value'];
            } elseif ($tag['name'] === '@tag') {
                $splitTags = array_filter(array_map('trim', explode(',', $tag['value'])), fn($t) => $t !== '');
                $tagsList = array_merge($tagsList, $splitTags);
            } elseif ($tag['name'] === '@accept' || $tag['name'] === '@consume') {
                $accept = $tag['value'];
                $hasMethodAccept = true;
            } elseif ($tag['name'] === '@produce') {
                $produce = $tag['value'];
                $hasMethodProduce = true;
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
                if (preg_match('/^(\d+|default)\s+(.*)$/i', $tag['value'], $matches)) {
                    $code = strtolower($matches[1]);
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
                    $responses[$code] = $typeResolver->resolve(
                        $typeNode,
                        $tag['line'] ?? $methodStartLine,
                        $this->currentlyAnalyzingFile
                    );
                    $responseDescriptions[$code] = $respDesc;
                } else {
                    throw new \PhpSwag\Exception\DiagnosticException(sprintf(
                        "Invalid syntax for tag '%s' in %s%s: "
                        . "expected format is '%s CODE TYPE [description]', got '%s'",
                        $tag['name'],
                        $tag['file'] ?? $this->currentlyAnalyzingFile ?? 'unknown',
                        isset($tag['line']) ? " on line " . $tag['line'] : "",
                        $tag['name'],
                        $tag['value']
                    ));
                }
            } elseif (in_array($tag['name'], ['@path', '@query', '@header', '@cookie'])) {
                $in = substr($tag['name'], 1);
                $parameters[] = array_merge($tag, [
                    'in' => $in,
                    'schema' => $typeResolver->resolve(
                        $tag['type'],
                        $tag['line'] ?? $methodStartLine,
                        $this->currentlyAnalyzingFile
                    ),
                    'name' => $tag['propertyName']
                ]);
            } elseif ($tag['name'] === '@body') {
                $schema = $typeResolver->resolve(
                    $tag['type'],
                    $tag['line'] ?? $methodStartLine,
                    $this->currentlyAnalyzingFile
                );
                $extra = is_array($tag['description']) ? $tag['description'] : [];
                $desc = $extra['description'] ?? null;
                unset($extra['description']);
                $validationTags = [
                    'enum', 'default', 'minimum', 'maximum', 'minLength',
                    'maxLength', 'pattern', 'format', 'example'
                ];
                foreach ($validationTags as $vTag) {
                    if (isset($extra[$vTag])) {
                        $val = $extra[$vTag];
                        if (
                            in_array(
                                $vTag,
                                ['minimum', 'maximum', 'minLength', 'maxLength', 'default', 'example']
                            )
                        ) {
                            $val = is_numeric($val)
                                ? (strpos((string)$val, '.') !== false ? (float)$val : (int)$val)
                                : $val;
                        }
                        $schema[$vTag] = $val;
                    }
                }
                $requestBody = [
                    'schema' => $schema,
                    'description' => $desc
                ];
            } elseif ($tag['name'] === '@security') {
                $hasMethodSecurity = true;
                $security = array_merge($security, $this->parseSecurityTag($tag['value']));
            }
        }

        if (!$hasMethodSecurity) {
            $security = $classSecurity;
        }
        if (!$hasMethodAccept) {
            $accept = $classAccept;
        }
        if (!$hasMethodProduce) {
            $produce = $classProduce;
        }
        $tagsList = array_values(array_unique($tagsList));

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

                $schema = $typeResolver->resolve(
                    $this->docCollector->parseType($type),
                    $param->getStartLine(),
                    $this->currentlyAnalyzingFile
                );

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
                extensions: $extensions,
                file: $this->currentlyAnalyzingFile,
                line: $member->getStartLine()
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

    private function isNativeTypeNullable(?Node $type): bool
    {
        if ($type === null) {
            return true;
        }
        if ($type instanceof Node\NullableType) {
            return true;
        }
        if ($type instanceof Node\Identifier && strtolower($type->name) === 'mixed') {
            return true;
        }
        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $subType) {
                if (
                    $subType instanceof Node\Identifier &&
                    in_array(strtolower($subType->name), ['null', 'mixed'])
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isDocTypeNullable(\PHPStan\PhpDocParser\Ast\Type\TypeNode $typeNode): bool
    {
        if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\NullableTypeNode) {
            return true;
        }
        if (
            $typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode &&
            strtolower($typeNode->name) === 'mixed'
        ) {
            return true;
        }
        if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\UnionTypeNode) {
            foreach ($typeNode->types as $type) {
                if (
                    $type instanceof \PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode &&
                    in_array(strtolower($type->name), ['null', 'mixed'])
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function determineRequired(
        string $propertyName,
        bool $isNullable,
        ?bool $explicitRequired,
        bool $hasDefault,
        ?Node $typeHint
    ): bool {
        if ($explicitRequired !== null) {
            return $explicitRequired;
        }

        // If it has a default value, it is optional (not required)
        if ($hasDefault) {
            return false;
        }

        // If there is a native type hint, use its nullability
        if ($typeHint !== null) {
            return !$this->isNativeTypeNullable($typeHint);
        }

        // Otherwise, use the PHPDoc nullability
        return !$isNullable;
    }
}
