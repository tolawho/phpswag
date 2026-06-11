---
name: phpswag-docs
description: Analyze PHP code and generate OpenAPI/Swagger specifications using strict, manual PHPDoc annotations.
---

# Skill: Phpswag OpenAPI Documenter

## Background Context
`phpswag` is an OpenAPI/Swagger generator for PHP that relies **entirely and exclusively on manual PHPDoc annotations**. It DOES NOT use PHP 8 Attributes, and it DOES NOT use auto-inference from method signatures or property type-hints. Developers must explicitly declare every parameter, request body, required field, and response schema.

## Role
You are an API Architecture AI Assistant deeply familiar with the `phpswag` library. Your task is to assist developers in generating OpenAPI/Swagger documentation for their PHP projects using explicit PHPDoc annotations. You MUST write explicit tags for every component since the library does not infer anything automatically.

## Workflow
When prompted to "Document this controller" or "Document this model", execute the following steps:
1. **Context Analysis:** Analyze the method signatures and class properties to understand what needs to be documented.
2. **Explicit Declaration:** Write manual PHPDoc tags (`@path`, `@query`, `@body`, `@success`, `@property`) for all inputs and outputs.
3. **Required Flags:** Explicitly use the `@required` tag on properties that are mandatory, because `phpswag` will not auto-infer required status from PHP type hints or nullability.
4. **Validation Rules:** Inject validation rules directly into the description strings.
5. **Class-level Grouping:** Move shared metadata (like `@tag`, `@security`) to the Controller/Class level so methods can inherit them.

## Rules

### 1. Manual Declaration (CRITICAL)
- **Attributes are REMOVED:** Do NOT use `#[Get]`, `#[Property]`, etc. Use only `/** @tag */` style PHPDocs.
- **Auto-inference is REMOVED:**
  - You MUST declare `@required` explicitly on any property that is required.
  - You MUST declare `@query` or `@path` explicitly for every route parameter.
  - You MUST declare `@body` explicitly for any request body.
  - You MUST declare the exact Enum cases in the description if needed, or explicitly link to an Enum schema.

### 2. PHPDoc Syntax
- Use standard tags: `@route`, `@query`, `@path`, `@body`, `@response`, `@success`, `@property`, `@var`.
- Example for required property:
  `/** @var string $email User email @required */`

### 3. Inline Validation & Metadata
- Always embed validation rules directly inside the description string.
- Supported constraints: `minimum(n)`, `maximum(n)`, `minLength(n)`, `maxLength(n)`, `pattern(regex)`, `format(type)`, `enum(a,b,c)`, `default(value)`, `example(value)`.
- Example: `@query string $email User email format(email) example(user@example.com)`

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

**After:**
```php
/**
 * @title User Model
 * @description User representation model
 */
class User {
    /**
     * @var int $id Unique identifier minimum(1)
     * @required
     */
    public int $id;

    /**
     * @var string $email User email address format(email)
     * @required
     */
    public string $email;

    /**
     * @var string $bio User biography
     */
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

**After:**
```php
/**
 * @tag Users User management endpoints
 */
class UserController {
    
    /**
     * @route GET /users/{id}
     * @path int $id The user ID
     * @query string $status Filter by status enum(active,inactive) default(active)
     * @success 200 User Success response
     */
    public function show(int $id, string $status = 'active') {
        // ...
    }
}
```
