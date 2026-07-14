=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.11.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An internal team handbook for WordPress: structured page types, clear ownership, and freshness tracking so docs don't rot.

== Description ==

Living Handbook turns WordPress into an internal team handbook that is built to stay current. Unlike customer-facing knowledge base plugins, it focuses on the thing that makes internal documentation fail over time: maintenance.

Core features:

* A dedicated handbook content type with structured page types (Diataxis plus FAQ).
* Ownership per page: a responsible role mapped to a current person.
* Freshness tracking: per-page review dates and intervals, an overdue dashboard, and escalation for pages that go unchecked.
* Frontend access per handbook: public, all members, or restricted to specific roles and/or people.
* Several handbooks side by side, each with its own access group, its own entry page, and its own navigation.
* An entry page per handbook with full-text search, taxonomy filters, area tiles and recently updated pages.
* A single-page layout with per-handbook navigation, badges, an on-this-page table of contents, feedback and a metadata footer, all as blocks.
* Per-handbook navigation built from the page hierarchy and rendered as a core Navigation block, styled by the VSN plugin (Vertical Sidebar Navigation).
* Markdown import: paste a document, upload a ZIP, or point at a GitHub file or folder. A MkDocs project (mkdocs.yml) keeps its page structure, titles and order. Transport metadata and README are applied, internal .md links and their titles are resolved, and Mermaid and collapsible details are converted to blocks.
* GitHub sync: a page can be sourced from a Markdown URL. It is pulled on save, on demand and on a configurable schedule; its editor is locked, the page overview shows the source, and a block marks the public page.
* No external WordPress plugin is required for the core (a block theme is, and VSN for the sidebar). The import and sync use two bundled Composer libraries (league/commonmark, symfony/yaml), shipped in vendor/.

== Installation ==

1. Upload the plugin to `wp-content/plugins/living-handbook`.
2. Activate it through the Plugins screen in WordPress.
3. Deactivate and reactivate once, or visit Settings then Permalinks, so the handbook URLs work.
4. For the sidebar navigation, install and activate the Vertical Sidebar Navigation (VSN) plugin.

== Changelog ==

= 0.11.0 =
* GitHub sync (concept 06, way 1): a page can carry a Markdown source URL and is pulled on save, via Sync now, and on a schedule set on a new settings page (off, hourly, twice daily, daily, weekly; default daily). Its content editor is locked, the page overview shows the source, and a placeable block marks the public page. A whole GitHub folder can be imported from a tree URL via the contents API.
* Security: the handbook content type is now registered non-public (kept out of the XML sitemap, feeds and oEmbed) while single pages and the archive stay reachable and access-guarded. The feedback endpoint now requires a logged-in user and counts one vote per user and page. Synced GitHub HTML is filtered through wp_kses before it is stored.
* Robustness: the Markdown source is limited to an allowlist of hosts (raw.githubusercontent.com, filterable) to prevent server-side request forgery; the scheduled sync runs in bounded batches instead of pulling every page in one run; the folder import reports a GitHub API rate limit clearly; a versioned upgrade routine runs migrations after an update; uninstall.php clears the plugin's options and caches (content removal is opt-in via a filter). Added an "Area overview" page type for area start pages.
* The build now ships the production Composer dependencies (vendor/) so import and sync work in an installed copy.

= 0.10.0 =
* Markdown import overhaul: one import UI (paste, ZIP, or a GitHub file/folder URL), support for github.com blob URLs and raw URLs, whole-folder import, and MkDocs nav-driven structure from mkdocs.yml (titles, order, parents). Transport metadata and README are applied on import and sync; internal .md links and their visible titles are resolved across a directory import; Mermaid diagrams and collapsible details render correctly. New blocks appear under a "Living Handbook" category.

= 0.9.0 =
* Navigation is now available on handbook entry pages as well as single pages, and offers a Menu or Accordion display.
* Feedback and metadata are now two separate blocks.
* On-this-page is a collapsible box covering H1 to H6, with a depth setting (overridable per page), a mobile placement above the content, and smooth scrolling.
* The metadata block can show or hide the people (avatar and name); the top navigation item is bold and adjustable via CSS.
* New documentation for the blocks and templates, with CSS customization examples.

= 0.8.0 =
* The navigation is rendered as a core Navigation block with a VSN block style, built fresh per handbook from the page hierarchy. Removed the single global generated menu.

= 0.7.0 =
* Multi-handbook structure: the handbook archive is a chooser of readable handbooks; each handbook has its own entry page (term archive) with search, taxonomy filters, area tiles and recently updated cards.
* Single-page template with navigation, badges, an on-this-page column, feedback and a metadata footer, provided as block templates.
* Access is enforced on the handbook entry pages as well as single pages.

= 0.6.0 =
* Fixed: the frontend stylesheet now loads on the overview page, so the cards are styled.
* Navigation menu generated from the page hierarchy, ready to be styled by the VSN plugin.

= 0.5.0 =
* Frontend design following the prototype: overview cards with a freshness dot, a bordered navigation tree, and styled metadata footer, badges and feedback.
* Colours exposed as CSS custom properties for theme adaptation; see docs/customization.md.

= 0.4.0 =
* Overview and navigation blocks, and a "Living Handbook" block category.

= 0.3.0 =
* Maintenance dashboard widget and handbook list columns.

= 0.2.0 =
* Access configuration UI, maintenance metadata, freshness status, feedback counter, default frontend rendering, and a German translation.

= 0.1.0 =
* Initial scaffold, data model, frontend access control, and internationalisation.
