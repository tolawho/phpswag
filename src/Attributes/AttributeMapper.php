<?php

namespace PhpSwag\Attributes;

use PhpSwag\DocBlockCollector;

class AttributeMapper
{
    private DocBlockCollector $docCollector;

    public function __construct(DocBlockCollector $docCollector)
    {
        $this->docCollector = $docCollector;
    }

    /**
     * Maps a parsed PHP 8 Attribute into a normalized tag data array.
     *
     * @param array{class: string, arguments: array<string, mixed>, line: int, file: string} $attr
     * @return array<int, array<string, mixed>>
     */
    public function map(array $attr): array
    {
        $class = $attr['class'];
        $args = $attr['arguments'];
        $file = $attr['file'];
        $line = $attr['line'];

        $tags = [];

        switch ($class) {
            case Route::class:
                $method = $args['method'] ?? 'GET';
                $path = $args['path'] ?? '/';
                $tags[] = [
                    'name' => '@route',
                    'value' => strtoupper($method) . ' ' . $path,
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case Get::class:
            case Post::class:
            case Put::class:
            case Delete::class:
                $method = strtoupper(basename(str_replace('\\', '/', $class)));
                $path = $args['path'] ?? '/';
                $tags[] = [
                    'name' => '@route',
                    'value' => "$method $path",
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case 'Symfony\Component\Routing\Annotation\Route':
            case 'Symfony\Component\Routing\Attribute\Route':
                $path = $args['path'] ?? '/';
                $methods = $args['methods'] ?? ['GET'];
                if (is_string($methods)) {
                    $methods = [$methods];
                }
                $method = !empty($methods) ? $methods[0] : 'GET';
                $tags[] = [
                    'name' => '@route',
                    'value' => strtoupper($method) . ' ' . $path,
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case Tag::class:
                $name = $args['name'] ?? '';
                $tags[] = [
                    'name' => '@tag',
                    'value' => $name,
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case OperationId::class:
                $tags[] = [
                    'name' => '@operationId',
                    'value' => $args['id'] ?? '',
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case Deprecated::class:
                $tags[] = [
                    'name' => '@deprecated',
                    'value' => '',
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case Response::class:
                $code = $args['code'] ?? '200';
                $type = $args['type'] ?? 'string';
                $desc = $args['description'] ?? '';
                $value = "$code $type";
                if ($desc !== '') {
                    $value .= ' ' . $desc;
                }
                $tags[] = [
                    'name' => '@response',
                    'value' => $value,
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case RequestBody::class:
                $descArray = ['description' => $args['description'] ?? ''];
                foreach ($args as $k => $v) {
                    if (!in_array($k, ['type', 'description'], true) && $v !== null) {
                        $descArray[$k] = $v;
                    }
                }
                $tags[] = [
                    'name' => '@body',
                    'type' => $this->docCollector->parseType($args['type'] ?? 'mixed'),
                    'description' => $descArray,
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case QueryParam::class:
            case PathParam::class:
            case HeaderParam::class:
            case CookieParam::class:
                $paramName = substr(basename(str_replace('\\', '/', $class)), 0, -5);
                $tagName = '@' . strtolower($paramName);
                $tag = [
                    'name' => $tagName,
                    'type' => $this->docCollector->parseType($args['type'] ?? 'string'),
                    'propertyName' => $args['name'] ?? '',
                    'description' => $args['description'] ?? '',
                    'file' => $file,
                    'line' => $line
                ];
                foreach ($args as $k => $v) {
                    if (!in_array($k, ['name', 'type', 'description'], true) && $v !== null) {
                        $tag[$k] = $v;
                    }
                }
                $tags[] = $tag;
                break;

            case Property::class:
                $descArray = ['description' => $args['description'] ?? ''];
                foreach ($args as $k => $v) {
                    if (!in_array($k, ['name', 'type', 'description', 'required'], true) && $v !== null) {
                        $descArray[$k] = $v;
                    }
                }
                $tags[] = [
                    'name' => '@property',
                    'type' => $this->docCollector->parseType($args['type'] ?? 'mixed'),
                    'propertyName' => $args['name'] ?? null,
                    'description' => $descArray,
                    'explicitRequired' => $args['required'] ?? null,
                    'file' => $file,
                    'line' => $line
                ];
                break;

            case Schema::class:
                if (isset($args['title'])) {
                    $tags[] = [
                        'name' => '@title',
                        'value' => $args['title'],
                        'file' => $file,
                        'line' => $line
                    ];
                }
                if (isset($args['description'])) {
                    $tags[] = [
                        'name' => '@description',
                        'value' => $args['description'],
                        'file' => $file,
                        'line' => $line
                    ];
                }
                break;
        }

        return $tags;
    }
}
