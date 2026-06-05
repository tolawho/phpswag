# PHP Swagger Generator

A framework-agnostic PHP Swagger/OpenAPI generator that uses static analysis (AST) and PHPDoc.

## Installation

```bash
composer require php-swag/php-swag
```

## Usage

```php
use PhpSwag\Core;

$core = new Core();
$yaml = $core->generate(['./src']);

file_put_contents('swagger.yaml', $yaml);
```

## Support Tags

- `@route [METHOD] [PATH]`
- `@summary [TEXT]`
- `@response [CODE] [CLASS]`
- `@property [TYPE] $[NAME] [DESCRIPTION]`
- `@var [TYPE]`

## Testing

To run the unit tests:
```bash
./vendor/bin/phpunit
```

To run the example generator:
```bash
php examples/generate.php
```
