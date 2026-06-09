# PHP Swagger Generator

[![CI Status](https://github.com/tolawho/phpswag/actions/workflows/ci.yml/badge.svg)](https://github.com/tolawho/phpswag/actions/workflows/ci.yml)
[![Codecov Coverage](https://codecov.io/gh/tolawho/phpswag/branch/main/graph/badge.svg)](https://codecov.io/gh/tolawho/phpswag)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-blue.svg)](https://packagist.org/packages/phpswag/phpswag)
[![PHPStan Level 7](https://img.shields.io/badge/PHPStan-level%207-brightgreen.svg)](https://github.com/phpstan/phpstan)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A framework-agnostic PHP Swagger/OpenAPI generator that uses static analysis (AST) and PHPDoc. This library scans your source code and generates OpenAPI 3.0 or 3.1 specifications automatically.

## Features

- **AST-based Static Analysis**: No need to run your application.
- **Modern PHP Support**: Handles namespaces, use aliases, and complex types.
- **Global API Metadata Discovery**: Automatically extracts `@title`, `@version`, `@description`, `@contact.*`, `@license.*`, `@host`, and `@server` from any file.
- **Security & Authentication**: Define global security schemes (ApiKey, JWT) and apply them to endpoints or globally.
- **Comprehensive Schema Validation**: Support for `minimum`, `maximum`, `minLength`, `maxLength`, `pattern`, `format`, and `example` directly in PHPDoc.
- **Auto-inference**: Automatically resolve route parameters and request bodies from method signatures.
- **Intelligent Schema Inference**: Automatically determines `required` fields for Model schemas based on PHP native type-hint nullability, PHPDoc types, and default values. Override with explicit `@required` tag.
- **Advanced Type Resolution**:
    - Primitives: `int`, `string`, `bool`, `float`.
    - Nullable types: `?string` or `string|null`.
    - Array types: `User[]` or `array<User>`.
    - Map/Dictionary types: `array<string, User>` (resolves to an object with `additionalProperties` mapping to `User`).
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
- **Native PHP Enum Support (PHP 8.1+)**: Automatically extracts enum cases and types for both `BackedEnum` (string/int) and `UnitEnum`.
- **PHP 8+ Attributes Support**: Declare routing, schema, and parameter metadata directly using native PHP 8 attributes (e.g., `#[Get]`, `#[Property]`, `#[QueryParam]`, `#[Response]`).
- **OpenAPI Linter & Validator**: Detect specification integrity issues and unresolved model references with the `--validate` option.
- **Framework Integrations**: Seamless bridges for Laravel Service Providers and Symfony Bundle DI configurations.

## Installation

```bash
composer require phpswag/phpswag
```

## Usage

### Simple Execution
```php
use PhpSwag\Core;

$core = new Core();
$core->setOpenApiVersion('3.1.0'); // Optional, defaults to 3.0.0

// Optional: Enable caching to speed up consecutive generations
$core->enableCache('./.phpswag-cache');

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
 * @server https://api.production.com Production Server
 * @server https://api.staging.com Staging Server
 *
 * @tag.name Auth Authentication endpoints
 * @tag.name Users User management endpoints
 */
```

- **Multiple Servers**: You can define multiple servers using `@server [URL] [Description]`. If `@server` tags are defined, they will take precedence over `@host`.
- **Global Tag Ordering**: Explicitly define tags using `@tag.name [name] [description]` at the global level. The generated OpenAPI spec will preserve the order and descriptions of these tags. Any other tags found on endpoints that are not declared at the global level will be sorted alphabetically and appended to the end of the list.

### Controller-level Metadata & Inheritance

You can declare `@tag`, `@security`, `@accept` (or `@consume`), and `@produce` at the Controller (class) level. These act as defaults for all methods in the class:

```php
/**
 * @tag Users
 * @security BearerAuth
 * @accept json
 * @produce json
 */
class UserController {
    /**
     * @route POST /users
     * @body UserCreateRequest
     */
    public function create() {} // Inherits @tag Users, @security BearerAuth, @accept json, @produce json

    /**
     * @route GET /users/public
     * @security
     */
    public function listPublic() {} // Overrides security to "no security" (empty array)
}
```

- **Multiple/Comma-separated Tags**: You can specify multiple tags on a single line separated by commas, e.g., `@tag Auth, Users` (supported at both class and method level). Class-level and method-level tags are automatically merged.
- **Overrides**: Method-level `@security`, `@accept`/`@consume`, and `@produce` completely override the class-level defaults. An empty `@security` tag on a method overrides class security to disable authentication for that endpoint.

### Security & Authentication

Define security schemes and requirements globally or per operation:

```php
/**
 * @title Security API
 * @securityDefinitions.apikey MyApiKey header X-API-KEY
 * @securityDefinitions.jwt MyJwtAuth
 * @securityDefinitions.basic MyBasicAuth
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

Validation constraints and formats are also fully supported on the `@body` tag description (e.g., `@body string file to upload format(binary)`).

### Intelligent Schema Inference

The library automatically infers `required` fields for your component schemas based on properties' PHP type-hints, default values, and PHPDocs.

```php
class User {
    /** @var string $id */
    public string $id; // Required (non-nullable native type, no default)

    /** @var string $name */
    public string $name = 'Anonymous'; // Optional (has default value)

    /** @var string $email */
    public ?string $email; // Optional (nullable native type)

    /** @var string $bio */
    public string|null $bio; // Optional (nullable union type)

    /** @var mixed $extra */
    public mixed $extra; // Optional (mixed type can be null)

    /** @var string $status */
    public $status; // Required (no native type, but @var type is non-nullable string)

    /** @var string|null $avatar */
    public $avatar; // Optional (no native type, but @var type is nullable string)
}
```

#### Explicit `@required` Tag
You can override the automatic inference using the `@required` tag:
- **For member properties (class properties):**
  Use `@required` as a standalone tag or inline in the description:
  ```php
  class User {
      /**
       * @var string $email
       * @required
       */
      public ?string $email; // Required because of explicit @required tag

      /** @var string $name Name @required */
      public ?string $name; // Required because of inline @required

      /**
       * @var string $status
       * @required false
       */
      public string $status; // Optional because of explicit @required false
  }
  ```
- **For class-level `@property` definitions:**
  Use `@required $propertyName` in the class docblock or inline `@required`:
  ```php
  /**
   * @property string $name @required
   * @property string $email
   * @property string $status
   * 
   * @required $email
   * @required $status false
   */
  class User {}
  ```

### Native PHP Enum Support (PHP 8.1+)

The library fully supports PHP 8.1+ native Enums (both `BackedEnum` and `UnitEnum`). When an enum is referenced as a type in `@response`, `@property`, `@var`, etc., it is automatically detected and registered in the OpenAPI schemas.

- **BackedEnum (string/int)**: Automatically resolves the schema `type` to `string` or `integer` based on the backing type, and populates the `enum` array with the backing values of all cases.
- **UnitEnum**: Resolves the schema `type` to `string` and populates the `enum` array with the names of all cases.

#### Example

```php
namespace App\Enums;

// Backed Enum (string)
enum UserStatus: string {
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}

// Backed Enum (int)
enum UserRole: int {
    case Admin = 1;
    case Editor = 2;
    case User = 3;
}

// Pure Unit Enum
enum TicketPriority {
    case Low;
    case Medium;
    case High;
}
```

When you use these enums as property types or response types, they are generated in the OpenAPI specification as:

```yaml
components:
  schemas:
    App_Enums_UserStatus:
      type: string
      enum:
        - pending
        - active
        - suspended
    App_Enums_UserRole:
      type: integer
      enum:
        - 1
        - 2
        - 3
    App_Enums_TicketPriority:
      type: string
      enum:
        - Low
        - Medium
        - High
```

### Smart Type Mapping & Custom Registry

The generator includes a built-in `TypeMappingRegistry` that automatically maps common PHP and library classes to their standard OpenAPI representations without requiring you to document them or triggering unresolved class errors:

- **DateTime / Date**:
  - `DateTime`, `DateTimeImmutable`, `DateTimeInterface` map to `string` with `format: date-time`.
- **File Uploads**:
  - `Symfony\Component\HttpFoundation\File\UploadedFile`, `Psr\Http\Message\UploadedFileInterface`, `Illuminate\Http\UploadedFile` map to `string` with `format: binary`.
- **UUIDs**:
  - `Ramsey\Uuid\Uuid`, `Ramsey\Uuid\UuidInterface`, `Symfony\Component\Uid\Uuid` map to `string` with `format: uuid` (only if the classes/interfaces exist in your runtime environment).

#### Custom Type Mapping

You can register your own custom class-to-schema mappings programmatically using `getTypeMappingRegistry()`:

```php
use PhpSwag\Core;

$core = new Core();
$core->getTypeMappingRegistry()->register(
    'App\ValueObjects\Money',
    [
        'type' => 'number',
        'format' => 'money'
    ]
);
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

### PHP 8+ Attributes Support

You can document your endpoints and models using modern PHP 8+ Attributes instead of (or alongside) PHPDoc comments. Attributes offer IDE autocomplete, static checking, and clean syntax.

#### Available Attributes

All attributes are located under the `PhpSwag\Attributes` namespace:

- **Routing & Tags**:
  - `#[Route(method: string, path: string)]` or method shortcuts: `#[Get(path)]`, `#[Post(path)]`, `#[Put(path)]`, `#[Delete(path)]`.
  - `#[Tag(name: string, description: ?string = null)]` (Repeatable, can be on class/method).
  - `#[OperationId(id: string)]` (On method).
  - `#[Deprecated]` (On method).
- **Parameters & Body**:
  - `#[QueryParam]`, `#[PathParam]`, `#[HeaderParam]`, `#[CookieParam]`: Specify custom path, query, header, or cookie parameters.
  - `#[RequestBody(type: string, description: ?string = null, ...validation)]`: Define endpoint's request body schema.
- **Response & Schema**:
  - `#[Response(code: int|string, type: string, description: ?string = null)]` (Repeatable, on method).
  - `#[Schema(title: ?string = null, description: ?string = null)]` (On class).
  - `#[Property(name: ?string = null, type: ?string = null, description: ?string = null, ...validation, required: ?bool = null)]` (Repeatable on class, or single on class property).

#### Example Usage

```php
use PhpSwag\Attributes\Get;
use PhpSwag\Attributes\Tag;
use PhpSwag\Attributes\QueryParam;
use PhpSwag\Attributes\Response;
use PhpSwag\Attributes\Property;
use PhpSwag\Attributes\Schema;

#[Schema(description: "User Response Model")]
class User {
    #[Property(description: "User unique ID", minimum: 1)]
    public int $id;

    #[Property(description: "User email", format: "email")]
    public string $email;
}

#[Tag("Users")]
class UserController {
    #[Get("/users/{id}")]
    #[QueryParam("status", type: "string", description: "Filter by status", enum: ["active", "inactive"])]
    #[Response(200, User::class, description: "Success response")]
    public function show(int $id) {}
}
```

#### Smart Merge Strategy (Parallel Usage)

When both PHPDoc and PHP 8 Attributes are present:
1. **Single-value properties** (e.g., `summary`, `description`, `operationId`): Values in **Attributes** override PHPDoc. PHPDoc is used as a fallback if not declared in Attributes.
2. **Collections** (e.g., tags, security): Values from both sources are **merged** together.
3. **Keyed Collections** (e.g., query params with matching names, response codes): Attributes **override** PHPDoc for that specific key/parameter. Unmatched keys from both sources are merged.


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
    - `@server [URL] [Description]`
- **Security**:
    - `@securityDefinitions.apikey [NAME] [IN: header|query|cookie] [KEY_NAME]`
    - `@securityDefinitions.jwt [NAME]`
    - `@securityDefinitions.basic [NAME]`
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
    - `@response [CODE] [TYPE] [DESC]` (e.g., `@response 200 ApiResponse<User[]> Success response`, supports `default` code)
    - `@success [CODE] [TYPE] [DESC]` (Alias of `@response`, e.g., `@success 200 User Success`, supports `default` code)
    - `@failure [CODE] [TYPE] [DESC]` (Alias of `@response`, e.g., `@failure 400 ErrorResponse Bad Request`, supports `default` code)
    - `@operationId [TEXT]` (Define explicit operationId)
    - `@deprecated` (Mark the operation as deprecated)
    - `@x-[EXTENSION_NAME] [VALUE]` (Custom OpenAPI extensions, e.g. `@x-code-samples [{"lang": "PHP"}]`)
- **Models**:
    - `@property [TYPE] $[NAME] [DESCRIPTION]` (Supports validation tags in description)
    - `@var [TYPE]` (Supports validation tags in description) (for class properties)
    - `@required` (for member properties) or `@required [PROPERTY_NAME] [true|false]` (for class-level properties or overrides)
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

You can use the CLI to generate documentation without writing any PHP code.

#### 1. Configuration Initialization (Wizard)

To easily set up a configuration file for your project, run:

```bash
./vendor/bin/phpswag init
```

This starts an interactive wizard that asks for your project options and generates a `phpswag.yaml` file in your root folder.

#### 2. Generating Documentation

If you have a `phpswag.yaml` file in your root directory, you can simply run:

```bash
./vendor/bin/phpswag generate
```

Or, specify options on the command line (which will override values in the configuration file):

```bash
./vendor/bin/phpswag generate --path src/Controllers --path src/Models --output swagger.yaml
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
- `--cache`: Enable caching to speed up generation.
- `--cache-file`: Cache file path. Default: `./.phpswag-cache`.
- `--validate`: Run validation and linter checks on the generated specification (checks for missing title/version, structural integrity, and unresolved `$ref` schemas).

#### 3. Live Preview & Hot Reload (Watch Mode)

You can launch a built-in preview server that hosts Swagger UI and hot-reloads instantly when you modify your PHP code:

```bash
./vendor/bin/phpswag watch
```

**Options:**
- `--path`, `-p`: Path(s) to scan.
- `--output`, `-o`: Output destination file path (default: `swagger.yaml`).
- `--format`, `-f`: Output format (`yaml` or `json`).
- `--host`: Server host (default: `localhost`).
- `--port`: Server port (default: `8080`).

#### 4. Configuration File (`phpswag.yaml`)

An example `phpswag.yaml` file:

```yaml
paths:
  - src/Controllers
  - src/Models
openapi_version: 3.1.0
format: yaml
output: public/swagger.yaml
filter_unused: true
cache: true
cache_file: ./.phpswag-cache
```

---

## PHP 8+ Attributes Support

In addition to PHPDoc annotations, `phpswag` fully supports native PHP 8 Attributes. Attributes can be used side-by-side with PHPDoc annotations and follow a **Smart Merge & Override** strategy:
- Single-value metadata (e.g. `summary`, `description`, etc.) defined in Attributes will override PHPDoc definitions.
- Parameter definitions are matched by name; Attributes override PHPDoc definitions for the same parameter name.
- Collection tags (e.g. `@tag`, `@security`) defined in both places are merged.

### Example

```php
use PhpSwag\Attributes\Get;
use PhpSwag\Attributes\Tag;
use PhpSwag\Attributes\QueryParam;
use PhpSwag\Attributes\Response;
use PhpSwag\Attributes\Schema;
use PhpSwag\Attributes\Property;

#[Tag("Users")]
class UserController {
    #[Get("/users/{id}")]
    #[QueryParam("status", type: "string", description: "Filter by user status", enum: ["active", "inactive"])]
    #[Response(200, User::class, description: "Returns the requested user")]
    public function show(int $id) {}
}

#[Schema(title: "User", description: "User representation")]
class User {
    #[Property(description: "Unique identifier")]
    public int $id; // Native type hint 'int' is automatically inferred as 'integer'!

    #[Property(description: "User email address", format: "email")]
    public string $email;
}
```

---

## Framework Bridges

`phpswag` includes out-of-the-box integrations for Laravel and Symfony.

### Laravel Integration

The Laravel bridge registers config, Artisan commands, and automatic Swagger UI route mappings.

#### 1. Registration
Add the Service Provider in `config/app.php` (if not auto-discovered):
```php
'providers' => [
    // ...
    PhpSwag\Bridges\Laravel\PhpSwagServiceProvider::class,
];
```

#### 2. Configuration
Publish the configuration file:
```bash
php artisan vendor:publish --tag=phpswag-config
```
This generates `config/phpswag.php` where you can customize directories to scan, output path, API metadata, and Swagger UI routes.

#### 3. Generation & Validation
Run the Artisan command to generate the spec:
```bash
php artisan phpswag:generate
```
Pass the `--validate` flag to validate schema references and spec completeness:
```bash
php artisan phpswag:generate --validate
```

---

### Symfony Integration

The Symfony bridge provides a Bundle to load parameters into the Dependency Injection container and registers Symfony console commands.

#### 1. Registration
Register the bundle in `config/bundles.php`:
```php
return [
    // ...
    PhpSwag\Bridges\Symfony\PhpSwagBundle::class => ['all' => true],
];
```

#### 2. Configuration
Create a configuration file `config/packages/phpswag.yaml`:
```yaml
phpswag:
    paths:
        - '%kernel.project_dir%/src/Controller'
        - '%kernel.project_dir%/src/Entity'
    output: '%kernel.project_dir%/public/swagger.yaml'
    title: 'My Symfony API'
    version: '1.0.0'
```

#### 3. Generation & Validation
Run the console command:
```bash
php bin/console phpswag:generate
```
To validate the schema:
```bash
php bin/console phpswag:generate --validate
```
