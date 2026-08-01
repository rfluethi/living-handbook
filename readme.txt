=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.52.0
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
* The plugin brings a handbook of its own: the documentation of the app, written as a Living Handbook and shipped inside the plugin. One click on the import screen loads it into the site, in English or German, always matching the installed version; the living_handbook_app_handbook_url filter points it at your own GitHub repository instead.
* Fully translatable (English source), with a German translation included.
* No external WordPress plugin is required; a block theme is. The import and sync require three Composer libraries (league/commonmark, symfony/yaml, enshrined/svg-sanitize), shipped in vendor/ together with their own dependencies, all under GPL-compatible licenses (BSD-3-Clause, MIT, GPL-2.0-or-later). Mermaid diagrams are rendered by mermaid.js, bundled in assets/js/ (see the FAQ for the third-party disclosure).

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

The diagram feature bundles mermaid.js version 11.16.0 (assets/js/mermaid.min.js), an open-source diagramming library by the Mermaid project, released under the MIT license, whose full text ships as assets/js/mermaid.LICENSE.txt. Homepage: https://mermaid.js.org. Source: https://github.com/mermaid-js/mermaid. It runs in the browser to draw Mermaid diagrams and makes no network calls. The import and sync also require three PHP libraries, shipped in vendor/ with their own dependencies: league/commonmark (BSD-3-Clause license), symfony/yaml (MIT license), and enshrined/svg-sanitize (GPL-2.0-or-later license), which cleans imported SVG images before they are stored. All bundled libraries use GPL-compatible licenses (BSD-3-Clause, MIT, GPL-2.0-or-later).

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

= 0.52.0 =
* Mermaid diagrams can now be clicked to enlarge in the lightbox, like the images. The enlarged diagram gets a light backing so its lines and text stay readable on the dark overlay.
* The bundled app handbook now sits under a single top page, "Living Handbook", with every area and page nested beneath it, one clean tree instead of many top-level entries.
* Fixed the entry filter list on themes that render checkboxes as block or full-width elements: the checkbox is kept small and native, so its label stays beside it instead of dropping below or stretching across the column.

= 0.51.0 =
* Images in handbook content can now be clicked to enlarge, in a dark overlay like the core Image block's lightbox, closed by a click, the close button or Escape. A raster image becomes clickable only when it is shown smaller than its real size, so small icons are left alone; an SVG is always clickable, since it stays sharp at any size.
* Mermaid diagrams now render on the bundled app handbook too. The script that draws them only loaded on GitHub-synced pages, so on a locally loaded app handbook (a WordPress-source page) the diagrams stayed as code. It now loads on any handbook page whose content holds a diagram.

= 0.50.0 =
* The app handbook now ships with the plugin instead of loading from GitHub, so it always matches the installed version and no install depends on a repository staying reachable. The "App handbook" tab imports it from the bundled folder; loading again after a plugin update refreshes the pages. A fork can still point the tab at a GitHub repository through the living_handbook_app_handbook_url filter.
* The GitHub folder import now brings images along: an image a page references by a relative path (like ../assets/x.svg) is fetched from the repository and sideloaded into the media library, so it is no longer a link that 404s on the site. The same happens on every later sync, and shared images are stored once.

Older versions are listed in [CHANGELOG.md](https://github.com/rfluethi/living-handbook/blob/main/CHANGELOG.md) in the repository.

== Upgrade Notice ==

= 0.52.0 =
Mermaid diagrams enlarge on click, the entry filter list stays readable on themes that stretch form controls, and the bundled app handbook sits under one "Living Handbook" top page.
