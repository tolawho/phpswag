# PHP Swagger Generator

A framework-agnostic PHP Swagger/OpenAPI generator that uses static analysis (AST) and PHPDoc. This library scans your source code and generates OpenAPI 3.0 or 3.1 specifications automatically.

## Features

- **AST-based Static Analysis**: No need to run your application.
- **Modern PHP Support**: Handles namespaces, use aliases, and complex types.
- **Auto-inference**: Automatically resolve route parameters and request bodies from method signatures.
- **Advanced Type Resolution**:
    - Primitives: `int`, `string`, `bool`, `float`.
    - Nullable types: `?string` or `string|null`.
    - Array types: `User[]` or `array<User>`.
    - Class references: Automatically resolves FQCN and creates schemas.
- **Advanced OOP Support**:
    - **Inheritance**: Properties from parent classes are automatically merged into child schemas.
    - **Traits**: Supports `use Trait` with property merging.
    - **Overrides**: Child classes can override parent property types and descriptions.
- **Powerful Generics**:
    - Supports `@template` in class docblocks.
    - Handles nested generics like `ApiResponse<Collection<User>>`.
    - Supports generic inheritance (e.g., `class UserResponse extends ApiResponse<User>`).
    - Uses clean schema naming: `ApiResponse.User`.
- **OpenAPI 3.0 & 3.1**: Supports both versions, with automatic conversion of nullable types for 3.1.
- **Schema Registry**: Handles circular references and avoids duplicate definitions.

## Installation

```bash
composer require php-swag/php-swag
```

## Usage

### Simple Execution
```php
use PhpSwag\Core;

$core = new Core();
$core->setOpenApiVersion('3.1.0'); // Optional, defaults to 3.0.0
$yaml = $core->generate(['./src/App']);

file_put_contents('swagger.yaml', $yaml);
```

### Route Parameters Handling

The library supports explicit tags and auto-inference (inspired by swaggo).

```php
/**
 * @route GET /users/{id}
 * @path int $id User unique ID
 * @query string $status Filter by status enum(active,inactive) default(active)
 */
public function show(int $id, string $status) {}
```

- **Explicit Tags**: `@path`, `@query`, `@header`, `@cookie`, `@body`.
- **Metadata**: Support `enum(a,b,c)` and `default(value)` in descriptions.
- **Auto-inference**: If no tags are provided, parameters are inferred from the method signature. Primitive types match path/query, and class types match the request body.

## Support Tags

- **Endpoints**:
    - `@route [METHOD] [PATH]` (e.g., `@route POST /data`)
    - `@summary [TEXT]`
    - `@description [TEXT]`
    - `@tag [NAME]`
    - `@path [TYPE] $[NAME] [DESC]`
    - `@query [TYPE] $[NAME] [DESC]`
    - `@header [TYPE] $[NAME] [DESC]`
    - `@cookie [TYPE] $[NAME] [DESC]`
    - `@body [TYPE] [DESC]`
    - `@response [CODE] [TYPE]` (e.g., `@response 200 ApiResponse<User[]>`)
- **Models**:
    - `@property [TYPE] $[NAME] [DESCRIPTION]`
    - `@var [TYPE]` (for class properties)
    - `@template [NAME]` (for generics)
    - `@extends [TYPE]` or `@use [TYPE]` (for generic arguments)

## Testing

To run the unit tests:
```bash
composer install
./vendor/bin/phpunit
```

To run the example generator:
```bash
php examples/generate.php
```

### CLI Usage
You can use the CLI to generate documentation without writing any PHP code:

```bash
./vendor/bin/php-swag generate --path src/Controllers --path src/Models --output swagger.yaml
```

**Options:**
- `--path`, `-p`: Path(s) to scan (can be used multiple times).
- `--output`, `-o`: Output file path (defaults to stdout).
- `--format`, `-f`: Output format (`yaml` or `json`). Default: `yaml`.
- `--openapi-version`: OpenAPI version (`3.0.0` or `3.1.0`). Default: `3.0.0`.
- `--filter-unused`: Filter out schemas that are not referenced by any route.
