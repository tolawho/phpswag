# PHP Swagger Generator

A framework-agnostic PHP Swagger/OpenAPI generator that uses static analysis (AST) and PHPDoc. This library scans your source code and generates OpenAPI 3.0 or 3.1 specifications automatically.

## Features

- **AST-based Static Analysis**: No need to run your application.
- **Modern PHP Support**: Handles namespaces, use aliases, and complex types.
- **Global API Metadata Discovery**: Automatically extracts `@title`, `@version`, `@description`, `@contact.*`, `@license.*`, and `@host` from any file.
- **Security & Authentication**: Define global security schemes (ApiKey, JWT) and apply them to endpoints or globally.
- **Comprehensive Schema Validation**: Support for `minimum`, `maximum`, `minLength`, `maxLength`, `pattern`, `format`, and `example` directly in PHPDoc.
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

// Optional: Enable caching to speed up consecutive generations
$core->enableCache('./.php-swag-cache');

$yaml = $core->generate(['./src/App']);

file_put_contents('swagger.yaml', $yaml);
```

### Global Metadata Discovery

You can define your API information in a top-level PHPDoc block in any of your scanned files:

```php
/**
 * @title My Awesome API
 * @version 2.1.0
 * @description This is a sample API for testing global metadata.
 * @contact.name John Doe
 * @contact.email john@example.com
 * @license.name MIT
 * @license.url https://opensource.org/licenses/MIT
 * @host https://api.example.com
 */
```

### Security & Authentication

Define security schemes and requirements globally or per operation:

```php
/**
 * @securityDefinitions.apikey MyApiKey header X-API-KEY
 * @securityDefinitions.jwt MyJwtAuth
 * @security MyJwtAuth
 */

class Controller {
    /**
     * @route GET /private
     * @security MyApiKey
     */
    public function secureAction() {}

    /**
     * @route GET /scoped
     * @security MyJwtAuth[read, write]
     */
    public function scopedAction() {}
}
```

- **OR logic**: Use multiple `@security` tags on a method.
- **AND logic**: Use a single tag with comma-separated schemes: `@security Key1, Key2`.


### Validation & Schema Metadata

You can add validation constraints and metadata directly in the description of `@property`, `@var`, `@query`, `@path`, etc., using a simple function-like syntax.

```php
/**
 * @query int $age User age minimum(18) maximum(100) default(20)
 * @query string $email User email format(email) example(user@example.com)
 * @query string $code Auth code pattern(^[A-Z0-9]{6}$) minLength(6) maxLength(6)
 */
```

Supported constraints:
- **Numeric**: `minimum(n)`, `maximum(n)`
- **String**: `minLength(n)`, `maxLength(n)`, `pattern(regex)`, `format(type)`
- **Common**: `enum(a,b,c)`, `default(value)`, `example(value)`

Values are automatically cast to their appropriate types (integers, floats, or strings) in the final OpenAPI output.

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

- **Global Metadata**:
    - `@title [TEXT]`
    - `@version [TEXT]`
    - `@description [TEXT]`
    - `@contact.name [TEXT]`
    - `@contact.email [TEXT]`
    - `@contact.url [TEXT]`
    - `@license.name [TEXT]`
    - `@license.url [TEXT]`
    - `@host [URL]`
- **Security**:
    - `@securityDefinitions.apikey [NAME] [IN: header|query|cookie] [KEY_NAME]`
    - `@securityDefinitions.jwt [NAME]`
    - `@security [NAME]` or `@security [NAME[scopes]]` (supports OR/AND)
- **Endpoints**:
    - `@route [METHOD] [PATH]` (e.g., `@route POST /data`)
    - `@summary [TEXT]`
    - `@description [TEXT]`
    - `@tag [NAME]`
    - `@accept [MIME_TYPE]` or `@consume [MIME_TYPE]` (e.g. `json`, `xml`, or full MIME type)
    - `@produce [MIME_TYPE]` (e.g. `json, xml` or full MIME type)
    - `@path [TYPE] $[NAME] [DESC]`
    - `@query [TYPE] $[NAME] [DESC]`
    - `@header [TYPE] $[NAME] [DESC]`
    - `@cookie [TYPE] $[NAME] [DESC]`
    - `@body [TYPE] [DESC]`
    - `@response [CODE] [TYPE] [DESC]` (e.g., `@response 200 ApiResponse<User[]> Success response`)
    - `@success [CODE] [TYPE] [DESC]` (Alias of `@response`, e.g., `@success 200 User Success`)
    - `@failure [CODE] [TYPE] [DESC]` (Alias of `@response`, e.g., `@failure 400 ErrorResponse Bad Request`)
    - `@operationId [TEXT]` (Define explicit operationId)
    - `@deprecated` (Mark the operation as deprecated)
    - `@x-[EXTENSION_NAME] [VALUE]` (Custom OpenAPI extensions, e.g. `@x-code-samples [{"lang": "PHP"}]`)
- **Models**:
    - `@property [TYPE] $[NAME] [DESCRIPTION]` (Supports validation tags in description)
    - `@var [TYPE]` (Supports validation tags in description) (for class properties)
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
- `--path`, `-p`: Path(s) to scan (can be used multiple times). Supports individual files or directories.
- `--output`, `-o`: Output file path (defaults to stdout).
- `--format`, `-f`: Output format (`yaml` or `json`). Default: `yaml`.
- `--openapi-version`: OpenAPI version (`3.0.0` or `3.1.0`). Default: `3.0.0`.
- `--filter-unused`: Filter out schemas that are not referenced by any route.
- `--title`: API Title override.
- `--api-version`: API Version override.
- `--description`: API Description override.
- `--host`: API Host/Server URL override.
- `--cache`: Enable performance caching.
- `--cache-file`: Custom cache file path (default: `./.php-swag-cache`).
