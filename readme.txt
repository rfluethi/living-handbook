=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.49.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An internal team handbook for WordPress: structured page types, clear ownership, and freshness tracking so docs don't rot.

== Description ==

Living Handbook turns WordPress into an internal team handbook that is built to stay current. Unlike customer-facing knowledge base plugins, it focuses on the thing that makes internal documentation fail over time: maintenance.

Core features:

* A dedicated handbook content type with structured page types (Diataxis plus FAQ).
* Ownership per page: a responsible role mapped to a current person.
* Freshness tracking: per-page review dates and intervals, an overdue dashboard, and escalation for pages whose review is overdue.
* Frontend access per handbook: public, all members, or restricted to specific roles and/or people.
* Several handbooks side by side, each with its own access group, its own entry page, and its own navigation.
* An entry page per handbook with full-text search, taxonomy filters that apply without a reload, area tiles and recently updated pages.
* A single-page layout with per-handbook navigation, badges, an on-this-page table of contents, feedback and a metadata footer, all as blocks.
* Per-handbook navigation built from the page hierarchy: a self-contained, collapsible page tree with a Menu or Accordion display, styled by the plugin. No other plugin is required.
* A handbook menu block that lists the handbooks a visitor may read; it can also be injected into the theme's own navigation.
* Markdown import: paste a document, upload a ZIP, or point at a GitHub file or folder; a folder is read with its subfolders and the folder structure becomes the page hierarchy. A MkDocs project (mkdocs.yml) keeps its page structure, titles and order. Transport metadata and README are applied, internal .md links and their titles are resolved, and Mermaid and collapsible details are converted to blocks. Re-importing the same source refreshes the pages instead of duplicating them.
* GitHub sync: a page can be sourced from a Markdown URL. It is pulled on save, on demand and on a configurable schedule; its editor is locked, the page overview shows the source, and a block marks the public page.
* The plugin brings a handbook of its own: the documentation of the app, written as a Living Handbook and kept on GitHub. One click on the import screen pulls it into the site, in English or German.
* Fully translatable (English source), with a German translation included.
* No external WordPress plugin is required; a block theme is. The import and sync use three bundled Composer libraries (league/commonmark, symfony/yaml, enshrined/svg-sanitize), shipped in vendor/. Mermaid diagrams are rendered by mermaid.js, bundled in assets/js/ (see the FAQ for the third-party disclosure).

== Installation ==

1. Upload the plugin to `wp-content/plugins/living-handbook`.
2. Activate it through the Plugins screen in WordPress.
3. Activation creates a page called "Handbook" holding the overview block, and a notice points you to the next step. Deactivate and reactivate once, or visit Settings then Permalinks, if the handbook URLs do not work yet.
4. Create a handbook under Handbook, Handbooks, set who may read it, and assign your pages to it. A page without a handbook stays invisible on the front end.
5. Living Handbook is built for single-site installations. On a multisite network, activate and uninstall it per site; network-wide activation is not supported.

== Frequently Asked Questions ==

= Do I need another plugin? =

No. The plugin works on its own; it only expects a block theme.

= Does it work on multisite? =

The plugin is built for single-site installations. On a multisite network, activate and uninstall it on each site individually; network-wide activation is not supported, because the one-time setup (the vocabulary, the overview page, the rewrite rules) and the uninstall cleanup run on the current site only.

= Why are my handbook pages not visible? =

Almost always one of two reasons. Either the page is not assigned to a handbook: access is granted per handbook, so a page that belongs to none belongs to nobody, and the page list warns you about it. Or the handbook itself is not visible to you: a new handbook defaults to "All members (logged in)", so logged out you see nothing.

= Does the plugin connect to any external services? =

Only when you use the GitHub features, and only to addresses you enter yourself. The Markdown import and the GitHub sync read files from GitHub (github.com, raw.githubusercontent.com and the GitHub contents API at api.github.com). The plugin only reads the public files you point it at; it does not send any of your data anywhere, and it contacts nothing when you do not use those features.

= What third-party libraries does the plugin bundle? =

The diagram feature bundles mermaid.js version 11.16.0 (assets/js/mermaid.min.js), an open-source diagramming library by the Mermaid project, released under the MIT license. Homepage: https://mermaid.js.org. Source: https://github.com/mermaid-js/mermaid. It runs in the browser to draw Mermaid diagrams and makes no network calls. The import and sync also bundle three PHP libraries in vendor/: league/commonmark (BSD-3-Clause license), symfony/yaml (MIT license), and enshrined/svg-sanitize (GPL-2.0-or-later license), which cleans imported SVG images before they are stored. All bundled libraries use GPL-compatible licenses.

= Who can see a handbook? =

Access is set per handbook: public (no login), all logged-in members, or restricted to specific roles and/or people. It is enforced on the entry page and on single pages.

= What happens when I uninstall the plugin? =

By default your content is kept and only the plugin's own settings and caches are removed. On the settings page you can opt in to remove everything the plugin created, including any templates you edited in the Site Editor.

= What does the plugin store about visitors? =

Very little, and nothing is sent anywhere. The "Was this helpful?" feedback records which logged-in users voted on a page (their user IDs) so it can count one vote per user; it accepts no vote from logged-out visitors. The counts and the voter list are stored as post meta in your own database and are removed with the content option when you delete the plugin.

== Screenshots ==

1. The handbook overview: a card for each handbook a visitor may read.
2. A handbook entry page with search, facet filters, area tiles and recently updated pages.
3. A single handbook page: navigation, on-this-page, badges and the metadata footer.
4. The maintenance dashboard listing pages whose review is overdue.
5. The Markdown and GitHub import screen.

== Changelog ==

= 0.49.0 =
* Public feedback: a new setting lets logged-out visitors vote "Was this helpful?" on public pages. To stay privacy-friendly it stores nothing personal, no cookie, no IP, no identifier, so the same visitor can vote again after reloading; the trade-off is no per-person limit. Off by default. On internal pages, logged-in users still vote once each regardless of this setting.
* Feedback can be reset per page: a "Reset feedback" action in the handbook list clears a page's counters, for a page reworked after weak feedback.
* The URL bases of a handbook page and a handbook grouping (/handbook/, /handbook-set/) can now be changed with two filters, living_handbook_post_type_slug and living_handbook_taxonomy_slug, for a site that needs a localized base. Changing them on a live site rewrites URLs and needs the permalinks flushed.
* Documented how to detach a GitHub-synced page and keep it in WordPress: switch its Source to "Maintained in WordPress" and the content stays, the sync stops and the editor unlocks.

= 0.48.0 =
* Import: a "## Transport-Metadaten" heading inside a fenced code block is no longer mistaken for the page's own transport block, so a page documenting the metadata format keeps its code block instead of being cut off there.
* Import: MkDocs admonitions ("!!! note", "??? tip") now become a blockquote led by the title, instead of collapsing into stray text with lost indentation.
* The GitHub source note now links to the source file on GitHub, next to the "maintained on GitHub" line.
* Handbook tables render with visible lines between rows and columns, a wider first column and top-aligned cells, so an imported Markdown table reads as a table; a wide code block scrolls instead of stretching the page.
* The handbook navigation takes the surface background, matching the cards and the on-this-page box, and a separator line sets off the "Was this helpful?" block.

= 0.47.0 =
* The import screen now shows its notes (links that resolved to no page, or an import cut short by a limit) in a highlighted block above the page list, instead of a plain line at the end where a large import buried them.

= 0.46.0 =
* Internal links can no longer become 404s, by two mechanisms. First, a folder import now records each page's repository path, so an internal .md link resolves to its page exactly by path, regardless of slug; this fixes links to a folder's README, whose page takes the folder name as its slug, not "readme". Second, a link that still resolves to no page is turned into plain text instead of a raw .md link: the text stays, the dead link cannot reach the browser, and the link comes back on its own once the target page exists. The import still lists every such link so a typo or missing page stays visible.

= 0.45.0 =
* Fixed internal links turning into 404s on GitHub-sourced pages. The import resolved a page's internal .md links to real pages, but every later sync re-rendered the Markdown and left the links raw again, so the first scheduled sync after an import broke every cross-link. The sync now resolves the links too, the same way the import does.

= 0.44.0 =
* The GitHub folder import now reports internal .md links that point at no page. After it wires up every link whose target exists, anything left pointing at a .md file is a dead link, a typo or a page not yet written, and the result list names the page it is on and the file it points at. This makes a large handbook with many cross-references far easier to keep whole.

= 0.43.0 =
* The GitHub source note ("This page is maintained on GitHub…") now reads as a note rather than body text: smaller, muted, with a subtle accent bar.
* Fixed a self-contradiction in the developer docs, where the limits section still said the folder import does not descend into subfolders, which it has since 0.40.0.

= 0.42.0 =
* The GitHub folder import now respects the transport "Reihenfolge" of a page for its order, instead of overwriting it with an automatic value. Number only the pages whose order matters, keep the numbers small; everything else falls back to its import position and sorts after them. An area's order lives in its README.
* An index or README file that stands for a folder now takes its slug from the folder name, so an area page gets a clean URL instead of "readme".
* The app handbook is published on load rather than left as drafts. It is curated, editor-locked content, and its visibility is governed by the handbook it lands in, so a "members" handbook keeps it behind the login. Any other GitHub import still stays a draft for review.

Older versions are listed in [CHANGELOG.md](https://github.com/rfluethi/living-handbook/blob/main/CHANGELOG.md) in the repository.

== Upgrade Notice ==

= 0.49.0 =
Optional public "Was this helpful?" voting (privacy-friendly, no cookie or IP, off by default), a per-page feedback reset, and filters to change the handbook URL bases. Pre-release: best on a fresh database.

= 0.48.0 =
Import fixes (transport block inside code fences, MkDocs admonitions), a GitHub link on the source note, and table, navigation and code-block styling. Reload the app handbook after updating. Pre-release: best on a fresh database.

= 0.47.0 =
Import notes (unresolved links, limits) now stand out in their own block. Pre-release: best on a fresh database.

= 0.46.0 =
Internal links can no longer turn into 404s: they resolve by repository path, and any that resolve to nothing become plain text. Reload the app handbook after updating. Pre-release: best on a fresh database.

= 0.45.0 =
Fixes internal links on GitHub-synced pages breaking into 404s after the first sync. Reload the app handbook after updating. Pre-release: best on a fresh database.

= 0.44.0 =
The GitHub folder import now lists internal links that resolve to no page. Pre-release: best on a fresh database.

= 0.43.0 =
Cosmetic: the GitHub source note is now styled as a hint. Plus a docs fix. Pre-release: best on a fresh database.

= 0.42.0 =
The GitHub folder import now orders pages by their transport "Reihenfolge", and the app handbook is published on load. Pre-release: best on a fresh database.

= 0.41.0 =
The app handbook now loads from GitHub rather than shipping in the plugin, one click on the import screen. Pre-release: best on a fresh database.

= 0.40.0 =
The GitHub folder import now includes subfolders and turns the folder structure into the page hierarchy. Pre-release: best on a fresh database.

= 0.39.0 =
Adds the app's own handbook, loadable from the import screen in English or German. Nothing is created automatically. Pre-release: best on a fresh database.

= 0.38.1 =
Adds a counter-check to the new REST access tests, so they cannot pass for the wrong reason. No functional change. Pre-release: best on a fresh database.

= 0.38.0 =
An export no longer carries the e-mail addresses of individually allowed people, and the REST access separation is now covered by tests. Pre-release: best on a fresh database.

= 0.37.0 =
Security fix: imported block content is now cleaned before it is stored, closing a hole where a prepared bundle could carry active markup into the handbook. Recommended for anyone using the bundle import. Pre-release: best on a fresh database.

= 0.36.0 =
Export gets its own screen, each import source now carries its own options and button inside its tab, and the German translation of both screens is complete. Pre-release: best on a fresh database.

= 0.35.0 =
Adds tests for the bundle export and import, and fixes a matching bug they found: an import could match a page of the same slug in a different handbook. Pre-release: best on a fresh database.

= 0.34.0 =
The bundle import can now be pointed at an existing handbook instead of the one named in the bundle. Pre-release: best on a fresh database.

= 0.33.0 =
Adds bundle import: upload a bundle from another site and choose whether existing pages are skipped, updated, or duplicated. Nothing is ever deleted. Pre-release: best on a fresh database.

= 0.32.0 =
The export picker now has two dependent fields: pick the handbook, and the second field lists only that handbook's areas. Pre-release: best on a fresh database.

= 0.31.0 =
Export now also handles a single area (a top-level page and its subpages), not just a whole handbook. Pre-release: best on a fresh database.

= 0.30.0 =
Housekeeping only: removes a suppress_filters flag the Plugin Check flags. No functional change. Pre-release: best on a fresh database.

= 0.29.0 =
Adds handbook export: download a handbook as a self-contained bundle (ZIP) under Handbook, Import. The matching import follows in a later release. Pre-release: best on a fresh database.

= 0.28.0 =
The block editor loads the handbook stylesheet only on handbook pages now, not in every editor. No visible change. Pre-release: best on a fresh database.

= 0.27.0 =
The handbook list gains a filter dropdown for each vocabulary too (page type, topic, responsibility, audience), alongside the handbook and source filters. Pre-release: best on a fresh database.

= 0.26.0 =
The handbook list gains a sortable Feedback column (net votes), filter dropdowns for handbook and source, and direct links from the two list warnings to the affected pages. Pre-release: best on a fresh database.

= 0.25.0 =
Fixes the untranslated import progress messages on non-English sites, and lets editing users find handbook pages again in the classic editor's link search. Frontend visibility is unchanged. Pre-release: best on a fresh database.

= 0.24.0 =
Handbook blocks now offer an HTML anchor and an additional CSS class under Advanced, so you can link to a block or target one instance from Custom CSS. Pre-release: best on a fresh database.

= 0.23.1 =
Packaging fix: the release ZIP no longer includes stray hidden files that the plugin repository check rejected. No functional change. Pre-release: best on a fresh database.

= 0.23.0 =
F7 completed: the Mermaid and GitHub source-note blocks now also use block.json, so every handbook block shares the single-source registration. No change on the page. Pre-release: best on a fresh database.

= 0.22.0 =
The handbook blocks now use a block.json file each as the single source of their metadata, removing duplicated definitions, and gain an inserter preview. No change on the page. Pre-release: best on a fresh database.

= 0.21.0 =
The ZIP import limit is now adjustable through the living_handbook_zip_max_bytes filter, and the navigation injection uses the HTML API instead of regex for robustness. No visible change. Pre-release: best on a fresh database.

= 0.20.0 =
Code review round 3: import failures return proper HTTP errors, imported images are reused only when unchanged (matched by a content hash), and the result count matches the cards shown. Pre-release: best on a fresh database.

= 0.19.0 =
Access-control hardening: a pre-query layer restricts handbook queries to the handbooks you may view, closing side-channels through suppress_filters and admin-ajax. No visible change. Pre-release: best on a fresh database.

= 0.18.0 =
Security hardening from a code review: GitHub fetches without redirects, sanitised SVG imports, no raw input elements in imported HTML, and a fixed background-sync follow-up. Pre-release: best on a fresh database.

= 0.17.0 =
JavaScript internationalisation moves to the WordPress standard (wp_set_script_translations plus JSON language files), and the importer counts use proper plural forms. No change on an English site. Pre-release: best on a fresh database.

= 0.16.0 =
The handbook now follows your theme's colours, including dark mode. Search fields match the navigation, and you can set the review date, reviewer and interval from the page list via Quick Edit. Pre-release: best on a fresh database.

= 0.15.0 =
A clearer two-step import screen with a tabbed source switcher, an on-page Handbook search block, a Custom CSS field in the settings, a weekly default sync frequency for new installs, and a divider in the admin menu. Pre-release: best on a fresh database.

= 0.14.0 =
Hardens the access side-channels (REST, comments, multi-handbook fail-closed), moves settings to the Settings API, makes navigation self-contained, and updates terminology. New getting-started and maintenance docs. Pre-release: best on a fresh database.

= 0.13.0 =
Activation now creates the handbook overview page and guides you through the first steps. The uninstall cleanup finally ships. Facet filters show sub-areas under their parent, re-imports no longer duplicate pages, and accessibility was improved. Still pre-release: best installed on a fresh database.

= 0.12.0 =
Interface polish, AJAX facet filtering, a handbook menu block, an uninstall data option, full translatability, and renamed source meta keys. Best installed on a fresh database while pre-release.
