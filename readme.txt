=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.58.0
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
* Fully translatable (English source), with a German and a Swiss German translation included.
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

= Are images in an internal handbook protected too? =

Their entries are: an image attached to a handbook page inherits that page's visibility, so it is not listed in the media endpoint and cannot be read there by someone who may not open the page. The file itself is a different matter. WordPress stores uploads in wp-content/uploads, and the web server delivers that folder directly, without asking WordPress. Anyone who knows or guesses the URL of the file can open it, and no plugin can change that from inside WordPress. If your handbook contains images that must not leave the team, protect the uploads folder at the server, for example with an Apache rule in wp-content/uploads/.htaccess or an nginx location block that requires authentication.

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

= 0.58.0 =
* Comments can be switched for a whole handbook, not only page by page: leave it to each page, or open or close comments for every page of that handbook at once. Under Handbook, Handbooks. Closing hides the form; comments already written stay and are not deleted.
* Links to another website are no longer broken by the import. A link whose address ends in .md was treated as a link inside the import even when it pointed at github.com, so it was stripped to plain text and reported as a dead link. This is what made a fresh installation of the shipped handbook report six of them.

= 0.57.0 =
* Comments on a handbook page work now. They never showed up, whatever you set: no shipped layout contained a comment block, and the import closed comments on every page it created. Open them under the page's Discussion panel and they appear.
* Mermaid diagrams follow your theme instead of always drawing light, so the connecting lines stay visible on a dark theme.
* An imported diagram keeps the title and description it carries (accTitle and accDescr), so screen readers get a description instead of the diagram source.
* The Custom CSS field no longer lets a pasted snippet load fonts or images from a foreign server, which would have made every reader of every handbook page contact that server.
* Anonymous feedback is capped at 200 votes per page and hour, so a script cannot fill the counter.
* An export bundle now carries the reviewer's user name instead of their e-mail address.
* The German translation covers the appearance settings. They reached a German site in English since 0.56.0, because the translation template was last built before that tab existed.

= 0.56.0 =
* The settings screen is now split into tabs: GitHub sync, Appearance, Feedback, Access, Uninstall. Saving writes the tab you are on and leaves the others alone.
* New under Handbook, Settings, Appearance: ten colour fields and a text size, for the case your theme's colours do not fit. A page carries up to three small badges and they keep three different colours on purpose, so the page type follows the accent while the topic and the audience have a pair each. Leave a field empty and the handbook follows your theme, exactly as before; the colour picker offers your theme's own palette. The text size scales what the plugin itself sets, the navigation, table of contents, badges, cards and page details, not the text of a page. Useful if your theme sets larger text than 16 pixels and the handbook looks small beside it. Nothing changes until you fill something in.
* Fixed: a handbook block placed in a header, a footer or a template part was styled differently from the same block on a handbook page, because your Custom CSS did not reach it.
* The page layouts the plugin brings along are rearranged. On a handbook page the title and the text come first, and everything about the page follows underneath: the feedback question, where the page comes from, the badges and the page details. The handbook search now sits in the left column under the navigation, and the navigation arrives collapsed, which is what a handbook with many levels needs in a narrow column. If you have edited one of these layouts in the Site Editor, your version stays; Design, Editor, Templates, Clear customizations brings the plugin's back.
* Fixed: a page could be put into two handbooks at once, and then showed the navigation of the wrong one. A page belongs to one handbook; choosing another one now moves it there instead of adding a second. Pages that are already in two stay as they are until you save them again, and are shown consistently in the meantime.
* Fixed: the two custom fields that record where a page comes from were editable by hand in the Custom Fields box, where changing them silently stopped a page from syncing, or handed a hand-written page to the next sync. They are now internal fields, like the rest of the plugin's bookkeeping. Existing pages are renamed automatically when the plugin updates; nothing needs doing.
* An import that runs into GitHub's hourly request limit now stops cleanly and tells you when you can continue, instead of carrying on and marking every remaining page with an error. Start the import again afterwards: pages that already exist are updated, not duplicated.
* Fixed: after an import, links between pages could be missing. A link to a page that had not been imported yet was turned into plain text and stayed that way. Whether a link survived depended on the order in which the pages happened to be imported. Links are now decided once the whole import is there, and the import screen shows that step instead of appearing to hang after the last page. Import a handbook again to repair its links.
* Large handbooks are much faster. Every handbook view used to cost the server about one database query per page in the handbook, so a handbook of 2000 pages needed around 2000 queries to show one page, and now needs about 20. Who may read what is unchanged.
* Accessibility: images and diagrams can now be enlarged with the keyboard, not only with the mouse, and the enlarged view keeps the focus until you close it. The search on a handbook page can be walked with the arrow keys, and screen readers are told how many pages a search or filter found instead of having the whole list read out after every keystroke.
* The handbook styles and scripts now arrive wherever a handbook block is placed, including in a header, a footer or another template part. Before, a block outside a handbook page could end up unstyled and without its interactive parts. Pages without a handbook block load nothing extra.

= 0.55.0 =
* A large folder import no longer stops with a blank screen or a server timeout. The import now works in passes: it imports as many pages as it can, then picks up where it left off, and the screen shows the pages as they arrive and how many are still to come. You can import a handbook of a few hundred pages in one go.
* A large folder import now downloads the repository once instead of asking GitHub for every single file. From about twenty pages on that is one request instead of hundreds, so importing a whole handbook no longer runs into GitHub's hourly limit and stops halfway. Small imports work exactly as before, and if the download fails the import carries on file by file and says so in its report.
* Fixed: importing a MkDocs project as a ZIP ignored its navigation and created a flat list of pages named after the files, as soon as mkdocs.yml configured a Python plugin, which is what the usual Mermaid setup does. The navigation is now read even then, and the import tells you when it could only read part of the file.
* Fixed: the media endpoint listed images from internal handbooks to anyone, with their title, alt text and file address. An image now inherits the visibility of the handbook page it belongs to. Protecting the image file itself is still a matter for your web server, see the FAQ.
* The German translation is complete again, and a Swiss German translation (de_CH) is now included: same wording, written the Swiss way without the sharp s.
* The libraries the plugin brings along for the import now live under a name of their own. If another plugin brings the same library in a different version, both keep working; before, whichever loaded first won and the import could switch itself off.

= 0.54.0 =
* A signed-in visitor who opens a handbook they may not read now gets an explanation and the status 403, instead of a bare 404 that claimed the page did not exist. The new setting Access, No-access page lets you point that at one of your own pages; left on the built-in message it works out of the box. Guests are still sent to the login and returned to the page afterwards.
* Fixed: a contributor could load the app handbook, which publishes its pages at once, although contributors may not publish. That import now requires the same permission as the bundle import and export.
* Fixed: the oEmbed endpoint described pages of an internal handbook to anyone, with title and author name. WordPress closes this itself from 6.8 on; the plugin now also declares the type as not embeddable and gates the endpoint. Requires at least is raised to 6.8 for that reason.
* Fixed: the area cards of a handbook were cached without the viewer, so pages an editor may see could be served to a guest for up to a day.
* Fixed: a link into another handbook no longer borrows the target page title as its link text, so the title of a page in a stricter handbook does not surface in an open one.
* Hardening: stricter permission on back-end AJAX reads, safer encoding of page titles on the export screen, an upload check on the ZIP import, per-post permission on the meta fields, the export bundle is written outside the public uploads folder, uninstalling no longer flushes the whole object cache, and every field writable over the REST API is validated.

= 0.53.0 =
* Fixed: the scheduled sync skipped every handbook that is not public. Cron runs without a logged-in user, and the reader filter narrowed the maintenance lookup to public handbooks, so a members or restricted handbook was never updated, silently and without an error. Internal lookups now opt out of that filter; front-end reads stay filtered exactly as before.
* Fixed: an internal link on a page of a non-public handbook could be degraded to plain text during a sync and written back, only to reappear on the next manual sync.
* Fixed: re-importing into a non-public handbook through the REST import endpoints created duplicate pages instead of updating the existing ones.

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

= 0.58.0 =
Comments can now be switched for a whole handbook instead of page by page. Fixes links to other websites being stripped by the import. Nothing needs doing on update; the new setting arrives switched off.

= 0.57.0 =
Recommended: comments on handbook pages finally work, Mermaid diagrams follow a dark theme, and the Custom CSS field can no longer pull fonts or images from a foreign server. Nothing needs doing on update; pages keep the comment setting they have.
= 0.56.0 =
Recommended for every site, especially large handbooks: a handbook view costs a fraction of the database queries it used to, and imports no longer lose links or stop at GitHub's hourly limit. Import an affected handbook again to repair its links.
= 0.55.0 =
Recommended if you import from GitHub or MkDocs, and for every site with internal handbooks: large imports no longer die at the server's time limit, a MkDocs navigation is kept, and the media endpoint no longer describes images from handbooks you may not read.

= 0.54.0 =
Recommended for every site with internal handbooks: closes two read channels and replaces the 404 for a denied page with a real explanation. Requires WordPress 6.8 or newer.

= 0.53.0 =
Required if you sync a members or restricted handbook from GitHub: the scheduled sync never reached those handbooks before this release.

= 0.52.0 =
Mermaid diagrams enlarge on click, the entry filter list stays readable on themes that stretch form controls, and the bundled app handbook sits under one "Living Handbook" top page.
