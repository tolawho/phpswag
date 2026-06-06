# PHP Swagger Generator

A framework-agnostic PHP Swagger/OpenAPI generator that uses static analysis (AST) and PHPDoc. This library scans your source code and generates OpenAPI 3.0 or 3.1 specifications automatically.

## Features

- **AST-based Static Analysis**: No need to run your application.
- **Modern PHP Support**: Handles namespaces, use aliases, and complex types.
- **Auto-inference**: Automatically resolve route parameters and request bodies from method signatures.
- **Advanced Type Resolution & Validation** (inspired by `swaggo`):
    - Primitives: `int`, `string`, `bool`, `float`.
    - Nullable types: `?string` or `string|null`.
    - Array types: `User[]` or `array<User>`.
    - **Validation**: Support `@minimum`, `@maximum`, `@minLength`, `@maxLength`, `@pattern`, `@format`, `@example` in PHPDoc.
- **Global Metadata & Security**:
    - **Global Info**: Define `@title`, `@version`, `@description`, `@contact.*`, `@license.*`, `@host` in any PHPDoc block.
    - **Security Schemes**: Define `@securityDefinitions.apikey` and `@securityDefinitions.jwt` globally.
    - **Endpoint Security**: Apply security with `@security [Name]`.
- **Advanced OOP Support**:
    - **Inheritance**: Properties from parent classes are automatically merged into child schemas.
    - **Traits**: Supports `use Trait` with property merging.
- **Powerful Generics**:
    - Supports `@template` in class docblocks.
    - Handles nested generics like `ApiResponse<Collection<User>>`.
- **OpenAPI 3.0 & 3.1**: Supports both versions.

## Installation

```bash
composer require php-swag/php-swag
```

## Usage

### Simple Execution
```php
use PhpSwag\Core;

$core = new Core();
$yaml = $core->generateYaml(['./src/App']);

file_put_contents('swagger.yaml', $yaml);
```

### Global Configuration
You can define global API information in a central file (e.g., `index.php` or `Main.php`):

```php
/**
 * @title My Awesome API
 * @version 1.0.0
 * @description This is a sample server.
 * @contact.name API Support
 * @contact.url http://www.swagger.io/support
 * @contact.email support@swagger.io
 * @host http://api.example.com
 *
 * @securityDefinitions.apikey ApiKeyAuth header X-API-KEY
 * @securityDefinitions.jwt BearerAuth
 */
```

### Route Parameters & Validation

```php
/**
 * @route GET /users/{id}
 * @summary Get user by ID
 * @path int $id User unique ID minimum(1)
 * @query string $status Filter by status enum(active,inactive) default(active)
 * @security ApiKeyAuth
 * @success 200 User Success response
 * @failure 404 string Not Found
 */
public function show(int $id, string $status) {}
```

## Support Tags

- **Endpoints**:
    - `@route [METHOD] [PATH]`
    - `@summary [TEXT]`
    - `@description [TEXT]`
    - `@tag [NAME]`
    - `@accept [CONTENT_TYPE]` (e.g., `multipart/form-data`)
    - `@produce [CONTENT_TYPE]` (e.g., `application/xml`)
    - `@success [CODE] [TYPE] [DESC]`
    - `@failure [CODE] [TYPE] [DESC]`
    - `@security [NAME]`
    - `@path`, `@query`, `@header`, `@cookie`
- **Models**:
    - `@property [TYPE] $[NAME] [DESCRIPTION]`
    - **Validation**: `minimum(n)`, `maximum(n)`, `minLength(n)`, `maxLength(n)`, `pattern(regex)`, `format(f)`, `example(v)`, `enum(a,b)`, `default(v)`.

## Testing

To run the unit tests:
```bash
composer install
./vendor/bin/phpunit
```

### CLI Usage
```bash
./vendor/bin/php-swag generate --path src/Controllers --output swagger.yaml
```
