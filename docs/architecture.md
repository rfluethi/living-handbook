# Architecture

A concise, developer-facing summary of the shipped design.

## Building blocks

- **Content type `handbook`**: the handbook pages. Hierarchical, so parent and order drive the navigation. Registered non-public (`public => false`, `publicly_queryable => true`) and kept out of the sitemap, feeds and oEmbed, so an internal handbook does not leak to logged-out visitors. Single pages stay reachable and access-guarded at `/handbook/<slug>/`. The post type archive is switched off (`has_archive => false`): the overview is a normal page holding the overview block, so there is no second, competing overview you cannot style.
- **Taxonomies**: page type (Diataxis plus FAQ), area, responsible role, audience. All four are plain vocabularies for filtering and search; they have nothing to do with the navigation.
- **Handbook grouping (`handbook_set`)**: each page belongs to exactly one handbook. A handbook carries a front-end access configuration and gets its own entry page at the term archive, `/handbook-set/<slug>/`.
- **Access**: three levels per handbook: public, all members, or restricted to roles and/or people. Enforced front-end only through a single central check, `AccessController::can_view_post()`, used by every read path (single view, entry page, search, result sets, REST, the facet endpoint, the feedback endpoint, the menu and the overview), and filterable via `living_handbook_can_view_post`. This check is the one mandatory gate: any programmatic or AI reader added later must route through it rather than query the database directly. It is deliberately fail-closed, so a page without a handbook is not readable. Editing in wp-admin is unrestricted and uses the standard WordPress roles.
- **Metadata**: native custom fields for last update, last review, review interval and reviewer, plus an AI-exclusion flag (`living_handbook_ai_exclude`). An aggregated read-only REST field, `living_handbook_status`, exposes the derived freshness (due/overdue), the review data, the permalink and the exclusion flag in one place.
- **Maintenance**: an overdue dashboard with a percentage and escalation, plus a per-page feedback counter. The feedback endpoint requires a logged-in user who is allowed to read that page, and counts one vote per user and page.
- **Navigation**: a core Navigation block per handbook, built from the page hierarchy with a VSN block style. The assembled markup is cached per handbook and rebuilt when a page or handbook term changes. Optionally the accessible handbooks are injected into the theme's own Navigation block via the `has-handbook-menu` class.
- **Import**: an import screen turns Markdown into handbook pages. A Markdown library (league/commonmark) produces HTML, and WordPress's own paste conversion turns that into editable blocks. Sources: a pasted draft, a ZIP of `.md` files (flat, or structured along a `mkdocs.yml` nav), and GitHub file or folder URLs. A ```` ```mermaid ```` fence becomes a dedicated live-rendered block, collapsible sections become details blocks, and images from `assets` are sideloaded into the media library. A shared post-processor applies the transport metadata and resolves parents and internal `.md` links. Re-importing the same source updates the existing pages instead of duplicating them, matched by source path, slug within the handbook, or source URL. See [import-and-sync.md](import-and-sync.md).
- **GitHub sync**: the source of a page is selectable per page (`living_handbook_source`, values `wordpress` or `github`). GitHub pages carry a Markdown source URL (`living_handbook_markdown_source`), are pulled from the repository (on save, via "Sync now", and via WP-Cron on a configurable schedule, in bounded batches), stored as rendered HTML filtered through `wp_kses`, and locked in the editor. The source URL is restricted to an allowlist of hosts to prevent server-side request forgery. A block marks the public page, a column in the list table shows the source, and failed syncs are flagged and surfaced as an admin notice. Live sync covers public repositories; private repositories are imported from a ZIP instead.

## Front-end surfaces

| Surface | Route | Built by |
| --- | --- | --- |
| Overview | a page you create | the `living-handbook/overview` block |
| Entry page of one handbook | `/handbook-set/<slug>/` | the "Handbook entry" template |
| Single page | `/handbook/<slug>/` | the "Handbook page" template |

## Principles

- No external WordPress plugin dependencies. A block theme is expected; VSN is needed for the sidebar navigation styling.
- Composer runtime dependencies only as a deliberate exception for the import and sync module: league/commonmark (Markdown, BSD-3-Clause) and symfony/yaml (reading `mkdocs.yml`, MIT), both open source and free. The build ships them in `vendor/`. mermaid.js is bundled in `assets/js/` for the diagram block (MIT).
- Standard WordPress interfaces (REST, hooks) behind the access check.
- Configuration follows "decisions, not options": visual choices belong in the Site Editor, behaviour options are few and justified, everything else is a hook.
- English is the language of the repository. The German translation lives in `languages/`, the German documentation in the team's vault.

_Updated in the same pull request as the code it describes._
