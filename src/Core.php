<?php

namespace PhpSwag;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpSwag\IR\RouteDefinition;
use PhpSwag\IR\SchemaDefinition;
use PhpSwag\Attributes\AttributeParser;

class Core
{
    private Scanner $scanner;
    private Parser $parser;
    private DocBlockCollector $docCollector;
    private Generator $generator;
    private SchemaRegistry $schemaRegistry;
    private TypeMappingRegistry $typeMappingRegistry;
    private ?Cache\CacheInterface $cache = null;
    private TypeAnalyzer $typeAnalyzer;
    private Metadata\GlobalMetadataDiscoverer $metadataDiscoverer;
    private AttributeParser $attributeParser;

    /** @var array<string, TagParser\TagParserInterface> */
    private array $tagParsers = [];

    /** @var array<string, TagParser\SchemaTagParserInterface> */
    private array $schemaTagParsers = [];

    /** @var array<string, array{node: Class_|Trait_|Enum_|Interface_, nameResolver: NameResolver, filePath: string}> */
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
    /** @var array<int, array<string, mixed>> */
    private array $globalServers = [];

    public function __construct(
        ?Scanner $scanner = null,
        ?Parser $parser = null,
        ?DocBlockCollector $docCollector = null,
        ?SchemaRegistry $schemaRegistry = null,
        ?TypeMappingRegistry $typeMappingRegistry = null,
        ?Generator $generator = null,
        ?TypeAnalyzer $typeAnalyzer = null,
        ?Metadata\GlobalMetadataDiscoverer $metadataDiscoverer = null
    ) {
        $this->scanner = $scanner ?? new Scanner();
        $this->parser = $parser ?? new Parser();
        $this->docCollector = $docCollector ?? new DocBlockCollector();
        $this->schemaRegistry = $schemaRegistry ?? new SchemaRegistry();
        $this->typeMappingRegistry = $typeMappingRegistry ?? new TypeMappingRegistry();
        $this->generator = $generator ?? new Generator($this->schemaRegistry);
        $this->typeAnalyzer = $typeAnalyzer ?? new TypeAnalyzer();
        $this->metadataDiscoverer = $metadataDiscoverer ?? new Metadata\GlobalMetadataDiscoverer($this->docCollector);
        $this->attributeParser = new AttributeParser();

        $this->registerTagParser(new TagParser\RouteTagParser());
        $this->registerTagParser(new TagParser\ResponseTagParser($this->typeAnalyzer, $this->docCollector));
        $this->registerTagParser(new TagParser\ParamTagParser());
        $this->registerTagParser(new TagParser\BodyTagParser());
        $this->registerTagParser(new TagParser\SecurityTagParser());
        $this->registerTagParser(new TagParser\BasicMethodTagParser());

        $this->registerSchemaTagParser(new TagParser\ExtendsTagParser($this->docCollector, $this->schemaRegistry));
        $this->registerSchemaTagParser(new TagParser\ClassMetadataTagParser());
        $this->registerSchemaTagParser(new TagParser\PropertyTagParser($this->typeAnalyzer));
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function registerTagParser(TagParser\TagParserInterface $parser): void
    {
        foreach ($parser->getSupportedTags() as $tag) {
            $this->tagParsers[$tag] = $parser;
        }
    }

    /**
     * @return array<string, TagParser\TagParserInterface>
     */
    public function getTagParsers(): array
    {
        return $this->tagParsers;
    }

    public function registerSchemaTagParser(TagParser\SchemaTagParserInterface $parser): void
    {
        foreach ($parser->getSupportedTags() as $tag) {
            $this->schemaTagParsers[$tag] = $parser;
        }
    }

    /**
     * @return array<string, TagParser\SchemaTagParserInterface>
     */
    public function getSchemaTagParsers(): array
    {
        return $this->schemaTagParsers;
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
     * @return array<string, mixed>
     */
    public function generateSpecArray(array $paths): array
    {
        $this->analyze($paths);
        return $this->generator->getSpecArray();
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
        $discovered = $this->metadataDiscoverer->discover(
            $code,
            $filePath,
            $this->globalMetadata,
            $this->metadataSources
        );

        $this->globalMetadata = array_merge($this->globalMetadata, $discovered['globalMetadata']);
        $this->metadataSources = array_merge($this->metadataSources, $discovered['metadataSources']);
        $this->securitySchemes = array_merge($this->securitySchemes, $discovered['securitySchemes']);
        $this->globalSecurity = array_merge($this->globalSecurity, $discovered['globalSecurity']);
        $this->globalTags = array_merge($this->globalTags, $discovered['globalTags']);
        $this->globalServers = array_merge($this->globalServers, $discovered['globalServers']);

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

        $contact = $this->cliOverrides['contact'] ?? null;
        if ($contact === null) {
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
        }
        if (!empty($contact)) {
            $this->generator->setContact($contact);
        }

        $license = $this->cliOverrides['license'] ?? null;
        if ($license === null) {
            $license = [];
            if (isset($this->globalMetadata['@license.name'])) {
                $license['name'] = $this->globalMetadata['@license.name'];
            }
            if (isset($this->globalMetadata['@license.url'])) {
                $license['url'] = $this->globalMetadata['@license.url'];
            }
        }
        if (!empty($license)) {
            $this->generator->setLicense($license);
        }

        $servers = $this->cliOverrides['servers'] ?? null;
        if ($servers !== null) {
            $this->generator->setServers($servers);
        } elseif (!empty($this->globalServers)) {
            $this->generator->setServers($this->globalServers);
        } else {
            $host = $this->cliOverrides['host'] ?? $this->globalMetadata['@host'] ?? null;
            if ($host) {
                $this->generator->setServers([['url' => $host]]);
            }
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
        if (
            $stmt instanceof Class_
            || $stmt instanceof Trait_
            || $stmt instanceof Enum_
            || $stmt instanceof Interface_
        ) {
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
                typeArguments: $typeArguments,
                file: $this->currentlyAnalyzingFile,
                line: $stmt->getStartLine()
            ));
        }
    }

    private function analyzeClass(string $fqcn, Class_|Trait_|Enum_|Interface_ $stmt, NameResolver $nameResolver): void
    {
        $schema = $this->schemaRegistry->get($fqcn);
        // @phpstan-ignore-next-line
        if (PHP_VERSION_ID >= 80100 && function_exists('enum_exists') && enum_exists($fqcn)) {
            $reflection = new \ReflectionEnum($fqcn);
            $isBacked = $reflection->isBacked();
            $cases = $reflection->getCases();
            $enumType = 'string';
            $enumValues = [];

            if ($isBacked) {
                /** @var \ReflectionNamedType $backingType */
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
        $phpDocTags = $this->docCollector->collectTags($docComment, $docStartLine, $this->currentlyAnalyzingFile);

        $attrs = $this->attributeParser->parse($stmt->attrGroups, $nameResolver, $this->currentlyAnalyzingFile);
        foreach ($attrs as $attr) {
            if ($attr['class'] === 'PhpSwag\Attributes\Tag') {
                $tagName = $attr['arguments']['name'] ?? '';
                $tagDesc = $attr['arguments']['description'] ?? null;
                if ($tagName !== '') {
                    $tagData = ['name' => $tagName];
                    if ($tagDesc !== null && $tagDesc !== '') {
                        $tagData['description'] = $tagDesc;
                    }
                    $hasTag = isset($this->globalTags[$tagName]);
                    $hasDesc = isset($tagData['description']);
                    $hasExistingDesc = isset($this->globalTags[$tagName]['description']);
                    if (!$hasTag || ($hasDesc && !$hasExistingDesc)) {
                        $this->globalTags[$tagName] = $tagData;
                    }
                }
            }
        }
        $tags = $this->mergeTagsAndAttributes($phpDocTags, $attrs);

        $context = new TagParser\SchemaContext($schema, $nameResolver);

        // First pass: class metadata and inheritance
        foreach ($tags as $tag) {
            $tagName = $tag['name'];
            if (isset($this->schemaTagParsers[$tagName])) {
                $this->schemaTagParsers[$tagName]->parse($tag, $context, $typeResolver);
            }
        }

        // Second pass: property definitions from class docblock
        foreach ($tags as $tag) {
            if ($tag['name'] === '@property') {
                if (isset($this->schemaTagParsers['@property'])) {
                    $this->schemaTagParsers['@property']->parse($tag, $context, $typeResolver);
                }
            }
        }

        // Third pass: property definitions from class member variables
        foreach ($stmt->stmts as $member) {
            if ($member instanceof Property) {
                 $propDoc = $member->getDocComment()?->getText() ?? '';
                 $propStartLine = $member->getDocComment()?->getStartLine();
                 $propPhpDocTags = $this->docCollector->collectTags(
                     $propDoc,
                     $propStartLine,
                     $this->currentlyAnalyzingFile
                 );

                 $propAttrs = $this->attributeParser->parse(
                     $member->attrGroups,
                     $nameResolver,
                     $this->currentlyAnalyzingFile
                 );

                $propName = $member->props[0]->name->toString();
                foreach ($propAttrs as &$pAttr) {
                    if ($pAttr['class'] === Attributes\Property::class) {
                        if (!isset($pAttr['arguments']['name'])) {
                            $pAttr['arguments']['name'] = $propName;
                        }
                        if (!isset($pAttr['arguments']['type']) && $member->type !== null) {
                            $pAttr['arguments']['type'] = $this->resolveTypeHint($member->type, $nameResolver);
                        }
                    }
                }
                unset($pAttr);

                $propTags = $this->mergeTagsAndAttributes($propPhpDocTags, $propAttrs);

                $explicitRequired = null;
                foreach ($propTags as $t) {
                    if ($t['name'] === '@required') {
                        $val = trim($t['value'] ?? '');
                        $explicitRequired = (strtolower($val) === 'false') ? false : true;
                    }
                }

                foreach ($propTags as $pTag) {
                    if ($pTag['name'] === '@var' || $pTag['name'] === '@property') {
                        $pTag['explicitRequired'] = $explicitRequired ?? $pTag['explicitRequired'] ?? null;
                        $pTag['hasDefault'] = ($member->props[0]->default !== null);
                        $pTag['typeHint'] = $member->type;
                        if (empty($pTag['propertyName'])) {
                            $pTag['propertyName'] = $propName;
                        }

                        $parserName = isset($this->schemaTagParsers[$pTag['name']]) ? $pTag['name'] : '@var';
                        if (isset($this->schemaTagParsers[$parserName])) {
                            $this->schemaTagParsers[$parserName]->parse($pTag, $context, $typeResolver);
                        }
                    }
                }
            }

            if ($member instanceof ClassMethod) {
                $this->analyzeMethod(
                    $member,
                    $typeResolver,
                    $nameResolver,
                    $context->classTags,
                    $context->classSecurity,
                    $context->classAccept,
                    $context->classProduce
                );
            }
        }

        if ($context->isSchema || !empty($schema->templates) || $stmt instanceof Trait_) {
            $schema->properties = $context->properties;
        }
    }

    /**
     * @param array<int, string> $classTags
     * @param array<int, array<string, array<int, string>>> $classSecurity
     */
    private function analyzeMethod(
        ClassMethod $member,
        TypeResolver $typeResolver,
        NameResolver $nameResolver,
        array $classTags = [],
        array $classSecurity = [],
        ?string $classAccept = null,
        ?string $classProduce = null
    ): void {
        $methodDoc = $member->getDocComment()?->getText() ?? '';
        $methodStartLine = $member->getDocComment()?->getStartLine();
        $methodPhpDocTags = $this->docCollector->collectTags(
            $methodDoc,
            $methodStartLine,
            $this->currentlyAnalyzingFile
        );

        $methodAttrs = $this->attributeParser->parse(
            $member->attrGroups,
            $nameResolver,
            $this->currentlyAnalyzingFile
        );

        foreach ($methodAttrs as $attr) {
            if ($attr['class'] === 'PhpSwag\Attributes\Tag') {
                $tagName = $attr['arguments']['name'] ?? '';
                $tagDesc = $attr['arguments']['description'] ?? null;
                if ($tagName !== '') {
                    $tagData = ['name' => $tagName];
                    if ($tagDesc !== null && $tagDesc !== '') {
                        $tagData['description'] = $tagDesc;
                    }
                    $hasTag = isset($this->globalTags[$tagName]);
                    $hasDesc = isset($tagData['description']);
                    $hasExistingDesc = isset($this->globalTags[$tagName]['description']);
                    if (!$hasTag || ($hasDesc && !$hasExistingDesc)) {
                        $this->globalTags[$tagName] = $tagData;
                    }
                }
            }
        }

        foreach ($member->params as $param) {
            if (!$param->var instanceof Node\Expr\Variable || !is_string($param->var->name)) {
                continue;
            }
            $paramName = $param->var->name;
            $paramAttrs = $this->attributeParser->parse(
                $param->attrGroups,
                $nameResolver,
                $this->currentlyAnalyzingFile
            );

            $type = $this->resolveTypeHint($param->type, $nameResolver);

            foreach ($paramAttrs as &$pAttr) {
                if (
                    in_array($pAttr['class'], [
                    Attributes\QueryParam::class,
                    Attributes\PathParam::class,
                    Attributes\HeaderParam::class,
                    Attributes\CookieParam::class
                    ])
                ) {
                    if (!isset($pAttr['arguments']['name'])) {
                        $pAttr['arguments']['name'] = $paramName;
                    }
                }
                if ($pAttr['class'] === Attributes\RequestBody::class) {
                    if (!isset($pAttr['arguments']['type'])) {
                        $pAttr['arguments']['type'] = $type;
                    }
                }
                if (
                    in_array($pAttr['class'], [
                    Attributes\QueryParam::class,
                    Attributes\PathParam::class,
                    Attributes\HeaderParam::class,
                    Attributes\CookieParam::class
                    ])
                ) {
                    if (!isset($pAttr['arguments']['type'])) {
                        $pAttr['arguments']['type'] = $type;
                    }
                }
            }
            unset($pAttr);

            foreach ($paramAttrs as $pAttr) {
                $methodAttrs[] = $pAttr;
            }
        }

        $tags = $this->mergeTagsAndAttributes($methodPhpDocTags, $methodAttrs);

        $context = new TagParser\RouteContext($classTags);

        foreach ($tags as $tag) {
            $tagName = $tag['name'];

            if (str_starts_with($tagName, '@x-')) {
                $extName = substr($tagName, 1);
                $val = $tag['value'];
                if (str_starts_with($val, '{') || str_starts_with($val, '[')) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $val = $decoded;
                    }
                }
                $context->extensions[$extName] = $val;
                continue;
            }

            if (isset($this->tagParsers[$tagName])) {
                $this->tagParsers[$tagName]->parse($tag, $context, $typeResolver);
            }
        }

        if (!$context->hasMethodSecurity) {
            $context->security = $classSecurity;
        }
        if (!$context->hasMethodAccept) {
            $context->accept = $classAccept;
        }
        if (!$context->hasMethodProduce) {
            $context->produce = $classProduce;
        }
        $context->tags = array_values(array_unique($context->tags));

        if ($context->routeTag) {
            $routeParts = explode(' ', $context->routeTag);
            $path = $routeParts[1];

            // Auto-inference from method parameters
            foreach ($member->params as $param) {
                if (!$param->var instanceof Node\Expr\Variable || !is_string($param->var->name)) {
                    continue;
                }
                $paramName = $param->var->name;

                $paramAttrs = $this->attributeParser->parse(
                    $param->attrGroups,
                    $nameResolver,
                    $this->currentlyAnalyzingFile
                );
                if (!empty($paramAttrs)) {
                    continue;
                }

                // Skip if already defined by explicit tags
                $exists = false;
                foreach ($context->parameters as $p) {
                    if ($p['name'] === $paramName) {
                        $exists = true;
                        break;
                    }
                }
                if ($exists) {
                    continue;
                }

                $type = $this->resolveTypeHint($param->type, $nameResolver);

                $schema = $typeResolver->resolve(
                    $this->docCollector->parseType($type),
                    $param->getStartLine(),
                    $this->currentlyAnalyzingFile
                );

                // If it's a class and not primitive, infer as requestBody if not already set
                $isPrimitive = in_array(ltrim($type, '\\'), ['int', 'string', 'bool', 'float', 'array', 'mixed']);

                if (!$isPrimitive && $context->requestBody === null) {
                    $context->requestBody = [
                        'schema' => $schema,
                        'description' => 'Auto-inferred from method parameter $' . $paramName
                    ];
                } else {
                    $in = 'query';
                    if (strpos($path, '{' . $paramName . '}') !== false) {
                        $in = 'path';
                    }

                    $context->parameters[] = [
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
                summary: $context->summary,
                description: $context->description,
                tags: $context->tags,
                responses: $context->responses,
                parameters: $context->parameters,
                requestBody: $context->requestBody,
                security: $context->security,
                responseDescriptions: $context->responseDescriptions,
                accept: $context->accept,
                produce: $context->produce,
                operationId: $context->operationId,
                deprecated: $context->deprecated,
                extensions: $context->extensions,
                file: $this->currentlyAnalyzingFile,
                line: $member->getStartLine()
            ));
        }
    }

    /**
     * Merges PHPDoc tags and parsed PHP 8 Attributes applying the smart merge strategy.
     *
     * @param array<int, array<string, mixed>> $phpDocTags
     * @param array<int, array{class: string, arguments: array<string, mixed>, line: int, file: string}> $attrs
     * @return array<int, array<string, mixed>>
     */
    private function mergeTagsAndAttributes(array $phpDocTags, array $attrs): array
    {
        $attributeMapper = new Attributes\AttributeMapper($this->docCollector);
        $attrTags = [];
        foreach ($attrs as $attr) {
            $mapped = $attributeMapper->map($attr);
            foreach ($mapped as $t) {
                $attrTags[] = $t;
            }
        }

        if (empty($attrTags)) {
            return $phpDocTags;
        }

        $overriddenSingleValues = [];
        $overriddenResponses = [];
        $overriddenParameters = [];
        $overriddenProperties = [];
        $hasAttrRequestBody = false;

        foreach ($attrTags as $tag) {
            $name = $tag['name'];

            if (in_array($name, ['@summary', '@description', '@operationId', '@deprecated', '@title'])) {
                $overriddenSingleValues[$name] = true;
            }

            if (in_array($name, ['@response', '@success', '@failure'])) {
                if (preg_match('/^(default|\d+)/i', $tag['value'], $matches)) {
                    $overriddenResponses[strtolower($matches[1])] = true;
                }
            }

            if (in_array($name, ['@query', '@path', '@header', '@cookie'])) {
                if (!empty($tag['propertyName'])) {
                    $overriddenParameters[$tag['propertyName']] = true;
                }
            }

            if (in_array($name, ['@property', '@var'])) {
                if (!empty($tag['propertyName'])) {
                    $overriddenProperties[$tag['propertyName']] = true;
                }
            }

            if ($name === '@body') {
                $hasAttrRequestBody = true;
            }
        }

        $filteredPhpDocTags = array_filter($phpDocTags, function ($tag) use (
            $overriddenSingleValues,
            $overriddenResponses,
            $overriddenParameters,
            $overriddenProperties,
            $hasAttrRequestBody
        ) {
            $name = $tag['name'];

            if (isset($overriddenSingleValues[$name])) {
                return false;
            }

            if (in_array($name, ['@response', '@success', '@failure'])) {
                if (preg_match('/^(default|\d+)/i', $tag['value'], $matches)) {
                    $code = strtolower($matches[1]);
                    if (isset($overriddenResponses[$code])) {
                        return false;
                    }
                }
            }

            if (in_array($name, ['@query', '@path', '@header', '@cookie'])) {
                if (!empty($tag['propertyName']) && isset($overriddenParameters[$tag['propertyName']])) {
                    return false;
                }
            }

            if (in_array($name, ['@property', '@var'])) {
                if (!empty($tag['propertyName']) && isset($overriddenProperties[$tag['propertyName']])) {
                    return false;
                }
            }

            if ($name === '@body' && $hasAttrRequestBody) {
                return false;
            }

            return true;
        });

        return array_merge(array_values($filteredPhpDocTags), $attrTags);
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
        $this->cliOverrides['contact'] = $contact;
    }

    /**
     * @param array<string, mixed>|null $license
     */
    public function setLicense(?array $license): void
    {
        $this->cliOverrides['license'] = $license;
    }

    /**
     * @param array<int, array<string, mixed>> $servers
     */
    public function setServers(array $servers): void
    {
        $this->cliOverrides['servers'] = $servers;
        if (isset($servers[0]['url'])) {
            $this->cliOverrides['host'] = $servers[0]['url'];
        }
    }

    private function resolveTypeHint(?Node $typeNode, NameResolver $nameResolver): string
    {
        if ($typeNode === null) {
            return 'mixed';
        }
        if ($typeNode instanceof Node\Identifier) {
            return $typeNode->toString();
        }
        if ($typeNode instanceof Node\Name) {
            $resolved = $typeNode->getAttribute('resolvedName');
            if ($resolved instanceof Node\Name) {
                return '\\' . $resolved->toString();
            }
            return $typeNode->toString();
        }
        if ($typeNode instanceof Node\NullableType) {
            return $this->resolveTypeHint($typeNode->type, $nameResolver);
        }
        if ($typeNode instanceof Node\UnionType) {
            $types = [];
            foreach ($typeNode->types as $subType) {
                $resolvedSub = $this->resolveTypeHint($subType, $nameResolver);
                if ($resolvedSub !== 'null') {
                    $types[] = $resolvedSub;
                }
            }
            if (empty($types)) {
                return 'mixed';
            }
            return implode('|', $types);
        }
        if ($typeNode instanceof Node\IntersectionType) {
            $types = [];
            foreach ($typeNode->types as $subType) {
                $types[] = $this->resolveTypeHint($subType, $nameResolver);
            }
            return implode('&', $types);
        }
        return 'mixed';
    }

    public function setCache(Cache\CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function enableCache(string $cacheFilePath): void
    {
        $this->cache = new Cache\FileCache($cacheFilePath);
    }
}
