# Living Handbook

An internal team handbook for WordPress: structured page types, clear ownership, and freshness tracking so docs don't rot.

Most WordPress documentation plugins are customer-facing knowledge bases. Living Handbook is different: it is built for an internal team handbook, and its focus is maintenance, the reason internal docs usually go stale.

## Status

Active development (0.x). The plugin is usable end to end: multiple handbooks, per-handbook access, entry pages with search and filters, single-page layout, and a per-handbook sidebar navigation. It stays in 0.x until it has been validated in real use.

## Features

- A dedicated handbook content type with structured page types (Diataxis plus FAQ).
- Ownership per page: a responsible role mapped to a current person.
- Freshness tracking: per-page review dates and intervals, an overdue dashboard, and escalation for pages whose review is overdue.
- Frontend access per handbook: public, all members, or restricted to specific roles and/or people.
- Several handbooks side by side, each with its own entry page and navigation.
- An entry page per handbook with full-text search, taxonomy filters, area tiles and recently updated pages.
- Per-handbook navigation built from the page hierarchy: a self-contained, collapsible page tree with a Menu or Accordion display, styled by the plugin. No other plugin is required.
- Markdown import: paste, a ZIP of `.md` files (flat or MkDocs-structured), or a GitHub file or folder URL.
- GitHub sync: a page can be sourced from a Markdown URL, pulled on save, on demand and on a schedule, with a locked editor.
- No external WordPress plugin dependency; the import and sync bundle two Composer libraries in `vendor/`.

## Requirements

- WordPress 6.7 or newer, with a block theme
- PHP 8.1 or newer

Living Handbook is built for single-site installations; network activation on multisite is not supported.

## Development

```bash
composer install
bash bin/check-and-build.sh   # lint, static analysis, unit tests, then build the zip
```

Or run the checks individually:

```bash
composer lint      # PHPCS (WordPress standards)
composer analyze   # PHPStan
composer test      # PHPUnit (unit)
```

Continuous integration runs linting, static analysis, and unit tests on every push and pull request. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Documentation

New to the plugin? Start with [getting started](docs/getting-started.md): from install to your first page that visitors can see. Then [maintenance and freshness](docs/maintenance.md), the feature the plugin is built around.

Developer documentation lives in [`docs/`](docs/): a plain-language [code overview](docs/code-overview.md) to start, then [blocks](docs/blocks.md), [templates](docs/templates.md), [customization](docs/customization.md), [architecture](docs/architecture.md), [hooks](docs/hooks.md) and [import and sync](docs/import-and-sync.md).

## License

[GPL-2.0-or-later](LICENSE).
