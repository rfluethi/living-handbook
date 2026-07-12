# Architecture

A concise, developer-facing summary of the shipped design. The detailed design rationale is maintained internally by the team.

## Building blocks

- **Content type** `handbook`: the handbook pages. Hierarchical, so parent and order drive the navigation. Registered non-public (`public => false`, `publicly_queryable => true`) and kept out of the sitemap and feeds, so an internal handbook does not leak to logged-out visitors; single pages and the archive stay reachable and access-guarded.
- **Taxonomies**: page type (Diataxis plus FAQ), topic, responsible role, audience.
- **Handbook grouping**: each page belongs to exactly one handbook. A handbook carries a frontend access configuration.
- **Access**: three levels per handbook, public, all members, or restricted to roles and/or people. Enforced frontend-only through a single central check, `AccessController::can_view_post`, used by every read path (single view, archive, search, REST, menu, overview), and filterable via `living_handbook_can_view_post`. This check is the one mandatory gate: any programmatic or AI reader added later must route through it rather than query the database directly. Editing in wp-admin is unrestricted.
- **Metadata**: native custom fields for last update, last review, review interval, and reviewer, plus an AI-exclusion flag (`living_handbook_ai_exclude`). An aggregated read-only REST field, `living_handbook_status`, exposes the derived freshness (due/overdue), the review data, the permalink and the exclusion flag in one place.
- **Maintenance**: an overdue dashboard with a percentage and escalation, plus a per-page feedback counter. The feedback endpoint requires a logged-in user and counts one vote per user and page.
- **Navigation**: a core Navigation block per handbook, built from the page hierarchy with a VSN block style. The assembled markup is cached per handbook and invalidated when a page or handbook term changes.
- **Import**: an import screen turns Markdown into handbook pages. A Markdown library (league/commonmark) produces HTML, and WordPress's own paste conversion turns that into editable blocks. Sources: a pasted draft, a ZIP of `.md` files (flat, or structured along a `mkdocs.yml` nav), and GitHub file or folder URLs. `mermaid` code fences become a dedicated live-rendered block, collapsible sections become details blocks, and images from `assets` are sideloaded into the media library. A shared post-processor applies the transport metadata and resolves parents and internal `.md` links. See [import-and-sync.md](import-and-sync.md).
- **GitHub sync**: the source of a page is selectable per page (`hb_quelle`). GitHub pages carry a Markdown source (`hb_markdown_source`), are pulled from the repository (on save, via "Sync now", and via WP-Cron on a configurable schedule), stored as rendered HTML (filtered through `wp_kses`), and locked in the editor. A block marks the public page, and a column in the list table shows the source. Live sync covers public repositories; private repositories are imported from a ZIP instead.

## Principles

- No external WordPress plugin dependencies.
- Composer runtime dependencies only as a deliberate exception for the import and sync module: league/commonmark (Markdown) and symfony/yaml (reading `mkdocs.yml`), both open source and free. The build ships them in `vendor/`.
- Standard WordPress interfaces (REST, hooks) behind the access check.
- Configuration follows "decisions, not options".

_This document is generated from the internal German design notes and updated in the same pull request as the code it describes._
