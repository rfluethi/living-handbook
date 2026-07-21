=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.21.0
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
* Markdown import: paste a document, upload a ZIP, or point at a GitHub file or folder. A MkDocs project (mkdocs.yml) keeps its page structure, titles and order. Transport metadata and README are applied, internal .md links and their titles are resolved, and Mermaid and collapsible details are converted to blocks. Re-importing the same source refreshes the pages instead of duplicating them.
* GitHub sync: a page can be sourced from a Markdown URL. It is pulled on save, on demand and on a configurable schedule; its editor is locked, the page overview shows the source, and a block marks the public page.
* Fully translatable (English source), with a German translation included.
* No external WordPress plugin is required; a block theme is. The import and sync use two bundled Composer libraries (league/commonmark, symfony/yaml), shipped in vendor/. Mermaid diagrams are rendered by mermaid.js, bundled in assets/js/ (see the FAQ for the third-party disclosure).

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

The diagram feature bundles mermaid.js version 11.16.0 (assets/js/mermaid.min.js), an open-source diagramming library by the Mermaid project, released under the MIT license. Homepage: https://mermaid.js.org. Source: https://github.com/mermaid-js/mermaid. It runs in the browser to draw Mermaid diagrams and makes no network calls. The import and sync also bundle two PHP libraries in vendor/: league/commonmark (BSD-3-Clause license) and symfony/yaml (MIT license). All bundled libraries use GPL-compatible licenses.

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

= 0.21.0 =
* The ZIP import's uncompressed size limit is now adjustable in code through the `living_handbook_zip_max_bytes` filter (default 100 MB), and the "too large" message reflects the active limit. It stays a safety limit; the real ceiling is the server's PHP upload and memory configuration.
* Code review F9: the navigation-injection helpers use the HTML API (WP_HTML_Tag_Processor) to add classes instead of regular expressions on core markup, so a change to the core navigation block's attribute order no longer silently breaks the handbook submenu. Inserting the submenu container stays a string operation, which the HTML API does not cover.

= 0.20.0 =
* Code review round 3. Import errors that abort a whole operation (CommonMark missing, an unreadable or oversized ZIP, a GitHub API failure) are now returned as WP_Error with an HTTP status, so a failure shows up as a failed request in logs and dev tools instead of a 200 with an error field. Per-page failures within a batch still list the page and let the rest continue.
* Imported images are reused only when the plugin imported them before and their content is unchanged (matched by an import marker and a content hash), so a foreign upload that happens to share a file name is never picked up, and an updated source image is re-imported.
* The result count and pagination on a handbook now reflect the pages actually shown after the access filter, so the number matches the cards on a single page.
* The ZIP import limit is raised to 100 MB uncompressed. A GitHub file URL that cannot be fetched now reports the error on the import screen and creates no page, instead of leaving an empty draft that only reveals the sync error once opened.

= 0.19.0 =
* Access-control hardening (code review F1): a coarse pre_get_posts layer now restricts handbook queries to the handbooks the current user may view. A third-party query that sets suppress_filters (the get_posts default), or a front-end read over admin-ajax, can no longer list the titles or excerpts of handbooks the user may not read. The precise, fail-closed per-page check on the display path is unchanged; this closes the two channels that bypassed it.

= 0.18.0 =
* Security hardening from the 0.16.0 code review. GitHub fetches now use wp_safe_remote_get with redirects disabled, so a redirect cannot lead the sync to an unchecked host. Imported SVG images are sanitised (enshrined/svg-sanitize) before they reach the media library. The HTML sanitiser no longer allows raw input elements from imported Markdown.
* Fixed the background sync follow-up: a large sync continues on its own one-off event instead of a guard that never matched, so a full pass no longer stalls until the next scheduled run.
* Uninstall uses the plugin's own option-name constants and flushes the object cache, so versioned caches are also cleared on sites with Redis or Memcached.
* Housekeeping: the build verifies that the version matches in the header, the constant and the readme, and a redundant weekly-schedule shim was removed (WordPress ships it since 5.4).

= 0.17.0 =
* Internationalisation of the JavaScript now follows the WordPress standard: translations load through wp_set_script_translations() and per-script JSON files generated from the .po at build time, replacing the previous hand-maintained bridge and its two string lists.
* The importer's counts use plural forms (_n): pages, images, drafts and converted links each read correctly in the singular or plural, in German and any other language.
* No visible change on an English site. On a translated site the block editor labels, the import screen and the progress messages read correctly.

= 0.16.0 =
* Appearance: the handbook now follows your theme's colours. Surfaces, text and accent default to the theme's colour presets, and borders and secondary text are derived from them, so a dark theme, or a dark style variation a visitor selects, turns the cards, navigation and table of contents dark on their own. The stylesheet is consolidated and its breakpoints are documented.
* The entry search and the on-page search field now take the surface colour with a thin border, matching the navigation and the table of contents, and stay legible on a dark theme.
* Quick Edit: the last-reviewed date, the reviewer and the review interval can be set straight from the handbook page list, without opening each page.
* The metadata footer places each person below its date instead of beside it.
* Performance: the navigation and the area tiles load a whole handbook in a single query instead of one query per branch, and the shared list of readable handbooks is built in one place; the navigation cache is refreshed only for handbook changes.
* Continuous integration runs the integration test suite automatically on push and pull request; the database service, WordPress core and the test config are set up in the workflow.
* Privacy and housekeeping: the feedback counters use hidden meta keys (migrated automatically), the readme notes that a logged-in voter's user ID is stored, and the GitHub sync clears all of its scheduled events on deactivation.
* Inserter and internationalisation: the handbook blocks carry a description and search keywords, and small fixes ("OK" now shows the sync date, the sync-failed marker is translatable).

= 0.15.0 =
* The import screen is reorganised into two steps: choose the target handbook, then pick one source in a tabbed switcher (paste, ZIP, or GitHub). Only the chosen source is shown, with a single import button, and the explanation moved into a collapsible help section.
* New "Handbook search" block: a search-as-you-type box for a single page. It shows matching pages as links, so the visitor jumps straight there without leaving the page.
* New "Custom CSS" field on the settings page: style the handbook from the plugin instead of the theme, so the styling is removed when the plugin is deleted.
* The default background sync frequency for a new install is now weekly (was daily). Existing sites keep their configured setting.
* The handbook admin menu shows a divider between the three usage pages and the six configuration pages.
* Polish: the import explanation is now a screen Help tab; live search on the entry page is debounced and shows real results (title and body matches); importing without a target handbook asks first; the review column is sortable and shown in the site date format; the overdue dashboard caps its list with a link to all pages; and small controls meet the minimum touch-target size.

= 0.14.0 =
* Security: closed several access side-channels so per-handbook visibility holds beyond the single page. Reading the handbook list over REST is limited to editors; comments on a handbook you may not read are hidden from comment queries, feeds and single REST reads; and a page's visibility is combined fail-closed across every handbook it belongs to. The Markdown sync source is restricted to https hosts.
* The settings page now uses the WordPress Settings API: it posts to options.php (no resubmit on reload), validates through a sanitize callback, and shows the standard settings notice.
* Navigation is now self-contained and needs no other plugin: a collapsible native page tree with a Menu or Accordion display, a title that toggles the whole tree, and a link to the handbook start page.
* Accessibility: Mermaid loads only when a diagram is present, and on demand in the editor; each diagram carries a title and a text alternative for screen readers.
* Import: each source (paste, ZIP, GitHub) has its own button, per-page failures are listed with their reason, and a ZIP is read within bounded limits (2000 entries, 5 MB per file, 50 MB total).
* Terminology: the grouping is shown as "Handbooks", the cross-cutting filter taxonomy as "Topics", and the escalated freshness state as "Review overdue". The transport block now prefers "Thema" for the topic field; "Bereich" and "Themengebiet" keep working.
* Uninstall also removes the seeded vocabulary terms when you opt in to full content removal.
* Performance: fixed an N+1 query in the maintenance dashboard widget.
* Documentation: new getting-started and maintenance guides (English and German), plus corrections to the blocks, hooks and contributing docs.
* Tests: added REST permission and access-chain integration tests, and tests for the GitHub source allowlist and the HTML sanitizer.

= 0.13.0 =
* First run: activating the plugin now creates the handbook overview as a normal page, so a fresh install shows something instead of nothing. A one-time notice points to it and explains the next step, and the page list warns about pages that stay invisible because they have no handbook. Nothing is created twice, and a page you delete does not come back.
* Fixed: the release build never shipped uninstall.php, so the uninstall cleanup could not run in an installed copy. It ships now.
* The handbook overview block now defaults to a list, which reads better for the handful of handbooks most sites have. The card/list switch is unchanged.
* Facet filters for hierarchical taxonomies (areas, page types, audiences) are shown as an outline: children are indented under their parent instead of being listed flat and alphabetically, which used to put a sub-area above its own parent.
* Re-importing the same source now refreshes the matching pages instead of creating duplicates, matched by source path, by slug within the chosen handbook, or by GitHub source URL. Slug and publication status are kept. A pasted draft still always creates a new page.
* Accessibility: visible focus outlines on all interactive elements, the table of contents moves keyboard focus to the target heading, reduced-motion preferences are respected, the feedback confirmation is announced, and the muted text colour now meets WCAG AA.
* Security: the feedback endpoint now only accepts votes from users who are allowed to read that page, not from any logged-in user.
* The transport block accepts "Bereich" next to the older "Themengebiet" for the area taxonomy; both are read, existing drafts keep working.
* Removed a leftover overview template that could never apply since the post type archive was switched off, and moved an inline script into a properly enqueued file.
* The developer documentation in docs/ was rewritten and corrected, including how to put the handbooks into your theme's navigation.

= 0.12.0 =
* Interface polish: the admin menu is ordered and clearly labelled, taxonomy edit screens use the right labels, the handbook list shows each handbook's access, the on-this-page block is titled "Table of Contents", and the overview and entry blocks can show their items as cards or as a list. Block controls that had no effect were removed, and a body class lets a site style the handbook without affecting the rest.
* Facets now filter without a page reload through an access-checked REST endpoint, so the separate filter button is gone; search still works without JavaScript.
* New "Handbook menu" block that lists the handbooks a visitor may read, collapsing behind a toggle on small screens. Optionally, the accessible handbooks are injected into a core Navigation block, or one of its submenus, that carries the "has-handbook-menu" class, so they ride the theme's own navigation and mobile menu.
* Settings: an uninstall option to keep or delete all handbook content (including templates you edited in the Site Editor). Sync failures are surfaced as an admin notice.
* The duplicate post type archive was disabled; the overview lives on a normal page holding the overview block.
* Internationalisation: the editor labels and the import screen's status messages are now translatable in any language (English source), and the build generates the translation template (living-handbook.pot).
* Renamed the GitHub source meta keys to the living_handbook_ prefix for clean, unique naming.
* Disclosed the bundled third-party libraries in the readme (mermaid.js 11.16.0 MIT, league/commonmark BSD-3-Clause, symfony/yaml MIT).

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
* Colours exposed as CSS custom properties for theme adaptation; see https://github.com/rfluethi/living-handbook/blob/main/docs/customization.md.

= 0.4.0 =
* Overview and navigation blocks, and a "Living Handbook" block category.

= 0.3.0 =
* Maintenance dashboard widget and handbook list columns.

= 0.2.0 =
* Access configuration UI, maintenance metadata, freshness status, feedback counter, default frontend rendering, and a German translation.

= 0.1.0 =
* Initial scaffold, data model, frontend access control, and internationalisation.

== Upgrade Notice ==

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
