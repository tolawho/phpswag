# PHP Swagger Generator

A framework-agnostic PHP Swagger/OpenAPI generator that uses static analysis (AST) and PHPDoc. This library scans your source code and generates OpenAPI 3.0 or 3.1 specifications automatically.

## Features

- **AST-based Static Analysis**: No need to run your application.
- **Modern PHP Support**: Handles namespaces, use aliases, and complex types.
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

### Advanced Types Example

#### Models with Inheritance and Generics
```php
namespace App\Models;

/**
 * @template T
 * @property T $data
 */
class ApiResponse {}

/**
 * @property int $id
 */
class BaseModel {}

/**
 * @property string $title
 */
class Post extends BaseModel {}

/**
 * @extends ApiResponse<Post>
 */
class PostResponse extends ApiResponse {}
```

#### Controller using Complex Types
```php
namespace App\Controllers;

use App\Models\ApiResponse;
use App\Models\Post;

class PostController
{
    /**
     * @route GET /posts/{id}
     * @response 200 ApiResponse<Post>
     */
    public function show(int $id) {}
}
```

## Support Tags

- **Endpoints**:
    - `@route [METHOD] [PATH]` (e.g., `@route POST /data`)
    - `@summary [TEXT]`
    - `@description [TEXT]`
    - `@tag [NAME]`
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
