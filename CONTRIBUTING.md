# Contributing

This is an internal team project that is open for anyone to read and, if useful, to build on. Contributions are welcome but not actively solicited.

## Development setup

```bash
composer install
```

## Before you open a pull request

Run the checks locally; the same checks run in CI and must pass:

```bash
composer lint      # PHPCS, WordPress coding standards
composer analyze   # PHPStan
composer test      # PHPUnit
```

## Conventions

- Target PHP 8.1 and WordPress 6.7 or newer.
- Follow the WordPress coding standards (enforced by PHPCS).
- All user-facing strings use the `living-handbook` text domain.
- Escape on output, sanitize on input, check capabilities and nonces.
- Keep the changelog in `readme.txt`.
- Branch off `main`, keep pull requests focused, and make sure CI is green.
