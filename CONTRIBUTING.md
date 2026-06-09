# Contributing to PHP Swagger Generator

First off, thank you for considering contributing to `phpswag`! It's people like you that make the open-source community such a great place to learn, inspire, and create.

## Where do I go from here?

If you've noticed a bug or have a feature request, make one! It's generally best if you get confirmation of your bug or approval for your feature request this way before starting to code.

## Fork & create a branch

If this is something you think you can fix, then fork `phpswag` and create a branch with a descriptive name.

A good branch name would be (where issue #325 is the ticket you're working on):

```sh
git checkout -b 325-add-new-validation-tag
```

## Local Development Setup

1. Clone your fork:
   ```sh
   git clone https://github.com/your-username/phpswag.git
   cd phpswag
   ```
2. Install dependencies:
   ```sh
   composer install
   ```

## Coding Standards

This project follows PSR-12 coding standards.
Please ensure your code conforms to our coding standards by running:

```sh
# PHP CodeSniffer
./vendor/bin/phpcs

# PHPStan for static analysis (Level 7)
./vendor/bin/phpstan analyse
```

## Testing

Please write tests for any new features or bug fixes. To run the test suite:

```sh
./vendor/bin/phpunit
```

## Pull Requests

- Fill out the pull request template completely.
- Ensure all tests pass.
- Ensure your code passes static analysis and coding standard checks.
- Document any new features in the `README.md`.
