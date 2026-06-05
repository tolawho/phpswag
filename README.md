# PHP Swagger Generator

A framework-agnostic PHP Swagger/OpenAPI generator that uses static analysis (AST) and PHPDoc. This library scans your source code and generates OpenAPI 3.0 specifications automatically.

## Features

- **AST-based Static Analysis**: No need to run your application.
- **Modern PHP Support**: Handles namespaces, use aliases, and complex types.
- **Advanced Type Resolution**:
    - Primitives: `int`, `string`, `bool`, `float`.
    - Nullable types: `?string` or `string|null`.
    - Array types: `User[]` or `array<User>`.
    - Class references: Automatically resolves FQCN and creates schemas.
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
$yaml = $core->generate(['./src/App']);

file_put_contents('swagger.yaml', $yaml);
```

### Defining Endpoints (Controllers)
```php
namespace App\Controllers;

use App\Models\User;

class UserController
{
    /**
     * @route GET /users
     * @summary List all users
     * @tag User Management
     * @response 200 User[]
     */
    public function index() {}

    /**
     * @route GET /users/{id}
     * @summary Get user details
     * @description Returns a single user object.
     * @tag User Management
     * @response 200 User
     * @response 404 string
     */
    public function show(int $id) {}
}
```

### Defining Models (Schemas)
```php
namespace App\Models;

/**
 * @property int $id User ID
 * @property string $name Full Name
 * @property string|null $email Optional email
 * @property User[] $friends Nested relationship
 */
class User {}
```

## Support Tags

- **Endpoints**:
    - `@route [METHOD] [PATH]` (e.g., `@route POST /data`)
    - `@summary [TEXT]`
    - `@description [TEXT]`
    - `@tag [NAME]`
    - `@response [CODE] [TYPE]` (e.g., `@response 200 User[]`)
- **Models**:
    - `@property [TYPE] $[NAME] [DESCRIPTION]`
    - `@var [TYPE]` (for class properties)

## Testing

To run the unit tests:
```bash
./vendor/bin/phpunit
```

To run the example generator:
```bash
php examples/generate.php
```
