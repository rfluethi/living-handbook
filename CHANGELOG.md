# Changelog

Full version history of Living Handbook. The plugin readme keeps only the most recent entries.

= 0.54.0 =
* A signed-in visitor who opens a handbook they may not read now gets an explanation and the status 403 instead of a 404. New setting Access, No-access page; the built-in message is filterable through living_handbook_access_denied_title and _message. The singular main query is no longer narrowed on pre_get_posts, because that produced the 404 before the guard could decide.
* A folder import of 20 pages or more (ARCHIVE_THRESHOLD) reads the files from one repository archive download instead of one request per Markdown file and per image. The file list still comes from the tree API, so a small import keeps the per-file path and does not fetch the whole repository for a handful of pages. A failed archive download is not fatal: the import falls back to single requests and notes why it is slow.
* Fixed: the app-handbook import publishes at once and now requires edit_others_posts, not edit_posts.
* Fixed: oEmbed described internal pages. The type is registered with embeddable => false (honoured from WordPress 6.8) and the lookup and response are filtered. Requires at least raised to 6.8.
* Fixed: the area-card cache is keyed per viewer.
* Fixed: a cross-handbook link keeps the file name as its text instead of the target title.
* Hardening: edit_others_posts on back-end AJAX reads, JSON_HEX_TAG on the export screen, is_uploaded_file on the ZIP import, auth_callback with the object id, export bundle in the system temp directory, no wp_cache_flush on uninstall, sanitize_callback on every REST-writable meta field.

= 0.53.0 =
* Fixed: the scheduled sync skipped every handbook that is not public. Cron runs without a logged-in user, and the reader filter narrowed the maintenance lookup to public handbooks, so a members or restricted handbook was never updated, silently and without an error. Internal lookups now opt out of that filter; front-end reads stay filtered exactly as before.
* Fixed: an internal link on a page of a non-public handbook could be degraded to plain text during a sync and written back, only to reappear on the next manual sync.
* Fixed: re-importing into a non-public handbook through the REST import endpoints created duplicate pages instead of updating the existing ones.
* Internal: the access layer gained AccessController::internal(), which marks the plugin own lookups, plus integration tests covering the scheduled sync across all three visibilities.

= 0.52.0 =
* Mermaid diagrams can now be clicked to enlarge in the lightbox, like the images. The enlarged diagram gets a light backing so its lines and text stay readable on the dark overlay.
* The bundled app handbook now sits under a single top page, "Living Handbook", with every area and page nested beneath it, one clean tree instead of many top-level entries.
* Fixed the entry filter list on themes that render checkboxes as block or full-width elements: the checkbox is kept small and native, so its label stays beside it instead of dropping below or stretching across the column.
* During this cycle the entry list contrast was raised and then reverted, so the lists use the theme's own colours; tune them through the Custom CSS setting if you want more.

= 0.51.1 =
* The bundled app handbook is now complete in German and available in English as well, translated from it. The English pages still carry the German screenshots for now; localized ones will follow.

= 0.51.0 =
* Images in handbook content can now be clicked to enlarge, in a dark overlay like the core Image block's lightbox, closed by a click, the close button or Escape. This is a handbook-only feature because handbook images are stored as plain HTML, not Image blocks, so the core setting never reaches them. A raster image becomes clickable only when it is shown smaller than its real size, so small icons are left alone; an SVG is always clickable, since it stays sharp at any size.
* Mermaid diagrams now render on the bundled app handbook too. The script that draws them only loaded on GitHub-synced pages, so on a locally loaded app handbook (a WordPress-source page) the diagrams stayed as code. It now loads on any handbook page whose content holds a diagram.

= 0.50.4 =
* The bundled app handbook was expanded: the German pages now carry screenshots and diagrams, and the text stresses more clearly that a page can be tailored in look and function through the blocks and templates. Reload the app handbook to get the new version.

= 0.50.3 =
* An imported image is now attached to the page it belongs to, so the media library shows it as uploaded to that page instead of unattached. A shared image keeps the first page it landed on.

= 0.50.2 =
* Imported SVG images now sideload even when the site does not allow SVG uploads: the plugin permits the SVG mime type only for the moment it stores its own already-sanitised SVG, not for user uploads in general. This is why the app handbook's diagram images did not appear.
* An image on a handbook page is now capped at the column width, so a large screenshot no longer overflows the page.

= 0.50.1 =
* App handbook pages are now locked in the editor, like GitHub-synced pages: they are managed content that a re-load replaces, so editing them by hand would only be lost on the next load. A notice on the page says so.
* Fixed a relative image reference with a percent-encoded path (a space as %20) not resolving to the file on disk, so such an image is now sideloaded too.

= 0.50.0 =
* The app handbook now ships with the plugin instead of loading from GitHub, so it always matches the installed version and no install depends on a repository staying reachable. The "App handbook" tab imports it from the bundled folder; loading again after a plugin update refreshes the pages. A fork can still point the tab at a GitHub repository through the living_handbook_app_handbook_url filter.
* The GitHub folder import now brings images along: an image a page references by a relative path (like ../assets/x.svg) is fetched from the repository and sideloaded into the media library, so it is no longer a link that 404s on the site. The same happens on every later sync, and shared images are stored once.

= 0.49.0 =
* Public feedback: a new setting lets logged-out visitors vote "Was this helpful?" on public pages. To stay privacy-friendly it stores nothing personal, no cookie, no IP, no identifier, so the same visitor can vote again after reloading; the trade-off is no per-person limit. Off by default. On internal pages, logged-in users still vote once each regardless of this setting.
* Feedback can be reset per page: a "Reset feedback" action in the handbook list clears a page's counters, for a page reworked after weak feedback.
* The URL bases of a handbook page and a handbook grouping (/handbook/, /handbook-set/) can now be changed with two filters, living_handbook_post_type_slug and living_handbook_taxonomy_slug, for a site that needs a localized base. Changing them on a live site rewrites URLs and needs the permalinks flushed.
* New handbook pages default to comments closed, so a handbook is not a comment thread unless you want one. Imported pages, the app handbook included, are created with comments off; switch them on per page in the Discussion panel.
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

= 0.41.0 =
* The app handbook now comes from GitHub instead of being shipped inside the plugin. The documentation of the app lives in a public repository, so it has one source, can be read and edited where it is written, and every install pulls the current state instead of a snapshot frozen at release time. The import screen keeps its "App handbook" tab and one-click button; behind it is an ordinary GitHub folder import against a fixed URL, chosen by the admin language.
* This drops the bundled example handbook added in 0.39.0. A separate demo made little sense next to real documentation: a good handbook explains the app well enough that the reader can try it, the same way any application's manual does. The bundle export and import between sites is untouched; only the shipped copy is gone.

= 0.40.0 =
* The GitHub folder import now descends into subfolders, and the folder structure becomes the page hierarchy. Previously only the files directly in the chosen folder were imported and everything landed side by side.
* A folder becomes a page: an index.md or README.md inside it becomes that folder's own page and its siblings hang under it; a folder with neither gets a page made from its name, carrying the area entries block, so a level that exists in the repository does not go missing from the navigation.
* The whole repository tree is now read in a single request to the Git trees API instead of one request per folder. Unauthenticated GitHub allows 60 requests an hour, so the old approach would have run out on any real documentation repository. At most 200 files are imported at once, and the result says so when the limit or GitHub's own tree limit was hit.
* On a re-import the repository decides the structure again, the same way it already decides the content of a synced page.

= 0.39.0 =
* New: the plugin brings its own handbook along. Nine pages in three areas that document how a Living Handbook is built, written as a working handbook so it is at the same time an example: several page types, filled vocabularies and pages in every review state, so the page type, the filters and the freshness badges can be seen doing their job instead of being described. It is meant to be taken apart: read it, change it, delete it.
* It is loaded on request, on the import screen under the new tab "App handbook", and never on activation. Content that appears by itself is content nobody asked for. By default it goes into a handbook of its own with visibility "members", so nothing becomes public, and loading it twice never overwrites an edit. You can also load it into an existing handbook. The notice after activation points at it, because an empty install cannot show what a page type or a freshness badge is for.
* It can carry images: they ship in the plugin, are sideloaded into the media library on load, and are recognised again by a content hash so a second load does not duplicate them.
* It exists in English and German and follows the admin language. Its vocabulary terms attach to the seeded ones instead of creating a second set next to them, and its review dates are relative to the moment it is loaded, so it does not turn entirely overdue as the release ages.

= 0.38.1 =
* Test quality: the new REST access tests got a counter-check. A test that only asserts "the answer does not contain this" passes just as happily when the answer is empty for everyone, which would prove nothing. The counter-check confirms that a content manager does get the content over the same four routes, so the negative tests fail if the routes ever stop returning anything.

= 0.38.0 =
* Privacy: an export no longer writes the list of individually allowed people into the bundle. Those are e-mail addresses, and a bundle is a file that gets downloaded and passed on; the target site has its own users anyway. Visibility and allowed roles still travel. If a handbook is restricted to named people, set them again after importing.
* The separation of internal content over the REST API is now covered by tests: a logged-out guest and a subscriber get nothing from a members-only or restricted handbook, over the plugin's own filter and search routes as well as the core collection and single-item routes. Those two plugin routes are open on purpose so a public handbook stays searchable; the docblock now says so, and the tests make sure the checks inside them cannot quietly go missing.

= 0.37.0 =
* Security: content that arrives already converted to blocks is now cleaned before it is stored. This closes a hole in the bundle import, where a prepared bundle could have carried active markup into the handbook and run it in the browser of every logged-in reader. Cleaning runs block by block, so the block structure is untouched; scripts, event handlers and unsafe URLs are removed. The same cleaning now also runs on the import screen's create endpoint, so it is a property of writing rather than of converting.
* This corrects an earlier decision. The bundle import used to insert content as it came, on the argument that block markup cannot pass through the HTML filter and that the required role was safeguard enough. Both were wrong: parsing the blocks first solves the first, and on a single site editors and administrators hold unfiltered_html, so the core filter does not run for exactly the people who may import. Found by a security review of 0.36.0.

= 0.36.0 =
* Export moved to its own screen under Handbook, Export, the way WordPress keeps its own import and export tools apart.
* The import screen now holds everything a source needs inside that source's own tab: its field, its options and its import button. Nothing from another source is on screen, and there is only ever one button.
* Importing a bundle is now one of those tabs, next to pasted text, a ZIP and GitHub, instead of a separate block at the bottom of the page.
* The German translation of the export and import screens is complete again; the new strings had been left untranslated while the layout was still moving.

= 0.35.0 =
* Tests for the bundle export and import: a round trip into another handbook, each of the three conflict rules, the protected flag, the vocabularies travelling with a page, and an area export carrying only its own subtree.
* Fixed, found by those tests: when a page was matched by slug, the restriction to the target handbook did not apply, because a query by name is treated as a lookup for a single page. An import into one handbook could therefore match a page of the same slug in a different handbook and, depending on the rule, skip or overwrite it. Both the bundle import and the Markdown re-import used that lookup and are corrected.

= 0.34.0 =
* The bundle import can now be pointed at an existing handbook instead of the one named in the bundle. The chosen handbook keeps its own access configuration.

= 0.33.0 =
* New: import a bundle. Upload a bundle exported from another site and choose what happens when a page already exists: skip it (the default, never overwrite), update it, or always create a new one. A page marked as protected is never overwritten either way, and nothing is ever deleted.
* On update the local upkeep stays put: the feedback counts and the review date, interval and reviewer belong to this site. A page created by the import does take those from the bundle.
* An imported handbook that did not exist here is created with visibility "members", even when the bundle says public, so an import can never silently publish content. An existing handbook keeps its own access configuration.
* Internal links between the imported pages are rewired to the new pages, GitHub-sourced pages resume syncing from their repository, and media travels with the bundle. Importing requires the content-manager role.

= 0.32.0 =
* The export picker now works in two dependent steps: choose the handbook, and the second field lists only that handbook's areas. Previously it offered every handbook's areas at once, which is unusable once a site has more than a few.

= 0.31.0 =
* Export now also does a single area: pick a top-level page and export just it and its subpages, instead of the whole handbook. The bundle still carries the handbook's configuration.

= 0.30.0 =
* Housekeeping: the review-status filter no longer sets suppress_filters on its internal lookup, which the Plugin Check flags. No functional change.

= 0.29.0 =
* New: export a handbook as a self-contained bundle (a ZIP with a manifest and the media) from Handbook, Import, at the bottom of the page. First half of the export/import feature; the matching import follows. The bundle carries the pages, the handbook configuration, the vocabularies and the freshness data, and keeps GitHub-sourced pages pointing at their repository. Requires the content-manager role.

= 0.28.0 =
* The block editor now loads the handbook stylesheet only when you edit a handbook page, not in every editor. No visible change on handbook pages; other post-type editors just load a little less.

= 0.27.0 =
* The handbook list now has a filter dropdown for every vocabulary too: page type, topic, responsibility and audience, next to the existing handbook and source filters, the same way the category filter works for posts. Filtering by taxonomy is standard; sorting the taxonomy columns stays off, because a page can belong to several terms.
* A review-status filter (reviewed, due, overdue, never reviewed) narrows the list by freshness. The Last reviewed column keeps sorting by date; the status, which also depends on each page's review interval, is now a filter of its own.
* Fixed the Last reviewed sort: pages without a review date used to split to both ends of the list. They now stay grouped and always follow the dated pages, in both directions.

= 0.26.0 =
* The handbook list now has a Feedback column that sorts by net feedback (yes votes minus no votes), so the best and worst received pages are one click away.
* Two filter dropdowns above the handbook list: filter by handbook, and by source (GitHub or WordPress). Taxonomy columns stay unsorted on purpose, because a page can belong to several handbooks; filtering is the reliable way to group them.
* The two warnings on the handbook list, pages without a handbook and GitHub pages that failed to sync, now list the affected pages as direct links, so you reach each one in a click instead of hunting for it.

= 0.25.0 =
* Fixed: the import progress messages ("Creating 3 pages", "2 links converted" and the like) are translatable again. Seven plural strings were calling the translation function without the plugin's text domain, so on a German site they stayed English; they now read in the site language.
* Access: back-end tools that run over admin-ajax, such as the classic editor's "link to existing content" search, again find handbook pages for users who may edit posts. The frontend visibility rules are unchanged; a page's content stays guarded, and comment visibility keeps its stricter rule.

= 0.24.0 =
* Every handbook block now offers an HTML anchor and an additional CSS class under the block's Advanced panel. The anchor becomes the id of the block's root element and the class is added to it, so you can link to a block or target a single instance from the Custom CSS field or your theme.

= 0.23.1 =
* Packaging: the build now strips every hidden file from the release ZIP, so a stray .fuse_hidden orphan from a network or FUSE mount, which the plugin repository check rejects, never ships. Silenced a false-positive lint warning on the one-time feedback-meta migration. No functional change.

= 0.23.0 =
* Code review F7 completed: the Mermaid diagram block and the GitHub source-note block now also take their metadata from a block.json file, so every handbook block uses the same single-source registration. The render callbacks are unchanged, so nothing changes on the page. The Mermaid block shows a sample diagram in the inserter preview, and an empty Mermaid block no longer loads the diagram library until it has code.

= 0.22.0 =
* Code review F7: the nine handbook blocks now take their metadata (title, category, icon, attributes, supports, keywords) from a block.json file each, a single source, instead of duplicating the definitions between the PHP registration and the editor script. The server render callbacks are unchanged, so nothing changes on the page, and the blocks gain a preview in the inserter.

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
