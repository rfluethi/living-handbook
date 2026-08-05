# Documentation

Two audiences share this folder. The first two pages are for anyone running the
plugin; the rest are for anyone changing it. Read them in this order and each
one assumes only the ones above it.

## Using the plugin

| Page | What it answers |
| --- | --- |
| [getting-started.md](getting-started.md) | From install to a first page a visitor can actually see. Start here. |
| [maintenance.md](maintenance.md) | Review dates, intervals, the overdue dashboard: the feature the plugin exists for. |

## Working on the plugin

| Page | What it answers |
| --- | --- |
| [code-overview.md](code-overview.md) | A plain-language tour of how the plugin is built, assuming no WordPress plugin experience. The place to start. |
| [architecture.md](architecture.md) | The same ground in one dense page, for someone who already knows WordPress. |
| [blocks.md](blocks.md) | Every block: what it renders, where it renders, and its settings. |
| [templates.md](templates.md) | The two block templates that ship, and how to rearrange them. |
| [customization.md](customization.md) | The `--lh-*` custom properties and the stable class names, and which rules must not be removed. |
| [hooks.md](hooks.md) | The filters and actions the plugin exposes. |
| [import-and-sync.md](import-and-sync.md) | Markdown import, bundles, and the GitHub sync. |
| [releasing.md](releasing.md) | How a version gets out: what has to agree, and what happens by itself. |

## Where the other documentation lives

- **The handbook shipped with the plugin** is under [`handbuch/`](../handbuch/),
  in German and English. It is the documentation of the running application, is
  loaded into WordPress from the settings screen, and is written for people using
  a handbook rather than building one.
- **Contributing, the local environment and the conventions** are in
  [`CONTRIBUTING.md`](../CONTRIBUTING.md).
- **The changelog** is in [`readme.txt`](../readme.txt) in short form and in
  [`CHANGELOG.md`](../CHANGELOG.md) in full.

These pages are English. That is deliberate: English is the language of the
repository, so the project stays open to people who do not read German. The
German source they are written from is kept in the project's own workspace and
is not part of this repository.
