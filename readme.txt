=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.76.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An internal team handbook for WordPress: structured page types, clear ownership, and freshness tracking so docs don't rot.

== Description ==

Living Handbook turns WordPress into an internal team handbook that is built to stay current. Unlike customer-facing knowledge base plugins, it focuses on the thing that makes internal documentation fail over time: maintenance.

Core features:

* A dedicated handbook content type with structured page types (Diataxis plus FAQ).
* Ownership per page: assign a responsible role to every page, and a reviewer as a WordPress user.
* Freshness tracking: per-page review dates and intervals, an overdue dashboard, and escalation for pages whose review is overdue.
* Access per handbook: public, all members, or restricted to specific roles and/or people, on the site and in wp-admin. Administrators read every handbook; editors read what a handbook grants them, so a handbook can be kept to administrators.
* Several handbooks side by side, each with its own access group, its own entry page, and its own navigation.
* An entry page per handbook with full-text search, taxonomy filters that apply without a reload, area tiles and recently updated pages.
* A single-page layout with per-handbook navigation, badges, an on-this-page table of contents, feedback and a metadata footer, all as blocks.
* Per-handbook navigation built from the page hierarchy: a self-contained, collapsible page tree with a Menu or Accordion display, styled by the plugin. No other plugin is required.
* A handbook menu block that lists the handbooks a visitor may read; it can also be injected into the theme's own navigation.
* Markdown import: paste a document, upload a ZIP, or point at a GitHub file or folder; a folder is read with its subfolders and the folder structure becomes the page hierarchy. A MkDocs project (mkdocs.yml) keeps its page structure, titles and order. Transport metadata and README are applied, internal .md links and their titles are resolved, and Mermaid and collapsible details are converted to blocks. Re-importing the same source refreshes the pages instead of duplicating them.
* GitHub sync: a page can be sourced from a Markdown URL. It is pulled on save, on demand and on a configurable schedule; its editor is locked, the page overview shows the source, and a block marks the public page.
* Learning paths (optional, off by default): put existing handbook pages in an order for onboarding or required reading, with a numbered lesson list, a Next button and a mark for what has been read. Progress is kept in the reader's browser only; nothing personal is stored on the server.
* Two exports: a bundle that moves a handbook, with its learning paths, to another site running the plugin, and a static website (a ZIP of plain HTML with the pages, their images, a page list, a search and a look you pick) for readers who have no access to your site at all. Both work on a single area too.
* The plugin brings a handbook of its own: the documentation of the app, written as a Living Handbook and shipped inside the plugin. One click on the import screen loads it into the site, in English or German, always matching the installed version; the living_handbook_app_handbook_url filter points it at your own GitHub repository instead.
* Fully translatable (English source), with a German and a Swiss German translation included.
* No external WordPress plugin is required; a block theme is. The import and sync require three Composer libraries (league/commonmark, symfony/yaml, enshrined/svg-sanitize), shipped in vendor/ together with their own dependencies. Mermaid diagrams are rendered by mermaid.js, bundled in assets/js/. All bundled code is under GPL-compatible licenses; see the FAQ for the third-party disclosure.

== Installation ==

1. Upload the plugin to `wp-content/plugins/living-handbook`.
2. Activate it through the Plugins screen in WordPress.
3. Activation creates a page called "Handbook" at /handbooks/ holding the overview block, and a notice points you to the next step. Deactivate and reactivate once, or visit Settings then Permalinks, if the handbook URLs do not work yet.
4. Create a handbook under Handbook, Handbooks, set who may read it, and assign your pages to it. A page without a handbook stays invisible on the front end.
5. Living Handbook is built for single-site installations. On a multisite network, activate and uninstall it per site; network-wide activation is not supported.

== Frequently Asked Questions ==

= Do I need another plugin? =

No. The plugin works on its own; it only expects a block theme.

= Does it work on multisite? =

The plugin is built for single-site installations. On a multisite network, activate and uninstall it on each site individually; network-wide activation is not supported, because the one-time setup (the starter terms, the overview page, the rewrite rules) and the uninstall cleanup run on the current site only.

= Are images in an internal handbook protected too? =

Their entries are: an image attached to a handbook page inherits that page's visibility, so it is not listed in the media endpoint and cannot be read there by someone who may not open the page. The file itself is a different matter. WordPress stores uploads in wp-content/uploads, and the web server delivers that folder directly, without asking WordPress. Anyone who knows or guesses the URL of the file can open it, and no plugin can change that from inside WordPress. If your handbook contains images that must not leave the team, protect the uploads folder at the server, for example with an Apache rule in wp-content/uploads/.htaccess or an nginx location block that requires authentication.

= Why are my handbook pages not visible? =

Almost always one of two reasons. Either the page is not assigned to a handbook: access is granted per handbook, so a page that belongs to none belongs to nobody, and the page list warns you about it. Or the handbook itself is not visible to you: a new handbook defaults to "All members (logged in)", so logged out you see nothing.

= Does the plugin connect to any external services? =

Yes, two, and both are described under External services below: GitHub, when you import from it or sync a page with it, and Gravatar, through WordPress's own avatars in the page footer.

= What third-party libraries does the plugin bundle? =

The diagram feature bundles mermaid.js version 11.16.0 (assets/js/mermaid.min.js), an open-source diagramming library by the Mermaid project, released under the MIT license, whose full text ships as assets/js/mermaid.LICENSE.txt. Homepage: https://mermaid.js.org. Source: https://github.com/mermaid-js/mermaid. It runs in the browser to draw Mermaid diagrams and makes no network calls. The bundle contains mermaid's own dependencies under their licenses: most are MIT, the d3 modules are ISC or BSD-3-Clause, the parser library chevrotain is Apache-2.0, DOMPurify is dual-licensed under MPL-2.0 or Apache-2.0, and one small geometry module, robust-predicates, is released under the Unlicense.

The import and sync also require three PHP libraries, shipped in vendor/ with their own dependencies: league/commonmark (BSD-3-Clause license), symfony/yaml (MIT license), and enshrined/svg-sanitize (GPL-2.0-or-later license), which cleans imported SVG images before they are stored. Their dependencies are BSD-3-Clause (league/config, and nette/schema and nette/utils, which are also offered under GPL-2.0 or GPL-3.0) and MIT.

All of these licenses are compatible with the plugin's GPL-2.0-or-later. Apache-2.0 is compatible with GPL version 3, which the "or later" in the plugin's license allows.

= Who can see a handbook? =

Access is set per handbook: public (no login), all logged-in members, or restricted to specific roles and/or people. It is enforced on the entry page and on single pages, in the search, the filters, the menu and the REST API. Two exceptions apply. Administrators read every handbook in full, restricted ones included; Editors read what a handbook grants them like everybody else, so a handbook can be kept to administrators, and in wp-admin other people's pages of such a handbook, its files and its settings stay closed to them. And anyone who may edit a page under the WordPress rules may also open it in the block editor, preview it and read it on the site, even when its handbook otherwise excludes them; for Authors that means their own pages, for Contributors their own drafts. Other people's pages, lists, search and navigation still follow the handbook's rules.

= What happens when I uninstall the plugin? =

By default your content is kept and only the plugin's own settings and caches are removed. On the settings page you can opt in to remove everything the plugin created, including any templates you edited in the Site Editor. Even then, files in the media library stay, including the ones uploaded to handbook pages; delete them in the media library if you no longer want them.

= What does the plugin store about visitors? =

Very little. The "Was this helpful?" feedback records which logged-in users voted on a page (their user IDs) so it can count one vote per user. By default it accepts no vote from logged-out visitors. If you switch on Public feedback under Handbook, Settings, Feedback, logged-out visitors can vote on pages of public handbooks; such a vote stores no cookie, no IP address and no identifier, it only raises the page's counter. To keep a script from filling that counter, anonymous votes are counted per page and hour in a transient (lh_fb_ followed by the page ID and the hour) that expires after two hours, and a page accepts 200 of them per hour by default. The counts and the voter list are stored as post meta in your own database and are removed with the content option when you delete the plugin.

Learning paths remember which lessons someone has read in that person's browser (localStorage), never on the server. The page footer shows avatars, which WordPress may load from Gravatar; see External services.

== External services ==

The plugin connects to two external services. Neither is contacted unless the feature that needs it is used.

= GitHub =

Used by the Markdown import from GitHub, by pages whose source is set to "Synced from GitHub", and by the App handbook tab only if a developer has pointed it at a repository with the living_handbook_app_handbook_url filter (by default the app handbook is read from the plugin folder and nothing is fetched).

* raw.githubusercontent.com: the Markdown file of a page and the images it references are downloaded when you import it, when you save a synced page, when you press Sync now, and on the background schedule you choose under Handbook, Settings (weekly by default, or off).
* api.github.com: when you import a folder, the repository's file list is read in one request, and for a folder of more than about twenty files the address of the repository archive is requested.
* codeload.github.com: that archive is then downloaded once, instead of one request per file.

What is sent: the repository owner, name, branch and file paths contained in the URLs you enter, and a User-Agent header naming the plugin. No content of your site, no user data and no access token is sent; only public repositories can be read. GitHub's terms of service: https://docs.github.com/en/site-policy/github-terms/github-terms-of-service. GitHub's privacy statement: https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement.

= Gravatar =

The page footer of a handbook page (the Handbook page meta block) shows the people who created, last updated and last reviewed the page, each with an avatar. The avatar comes from WordPress's own get_avatar(), which by default loads the image from Gravatar (secure.gravatar.com), a service by Automattic. Each reader's browser then requests the image, and the request carries a hash of that person's e-mail address. It happens every time such a page is viewed. To avoid it, untick Show Avatars under Settings, Discussion, which switches avatars off for the whole site, or switch off "Show people (avatar and name)" in the settings of the Handbook page meta block. Gravatar's terms of service: https://wordpress.com/tos/. Automattic's privacy policy: https://automattic.com/privacy/.

== Screenshots ==

1. The handbook overview: a card for each handbook a visitor may read.
2. A handbook entry page with search, facet filters, area tiles and recently updated pages.
3. A single handbook page: navigation, on-this-page, badges and the metadata footer.
4. The maintenance dashboard listing pages whose review is overdue.
5. The Markdown and GitHub import screen.

== Changelog ==

= 0.76.0 =
* Security: comments can only be written by people who may read the page; previous/next links, attachment pages, cards and search snippets respect access rules and passwords.
* Security: imports store only real images (and, in bundles, ordinary media files); an imported page cannot be placed under a page you may not edit.
* New: a bundle carries the handbook's learning paths, pointing at the imported pages.
* New: WordPress's privacy tools export and erase the plugin's personal data, with a suggested privacy policy text.
* Changed: whoever may edit a page gets it in the block editor and the preview, even in a handbook they may not read. Only administrators read every handbook; editors read what a handbook grants them, on the site and in wp-admin.
* Changed: loading the app handbook again lists pages that are no longer shipped and leaves pages loaded without the source connection alone. A new installation's overview page lives at /handbooks/.
* Fixed: a scheduled sync keeps the page order and excerpts the file does not set; links to sections of other pages work; MkDocs imports keep their metadata.
* Fixed: "delete everything" removes trashed pages, customised templates and every setting; the media library stays. Updates migrate without an admin visit.
* Fixed: a bundle keeps the password of a protected page; saving a page no longer makes user 1 its reviewer (check pages that name user 1).
* Faster: the navigation of a large handbook needs a fraction of the memory, and lists, learning paths and the overview need fewer queries.
* Fixed: dates turn at midnight in the site's time zone, plural forms, anchors on WordPress 6.8, and many smaller things listed in the changelog.

= 0.75.1 =
* Security: the bundled libraries for SVG cleaning, Markdown and YAML are updated past published vulnerabilities.
* Security: the website export copies only media from the uploads folder; a crafted link could pull a file from outside it into the ZIP.
* Fixed: a hand-written filter URL could put a handbook page on the "critical error" screen.
* Fixed: learning paths work right after switching them on or updating, without saving the permalinks; their blocks are known to the Site Editor; their images and comments follow the access rules of the handbook.
* Fixed: search, filters and feedback no longer fail for visitors on sites with a page cache.

= 0.75.0 =
* New: learning paths. Put existing handbook pages in an order, and people read them one after another with a Next button, a numbered list and a mark for what they have already read. For onboarding, or for a piece of required reading. Off by default, switched on under Settings, Learning paths.
* The pages stay ordinary handbook pages: a path only says which of them to read in which order, so nothing is copied and nothing has to be maintained twice.
* The build now refuses to produce a release when the translation tools are missing, instead of quietly shipping new text in English on a German site.
* This first stage stores no progress on the server. How far somebody has read is remembered in their browser and nowhere else, so nothing personal is stored, and it is gone when they clear their browser data or change device.

= 0.74.2 =
* Fixed: the website export showed your site's header, footer and menu, a second page tree beside the one from the template, and a white page in the plugin's own font instead of your theme's colours and type. 0.74.0 promised the theme and painted over it.

= 0.74.1 =
* The export screen goes back to how it started once the file has been downloaded, instead of leaving a button that can only answer with an error.

= 0.74.0 =
* The website export now looks like your site: it takes your theme's colours, fonts and spacing along, and it renders the pages through the same block template the site uses, so blocks you moved in the Site Editor sit where you put them in the export too.

= 0.73.0 =
* The website export gained three things: a look you choose when exporting (like this site, plain, dark, or paper for printing), Mermaid diagrams that are actually drawn, and images and diagrams that enlarge on a click, as they do on the site.

= 0.72.1 =
* Fixed: the download button of the website export led to "the link you followed has expired" instead of the file.

= 0.72.0 =
* New: export a handbook as a website. A ZIP of plain HTML files that opens by double-clicking index.html, with the pages, their images, a page list and a search, and no server or internet connection needed. For readers who have no access to your site. It contains what you can read, and it carries no access rules of its own, so pass it on the way you would pass on a printout.

= 0.71.2 =
* Nothing changed in the plugin: this release only makes the build script readable again, which on PHP 8.4 drowned in deprecation warnings from wp-cli itself.

Older versions are listed in [CHANGELOG.md on GitHub](https://github.com/rfluethi/living-handbook/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 0.76.0 =
Closes an independent review: access on comments, attachments and protected pages, safer imports, privacy tools, learning paths in bundles, and authors can edit their own pages again. Editors now read only the restricted handbooks that name them: check the roles of each one.

= 0.75.1 =
Security and bug fix release, recommended for every site: updated libraries with published vulnerabilities, a website export that cannot carry files from outside the uploads folder, and learning paths that work without saving the permalinks.
