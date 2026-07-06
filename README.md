# Living Handbook

An internal team handbook for WordPress: structured page types, clear ownership, and freshness tracking so docs don't rot.

Most WordPress documentation plugins are customer-facing knowledge bases. Living Handbook is different: it is built for an internal team handbook, and its focus is maintenance, the reason internal docs usually go stale.

## Status

Early development. This repository currently contains the plugin scaffold and developer tooling. Features are built on top of it.

## Planned features

- A dedicated handbook content type with structured page types (Diataxis plus FAQ).
- Ownership per page: a responsible role mapped to a current person.
- Freshness tracking: per-page review dates and intervals, an overdue dashboard, and escalation for unchecked pages.
- Frontend access per handbook: public, all members, or restricted to specific roles and/or people.
- Automatic navigation menus generated from the page hierarchy.
- Markdown and ZIP import.
- No external plugin dependencies, no cost.

## Requirements

- WordPress 6.7 or newer
- PHP 8.1 or newer

## Development

```bash
composer install
composer lint      # PHPCS (WordPress standards)
composer analyze   # PHPStan
composer test      # PHPUnit
```

Continuous integration runs linting, static analysis, and unit tests on every push and pull request. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Documentation

Developer documentation lives in [`docs/`](docs/).

## License

[GPL-2.0-or-later](LICENSE).
