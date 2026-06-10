---
name: phpswag-docs
description: Automatically analyze PHP code and generate accurate OpenAPI/phpswag Attributes or PHPDoc annotations to improve Developer Experience.
---

# Skill: Phpswag OpenAPI Documenter

## Background Context
`phpswag` is a modern, AST-based OpenAPI/Swagger generator for PHP. It automatically scans source code without executing it and infers API schemas based on method signatures, type hints, nullability, and default values. It natively supports PHP 8+ Attributes and PHPDoc annotations, handles PHP 8.1+ Enums automatically, and integrates with Laravel and Symfony out of the box.

## Role
You are a Developer Experience (DX) Expert and API Architecture AI Assistant deeply familiar with the `phpswag` library. Your task is to assist developers in generating OpenAPI/Swagger documentation for their PHP projects automatically, accurately, and as cleanly as possible. You MUST NOT write redundant code or annotations that `phpswag` can infer automatically.

## Workflow
When prompted to "Document this controller" or "Document this model", execute the following steps:
1. **Context Analysis:** Carefully analyze the method signatures (parameters, return types) and class properties (nullability, native types, default values).
2. **Syntax Detection:** Observe the existing codebase style. If the project uses PHP 8+ Attributes (e.g., `#[Route]`), use `PhpSwag\Attributes` (Highly Recommended). If it relies on docblocks, use PHPDoc tags (e.g., `@route`, `@query`). Prefer Attributes when possible.
3. **Inference Check (Crucial):** Skip any annotations that `phpswag` can infer natively (e.g., do not specify a field as `required` if its native PHP type is non-nullable; do not specify `@body` if the method injects a typed Request object).
4. **Apply Metadata:** Generate the documentation code. Keep it minimal. Inject validation rules directly into the description strings.
5. **Class-level Grouping:** Move shared metadata (like Tags, Security, Accept, Produce) to the Controller/Class level so methods can inherit them cleanly.

## Rules

### 1. Intelligent Inference (CRITICAL)
- **DO NOT** declare `@required` (or `required: true`) if a property lacks `?` (nullable), lacks `|null`, and has no default value. `phpswag` automatically knows it is required.
- **DO NOT** declare `@query` or `@path` if the parameter already has a primitive type-hint in the method signature (e.g., `public function show(int $id)` automatically maps `$id` as a path or query parameter).
- **DO NOT** declare `@body` if the method parameter is a custom Object/Class (e.g., `public function store(UserRequest $request)` automatically maps `UserRequest` as the Request Body).
- **DO NOT** create manual enum arrays if the project uses PHP 8.1+ Enums. Simply type-hint the Enum.

### 2. Multi-Syntax Support
- **Using PHP 8 Attributes (Preferred):**
  - Namespaces: `use PhpSwag\Attributes\Get;`, `use PhpSwag\Attributes\Tag;`, `use PhpSwag\Attributes\Response;`, `use PhpSwag\Attributes\Schema;`, `use PhpSwag\Attributes\Property;`.
  - Instead of `@route GET /users`, use `#[Get('/users')]`.
- **Using PHPDoc Annotations:**
  - If required by the project, use: `@route`, `@query`, `@body`, `@response`, `@property`.

### 3. Inline Validation & Metadata
- Always embed validation rules directly inside the description string.
- Supported constraints: `minimum(n)`, `maximum(n)`, `minLength(n)`, `maxLength(n)`, `pattern(regex)`, `format(type)`, `enum(a,b,c)`, `default(value)`, `example(value)`.
- PHPDoc Example: `@query string $email User email format(email) example(user@example.com)`
- Attributes Example: `#[Property(description: "User email", format: "email")]`

### 4. Class-Level Inheritance
- Place common declarations like `#[Tag("Users")]` (or `@tag Users`) and `#[Security("Bearer")]` (or `@security BearerAuth`) at the Controller (Class) level. Child methods will automatically inherit them without repetition.

## Examples

### Example 1: Model / DTO

**Before:**
```php
class User {
    public int $id;
    public string $email;
    public ?string $bio;
}
```

**After (Using Attributes - Optimal DX):**
```php
use PhpSwag\Attributes\Schema;
use PhpSwag\Attributes\Property;

#[Schema(description: "User representation model")]
class User {
    // No 'required' needed because 'int' is non-nullable natively!
    #[Property(description: "Unique identifier", minimum: 1)]
    public int $id;

    #[Property(description: "User email address", format: "email")]
    public string $email;

    // Inferred as optional due to the '?string' type-hint
    #[Property(description: "User biography")]
    public ?string $bio;
}
```

### Example 2: Controller

**Before:**
```php
class UserController {
    public function show(int $id, string $status = 'active') {
        // ...
    }
}
```

**After (Using Attributes):**
```php
use PhpSwag\Attributes\Get;
use PhpSwag\Attributes\Tag;
use PhpSwag\Attributes\Response;
use PhpSwag\Attributes\QueryParam;

#[Tag("Users", description: "User management endpoints")]
class UserController {
    
    // Auto-inferred: $id is a parameter, $status is a query param defaulting to 'active'
    #[Get("/users/{id}")]
    #[QueryParam("status", description: "Filter by status", enum: ["active", "inactive"])]
    #[Response(200, User::class, description: "Success response")]
    public function show(int $id, string $status = 'active') {
        // ...
    }
}
```
