# Import and GitHub sync

How to get Markdown into the handbook, and how a page can stay synced from a GitHub repository.

## The import screen

Under **Handbook → Import**, each source has its own tab, and everything that source needs sits inside it: the field, its options and its import button. Only the chosen source is on screen, so a pasted draft is never ignored because a URL is still in another field, and there is only ever one button to press. A short "How the import works" section at the top explains the details on demand.

The sources are:

1. **Paste text**: paste a Markdown draft, then **Import Markdown**.
2. **ZIP file**: upload a ZIP of `.md` files, then **Import ZIP**. The ZIP may be a flat set of files or a structured MkDocs project (see below).
3. **GitHub**: enter a GitHub URL, then **Import from GitHub**. Both a single file and a whole folder work:
   - a file, either a `raw.githubusercontent.com` URL or a `github.com/.../blob/...` URL (the blob URL is normalised to raw automatically);
   - a folder, a `github.com/.../tree/...` URL. Every `.md` file under that folder is imported, subfolders included, and the folder structure becomes the page hierarchy (see below).

4. **Bundle**: upload a bundle exported from another site running the plugin (see below). Requires `edit_others_posts`, so an editor or above.
5. **App handbook**: load the app's own handbook, the copy bundled with the plugin, in one click (see below). Requires `edit_others_posts`, so an editor or above: the tab is shown to anyone who may import, but the load itself publishes a whole handbook and refuses lesser roles.

Pages that land without a handbook are read on the front end only by editors and administrators, because access is fail-closed.

As the target handbook the screen offers only handbooks the importing person may read, and `/import-github` and `/create` refuse any other with "You are not allowed to import into this handbook.": whoever may not read a handbook does not write into it either. That holds for every source, the app handbook included. The scheduled sync and WP-CLI run without a user and are exempt (`AccessController::may_use_handbook()`).

**How the conversion works:** league/commonmark renders the Markdown to HTML, and WordPress's own paste conversion turns that HTML into editable blocks. A ```` ```mermaid ```` code fence becomes a live-rendered Mermaid block, collapsible `<details>` sections become details blocks, and images referenced from an `assets` folder are sideloaded into the media library. Once all pages exist, a shared post-processor applies the transport metadata and resolves parents and internal `.md` links, both the link target and the visible link text.

### Importing a folder with its subfolders

A folder import reads the whole repository tree in **one** request to the Git trees API, not one request per folder. Unauthenticated GitHub allows 60 requests an hour, and a walk that spends one on every folder runs out on a documentation repository of any size.

The folder structure becomes the page hierarchy, and a folder becomes a page:

- A folder holding an **`index.md`** (or, failing that, a **`README.md`**) is represented by that file: it becomes the folder's page, and everything else in the folder hangs under it.
- A folder holding **neither** gets a page made from its name, carrying the area entries block, so it lists what is inside it. A level that exists in the repository but not in the handbook would leave a hole in the navigation.
- The **`README.md` of the folder you point at** stays an ordinary page. There is no folder page above it to be consumed by.
- Levels that exist only as path segments are filled in, so `docs/one/two/three/page.md` produces three levels and not one.

At most **200 files** are imported in one go. If that limit is reached, or if the repository is too large for GitHub to return its tree in one piece, the result list says so; import the remaining subfolders separately.

**A folder import runs in passes.** One request would not survive a few hundred pages, so the import stops after `IMPORT_BUDGET` seconds (20, capped at 60 percent of `max_execution_time`, filterable through `living_handbook_import_time_budget`), saves the rest of its work list in a transient and answers with a job id; the import screen asks again with that id until the queue is empty. It stops between two pages, never inside one, and a job belongs to the user who started it.

Resolving the internal links is the **second phase of the same job**, not a closing step. It runs when every page of the import exists and pauses on the same budget, reporting `phase: links` so the screen can say what it is doing. While pages are still being created, a link whose target does not exist yet is left untouched instead of being turned into text; only the closing phase decides. Otherwise a page imported before its target would keep a dead link for good, and whether a link survived would depend on the order of the work list.

**The GitHub request quota** is read from every API answer (`X-RateLimit-Remaining`, `X-RateLimit-Reset`). When it is spent, the import stops on a whole page and reports how many pages it managed and when the limit resets, instead of carrying on and writing an error onto every remaining page. The answer carries `retry_after`, so the screen stops asking. Starting the import again updates the pages that exist rather than duplicating them. Those headers come from `api.github.com`; the raw file host and the archive download have their own, opaque limits and report nothing, which is one more reason the archive path exists.

On a re-import the repository decides the structure again, so a parent set by hand is reset. That is the same bargain as the content of a synced page: for a folder import the repository is the original.

**Ordering.** The order of pages and areas comes from the transport metadata: a page with a `Reihenfolge` line in its transport block (see below) is placed by that number. A page without one falls back to its position in the import, which is deep-last and alphabetical, and always sorts after the numbered pages. So you only number the pages whose order matters, keep the numbers small (1, 2, 3), and leave the rest. An area's own order lives in its `README.md`; an area folder without one is ordered by the fallback.

An `index.md` or `README.md` that stands for a folder takes its slug from the **folder** name, not from the file name, so the area page gets a clean URL instead of `readme`.

**Images.** An image a page references by a relative path (for example `../assets/x.svg`) is fetched from the repository and sideloaded into the media library, so the stored page points at the media copy instead of a path that would 404 on the site. It happens on the import and on every later sync; sideloading dedupes by file name and content, so a shared image is stored once and reused. An absolute image URL is left as it is.

## Importing the same source twice

Re-importing the same source **updates the existing pages instead of creating duplicates**. How a page is recognised depends on the import:

- **ZIP and MkDocs imports** are matched by the stored source path. If a page has no source path, it is matched by slug within the chosen handbook.
- **GitHub imports** are matched by the Markdown source URL.
- **A pasted draft always creates a new page.** It carries neither a source path nor an explicit slug, and silently overwriting an existing page just because a title matched would be worse than a duplicate.

When a page is updated this way it keeps its **slug and its publication status**, so URLs stay stable and a published page does not silently drop back to draft. Title, content and parent are refreshed from the source.

## Exporting a handbook

Under **Handbook → Export**, anyone with `edit_others_posts` (an editor or above) can export a handbook they may read **as a bundle**; others are not offered, and one passed in anyway is refused by the export with a 403. The bundle is a single ZIP with a `manifest.json` and a `media/` folder. Pick the **handbook** first; the second field then lists that handbook's **areas** (an area is a top-level page, and it exports together with its subpages). Leave it on *the whole handbook* to export everything. Then **Export bundle**, and the ZIP downloads. It is self-contained, so it can be moved to another site running the plugin without reaching back to this one. An area bundle still carries the handbook's configuration, so the target knows where the pages belong. An area the person may not view, or one in the trash, is refused with a 403 ("That area cannot be exported.") instead of falling back to the whole handbook.

The bundle carries the handbook's configuration (visibility and allowed roles), every page as a block-markup snapshot with its place in the hierarchy, the four classifying taxonomies, the freshness metadata, and the referenced media. It deliberately does **not** carry the list of individually allowed people: those are e-mail addresses, a bundle is a file that gets downloaded and passed on, and the target site has a different set of users anyway. If a handbook is restricted to named people, set them again after importing. A GitHub-sourced page keeps its source URL, so on the target site it resumes syncing from the same repository. Local, site-specific data is deliberately left out: the feedback counts and the sync status belong to each site.

**Learning paths.** The bundle takes the handbook's learning paths along, in every status (published, scheduled, draft, pending, private), in the manifest field `paths`. Each entry carries `key` (`path:<slug>`, with `-<ID>` appended on a collision), `origin_id`, `title`, `slug`, `status`, `content`, `excerpt`, `lessons`, the lessons as an ordered list of the bundle's page keys, and `media` as for a page. The manifest `version` stays 1; older plugin versions ignore `paths`. When the whole handbook is exported, a path without lessons stays in. In an area export a path keeps only the lessons in the area, and a path without a lesson in the area is missing from the bundle.

**Password-protected pages.** A page or learning path with a password carries the field `password` in the bundle, holding the password in plain text; without a password the field is absent, as in older bundles. The protected text is readable in the bundle anyway, so the password does not make the file more sensitive, but it keeps the protection on the target site. Treat a bundle as confidential as the protected pages themselves: do not pass it on where they do not belong, and do not leave it lying around after the import.

**What the exporting person may not read is left out.** The bundle export omits pages and paths that the `living_handbook_can_view_post` filter hides from the exporting person, as the website export does too.

## Exporting a handbook as a static website

The same screen offers a second export, for readers who have no WordPress at all: **Export as a website** builds a ZIP of plain HTML files that opens from the file system, no server and no network required. `edit_others_posts` again and a handbook the person may read, and the screen states the consequence plainly: a static copy carries no access rules, so whoever holds the file reads every page in it.

Four decisions are worth knowing when reading the code (`Export/StaticSite.php`, `Export/SiteRenderer.php`):

- **Rendered in process, not fetched over HTTP.** The obvious implementation, `wp_remote_get()` per page, arrives as a logged-out visitor, which against a fail-closed handbook exports a folder of "no access" pages and reports success. The pages are rendered here instead, and every page is checked with `AccessController::can_view_post()` against the exporting user, so an export holds exactly what its author may read.
- **Passes with a time budget.** Rendering a large handbook does not fit in one request. A pass renders until `living_handbook_static_export_time_budget` is up, writes what it has into the archive, saves the job in a transient and answers with what is left; the browser asks for the next pass. A job belongs to the user who started it. The job id keeps its case: the transient name goes through a case-insensitive database column but a case-sensitive object cache, and lower-casing it makes a pass read a stale copy of its own state.
- **Paths, not URLs.** Every link between pages, and every image, is rewritten to a relative path inside the ZIP; a page's file sits at the slug path of its ancestors (`upkeep/review-pages.html`), which is the key the bundle export already uses. Links pointing anywhere else are left absolute on purpose.
- **A search without a server.** The index is a JavaScript file that sets a global, not JSON that a page would fetch: a document opened over `file://` is its own origin in most browsers and may not read the file beside it. The table of contents is built on the server from the heading anchors, rather than in the browser from the DOM. Next to the pages and their images the ZIP holds `index.html`, `README.txt` and, under `assets/`, the stylesheet `style.css`, the index `search-index.js`, the export's own script `site.js` (shipped from `assets/js/static-site.js`) with `site-strings.js` beside it, which defines the script's translated labels as `LH_STRINGS`, and `frontend.js`.

- **The look is chosen, not fixed.** There is no theme in a ZIP, so the export brings a small layout of its own and a set of colours picked on the screen: the site's own settings, a neutral light, a dark, or a print-oriented paper look. Each is a handful of `--lh-user-*` custom properties, which is all it takes because the plugin's stylesheet reads every colour through that chain; `living_handbook_static_export_themes` adds one. Every look carries the same print rules, so a page printed from the copy comes out as a document rather than a screenshot of a website.
- **Two things borrowed from the front end.** Mermaid diagrams are already in the exported markup as `<pre class="mermaid">`, so the export ships `mermaid.min.js` and its view script and draws them; the library is 3.5 MB and travels only when a diagram is actually present, loaded only on the pages that have one. And `frontend.js` is shipped as it is, with the page carrying the `living-handbook-page` class and the three overlay labels, so images and diagrams enlarge on a click exactly as on the site. Its search, filter and feedback parts find no elements here and do nothing, which is why a second implementation of the same behaviour was not written.

- **The page is the template, not a layout invented here.** The export does not assemble its own page but follows what the Site Editor says: it renders `living-handbook//single-handbook`, the edited copy when there is one, inside a real single-post query: half the blocks ask `is_singular()` or read the current post, and rendered outside a query they return nothing at all, silently. What cannot travel is cut from the markup first, rather than rendered and hidden: the theme's header and footer template parts, the feedback prompt, the comments and the typeahead search. The table of contents block is filled from the heading anchors during the export, so it works without scripting and on paper, and the people are dropped from the metadata footer.
- **The stylesheet is the site's own.** `wp_get_global_stylesheet()` carries the theme's palette, fonts, spacing and layout rules as `--wp--preset--*` properties, which is exactly what the plugin's stylesheet falls back to; the core block stylesheet comes with it, or a columns block is two stacked divs; and the block supports the style engine collected while rendering are gathered per page and written out once. The stylesheet takes fonts and images into the ZIP, under `assets/site/`, and only files that really lie inside wp-content, wp-includes or the WordPress folder: the path is resolved with `realpath()`, so a symbolic link cannot lead out of it, and the resolved file has to be a font or an image too. Anything else a `url()` names keeps its address. That is how the theme's fonts reach a reader with no network.

Left out, and each for a reason: comments and the "Was this helpful?" prompt need a server; the facet filters need queries; and names and avatars are dropped from the page footer because the file leaves the building.

## Exporting a handbook as Markdown

The third export under **Handbook → Export**, **Export as Markdown** (`Export\MarkdownExport`, decision E-0083), writes a handbook or an area as a ZIP that the Markdown import reads back. Permission and selection are the bundle's (`HandbookExport::target()` and `export_set()`): `edit_others_posts`, a handbook the person may read, and every page of it they may see. Pages with a password are left out; their subpages move up to the nearest exported ancestor.

Layout of the ZIP, under a folder named after the handbook's slug (for an area with the area's slug appended):

- `mkdocs.yml` with `site_name`, `docs_dir: docs` and `nav` in the handbook's order (`menu_order`, then title). Every string is written as a JSON string, which YAML reads as a double-quoted string.
- `docs/`: a page with subpages becomes a folder with `README.md`, every other page a `.md` file. The file name comes from the slug, ASCII, unique per folder; `assets`, `readme` and `index` are taken at the top.
- `docs/assets/`: every file from the uploads folder a page refers to as an image or a link, after the same path check as in the website export, with a unique name, because the import matches images by file name.
- `EXPORT.txt`: handbook, date, plugin version, how the import works, and what is missing: blocks left out per page, pages with a password, the GitHub source of synced pages. As `.txt`, so an import without `mkdocs.yml` does not create a page from it.

Each page starts with `# Title` and ends with the `## Transport-Metadaten` block: Seitentyp, Slug, Verantwortliche Rolle, Thema (only the first, the import knows one), Zielgruppe, Eltern-Seite (`oberste Ebene` at the top), Reihenfolge, Textauszug, Letzte Aktualisierung, Letzte Prüfung, Prüfintervall. The labels are German, as the import reads them. Empty lines are left out.

`Export\MarkdownWriter` writes the text from the HTML of the blocks (`render_block()` per block, not `the_content`, so no filter writes in between). It escapes text that Markdown would read as syntax, keeps `<details>` with `<summary>`, writes line breaks as a backslash, tables as pipe tables and code as a fence. A Mermaid block becomes a `mermaid` fence; the plugin's other blocks are left out and listed in `EXPORT.txt`. A link to another exported page becomes a relative `.md` path with its anchor, which the import's postprocessor points at the page again.

The roundtrip is pinned in a test: export, `read_zip()`, `MkDocsImport::build_specs()`, creating each page as the import screen does, postprocessing. Title, structure, order, slug, classification, review data, excerpt, text, image and link come back the same. What does not come back: the status (the import creates drafts), design and the plugin's blocks except Mermaid, learning paths, and files other than images, because the ZIP import takes only images into the media library.

## Importing a bundle

On the import screen, the **Bundle** tab takes a bundle exported from another site. Upload the ZIP and choose what should happen when a page already exists:

- **Skip** (the default): existing pages are left completely alone, only new ones are created.
- **Update**: title, content, structure and terms of a matching page are refreshed from the bundle.
- **Always create**: every page in the bundle becomes a new page, useful for cloning into a second handbook.

A page is recognised by the origin id it was exported with, then by its bundle key, then by slug within the target handbook. Two rules hold whatever you choose: a page carrying the **protected** flag (`_lh_import_protected`) is never overwritten, and **nothing is ever deleted**: a page that exists here but is missing from the bundle simply stays. Whatever the lookup finds is changed only when the importing person may edit it (`edit_post`; without a signed-in user, as under WP-CLI, the site itself imports): a page or learning path they may not edit is neither updated nor used as a parent or a lesson, whatever the run rule, and the report counts it. An import as ordinary pages (see below) has no handbook to look in; it finds its earlier pages among the site's pages by origin id and bundle key only, never by slug, which would match any page.

On update the site's own upkeep is preserved: the feedback counts and the review date, interval and reviewer stay as they are here, because they are local maintenance. A page created by the import does take those values from the bundle. The password of a page or learning path (`password`), on the other hand, is set on create and on update alike, so a protected page does not stand open on the target; an entry without the field, from an older bundle for instance, leaves a password set here as it is.

**Import into** decides where the pages land. By default the bundle goes into its own handbook, the one it was exported from, which is created here if it does not exist yet. Pick an existing handbook instead to put the pages there; that handbook keeps its own access configuration.

If the handbook does not exist yet it is created with visibility **members**, even when the bundle says public, so an import can never silently publish content; raise it by hand afterwards. An existing handbook keeps its own access configuration. The bundle carries no list of allowed people (see the export above), so nobody is matched and nobody is added; an older bundle that still carries such a list is reported, not applied. The one person a bundle names is the **reviewer** of each page, written as a login (or whatever `living_handbook_export_user_identifier` returns) and looked up here by e-mail address when the value is one, by login otherwise. It is applied only to pages the import creates; a reviewer with no account here is left empty without a report. After the pages are in, internal links between them are pointed at the new pages, and GitHub-sourced pages resume syncing from their repository.

The bundle's learning paths are imported too, their lessons pointed at the imported pages. A path is recognised like a page, by origin id, then bundle key, then slug, and the same run rules apply: skip, update or always create; a protected path is never overwritten. A lesson whose page the bundle does not contain is dropped, with a note in the report. If the learning paths module is off on this site, the paths are created anyway; they appear as soon as the module is switched on.

A short report at the top of the screen says how many pages were created, updated, skipped or protected and how many it was not allowed to touch, the same separately for the learning paths, and lists anything that could not be mapped.

The report also names the plugin version the bundle was made with (`plugin_version` in the manifest), as long as it looks like a version number. The imported files are then attached to the first imported page that shows them; a file already attached to another page keeps it. If the target handbook is not public, they lie in the protected folder as a result (see [protected-files.md](protected-files.md)).

Importing requires `edit_others_posts`, so an editor or above, and a target the person may read. **Import into** offers only such handbooks. A bundle whose own handbook already exists here and is not readable for the person is refused with "You are not allowed to import into this handbook." before any page is touched; an update would otherwise overwrite pages they may not see. A bundle is a file from another site, so its content is treated as external and cleaned on the way in, the same way the Markdown import and the GitHub sync are: scripts, event handlers and unsafe URLs are stripped. The cleaning runs block by block, so the block structure survives untouched. Media is cleaned as well, including SVG. That said, a bundle still brings in content someone else wrote, so it is worth reading before you publish it.

## The app handbook

The plugin comes with four handbooks: the **user handbook** in German and English, the documentation for the people who write and read a handbook, and the **technical documentation** in German and English, for whoever installs, styles or extends the plugin. All four are written as Living Handbooks, so they double as a first example of one. They **ship inside the plugin**, as Markdown under `docs/user/de/`, `docs/user/en/`, `docs/technical/de/` and `docs/technical/en/`, and the **App handbook** tab imports them from there. Shipping it means it always matches the installed version and no install depends on a repository staying reachable. The Markdown is authored in a public repository and copied into the plugin at build time, so it still has one editing source; it just travels with the release. Loading it again after a plugin update refreshes the pages.

**A reload updates and creates, it never deletes.** `GitSync::import_local_folder()` finds each file's page again by its stored source path (`_lh_source_path`) within the target handbook and rewrites it, provided it was loaded with **Connection to the source** (see below), and creates a page for a file it does not find. A page whose Markdown file was removed or renamed in a later version stays in the handbook, published, with its old content and its place in the tree. The result list of a load names such orphans, pages of the target handbook that no longer belong to the shipped handbook; they are not deleted, that is done by hand through the trash. A rename is the common case, because the slug and the source path follow the file name.

The pages are read from disk and their images sideloaded into the media library, the same way the GitHub folder import handles a repository. The content is stored as sanitised HTML, so the plugin's own blocks (the entry list, the feedback prompt, the badges) cannot travel this way; they are described in the text, not embedded. Mermaid diagrams do travel.

A fork, or anyone who would rather pull the latest state straight from GitHub, points the tab at a repository through the `living_handbook_app_handbook_url` filter (see [hooks.md](hooks.md)): any tree URL it returns is imported as a GitHub folder instead of the bundled copy. The filter defaults to an empty string, which means the bundled copy. Only where there is nothing to load, an empty filter on a source build without the folders under `docs/`, are the tab and the setup hint hidden, so there is never a button leading nowhere.

The app handbook is **published straight away** rather than left as a draft: it is curated content, and its front-end visibility is governed by the handbook it lands in. Put it in a handbook set to "members" and only logged-in people see it; it becomes public only if you set that handbook to public. A manual GitHub import, by contrast, stays a draft, so you can review the pages before publishing.

**Which handbook** picks one of the four. Loading several into handbooks of their own is fine and is what a site that wants both would do.

**Load into** picks the handbook the pages belong to. Create one first, for example "App handbook", and set who may read it there.

**Connection to the source** is on by default: the pages are locked in the editor and a later load refreshes them, so they follow the plugin. Switch it off to load them as ordinary handbook pages, free to edit, which is what you want when you take a shipped handbook as the template for your own. The price is stated on the screen and holds: an untied copy is never refreshed again, so it ages with every plugin update. Load into the same handbook again and the import finds its pages again by source path, but leaves them untouched and says so in the result list, so your own changes to the template survive. Whoever wants the fresh edition of such a page moves it to the trash first and then loads again.

## Transport metadata

A page can carry a metadata block that maps it onto the handbook's taxonomies and freshness fields. The block is detected by the German heading marker `## Transport-Metadaten`; everything above it is the page body, and the first `# H1` becomes the title. An English heading is not recognised as the marker. A marker inside a fenced code block is treated as an example and skipped, and when the marker appears more than once outside code, the last occurrence wins — so a page can quote the marker in its documentation without being cut in half.

The fields (German labels, one per list item):

```
## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Thema: Applikation
* Zielgruppe: Alle Mitglieder, Technik
* Eltern-Seite: Übersicht
* Reihenfolge: 3
* Textauszug: Kurz erklärt.
* Letzte Prüfung: 2026-07-08
* Prüfintervall: 90 Tage
```

Notes:

- **The topic field accepts `Thema`, `Bereich` or `Themengebiet`.** The taxonomy is called "Topics" in the interface. `Thema` matches that label and is preferred for new drafts; `Bereich` and the older `Themengebiet` keep existing drafts working, so you do not have to touch a corpus written before the rename. If a draft carries more than one, `Thema` wins, then `Bereich`.
- `Zielgruppe` is a comma-separated list.
- `Eltern-Seite` is matched by title after all pages of the import exist, so the parent may appear later in the same import. The page found becomes the parent only when the importing person may edit it (`edit_post`), the same rule the import screen applies to a parent it is handed; without a signed-in user, as in the scheduled sync or WP-CLI, it is taken as found.
- Bracketed placeholders such as `[Rolle]` or `[JJJJ-MM-TT]` are treated as empty; `[ANNAHME: FAQ]` resolves to its value (`FAQ`).
- The handbook a page belongs to can also be set in the transport block, with `Handbuch`. It overrides the target handbook chosen on the import screen.

## Structured import from MkDocs

If the ZIP contains a `mkdocs.yml`, its `nav` section is the source of truth for structure: page titles, order and parent-child nesting follow the nav, and a section's `index.md` becomes that section's page. This preserves a documentation site's shape on import. It needs the symfony/yaml library, which ships in `vendor/`.

## GitHub sync (keeping a page in sync)

Each page has a source, set in the **Source** box in the editor:

- **Maintained in WordPress** (default): edited normally in WordPress.
- **Synced from GitHub**: the page carries a Markdown source URL. On save, via the **Sync now** button, and on a schedule, the page is pulled from the repository and re-rendered to HTML (filtered through `wp_kses` before it is stored). Its content editor is locked so it cannot be edited by hand. The page list shows the source in its own column, and the "GitHub source note" block marks the public page as maintained on GitHub.

### Converting a synced page to a WordPress page

A page synced from GitHub can be detached and kept in WordPress. In the **Source** box, switch it from **Synced from GitHub** to **Maintained in WordPress** and save. The current content stays exactly as it is, the background sync stops touching the page, the content editor is unlocked so you can edit it by hand, and the "GitHub source note" no longer shows on the public page. Nothing is fetched again, so the page will not revert on the next sync. The move is one-way in practice: to go back, switch the source to GitHub again and re-enter the Markdown source URL, and the next sync overwrites the page with the repository version.

### How the sync learns of changes

There is no webhook. WordPress pulls: on save, on demand (Sync now), and on a background schedule (WordPress cron, which fires on site visits). Set the schedule under **Handbook → Settings**: off, hourly, twice daily, daily, or weekly (the default on a new install). "Off" still syncs on save and via Sync now.

A large handbook is synced in batches rather than all at once, so a single request never has to fetch every page.

At the end of each complete pass, `HandbookVersion::refresh_tags()` asks, for every handbook whose edition comes from the GitHub tag, for the tags of the chosen repository (`GET /repos/{owner}/{repo}/tags?per_page=100`, one request per handbook). If its pages follow a version tag themselves, the tag is in their raw address and nothing is asked. After the pages, not before: so the content of a new tag starts its edition at 0 (E-0084).

The pull on save runs inside the save request: saving a GitHub-synced page fetches the source and re-renders it before the save returns, so the new content is visible right away. The trade-off is that the save waits for the network round-trip to GitHub. For normal page sizes this is not noticeable; only if a source were unusually large or the network very slow could a save feel slow. Moving this pull into a background event (so the save returns at once and the content updates a moment later) is noted as a possible future change; it is not done today because it would break the "pulled when you save" behaviour that makes the editor show the fetched content immediately.

### When a sync fails

A failed pull is recorded on the page and flagged. An admin notice on the handbook screens tells you how many pages could not be synced; open a page and look at "Last sync" in the Source box for the reason (a rate limit, an HTTP error, an unreachable host). The page keeps its previous content, so a failed sync never empties a page.

### Public vs. private repositories

Live sync fetches the raw Markdown over HTTP, so it works for public repositories. For a private repository, import from a ZIP export instead (the MkDocs structure is preserved) rather than storing an access token.

For safety the source URL must point at an allowed host (`raw.githubusercontent.com` by default) over https, so nobody can aim the server at an internal address. Extend the list with the `living_handbook_sync_allowed_hosts` filter, see [hooks.md](hooks.md).

## What the plugin sends where

Only what you ask it to fetch, from GitHub. A `github.com` URL is only parsed, never fetched: the import and the sync read files from `raw.githubusercontent.com`, a folder listing from the Git trees API at `api.github.com`, the tags of a repository for a handbook whose version follows the tag from the same API, and for a folder of more than about twenty files the repository archive, whose address `api.github.com` returns and which is downloaded from `codeload.github.com`. They read only from addresses you enter yourself, and they send nothing but those addresses and a User-Agent header. If you use neither feature, none of this happens.

The other external request does not come from the plugin's code but from WordPress: the page footer (`living-handbook/pagemeta`) shows each person with `get_avatar()`, which loads the image from Gravatar in the reader's browser. Switching off **Show Avatars** under Settings, Discussion, or *Show people (avatar and name)* in the block, removes it. The readme lists both services under *External services*, as the plugin directory requires.

The unauthenticated GitHub API allows about 60 requests per hour. Importing a large folder can hit that; the import reports it clearly and you can retry later.

## Uninstalling

By default, deleting the plugin keeps your content and removes only the plugin's own settings and caches. On **Handbook → Settings** you can opt in to remove everything the plugin created, including handbook pages, handbooks, their metadata, the seeded taxonomy terms and any of the plugin's templates you edited in the Site Editor. The option is off by default on purpose: an accidental delete should not cost you the handbook. Even with it, the files in the media library stay, including the ones uploaded to handbook pages or sideloaded by an import; whoever wants them gone deletes them in the media library. Protected files first move back to the normal uploads folder; without the option they stay locked (see [protected-files.md](protected-files.md)). What the plugin noted on those files so that a later import reuses an unchanged image (`_lh_import_file`, `_lh_import_hash`) is removed with it.

## Limits

- A folder import covers subfolders, but at most 200 files in one go. If the limit is hit, import the remaining subfolders separately.
- A ZIP is read within limits (at most 2000 entries, 5 MB per file, 100 MB uncompressed in total), so a prepared archive cannot exhaust the server's memory. A Markdown file, an image or an `mkdocs.yml` above 5 MB is left out and named in the import notes, or in the error when no Markdown is left. The uncompressed total is adjustable in code through the `living_handbook_zip_max_bytes` filter (see [hooks.md](hooks.md)); the real ceiling stays the server's PHP upload and memory limits.
- A handbook bundle has its own, higher limits (at most 20000 entries, 1 GB uncompressed), because it carries the PDFs and videos of its pages. Both are adjustable through `living_handbook_bundle_max_entries` and `living_handbook_bundle_max_bytes`. A single file is read only up to the smaller of 128 MB and a quarter of the free memory (`living_handbook_bundle_max_file_bytes`); the report names a larger one.
- The transport marker and its field labels are German.
- Synced content is stored as rendered HTML, not editable blocks, because a cron job has no browser to convert HTML into blocks.
- MkDocs admonitions (`!!! note`, `??? tip`) are converted to a blockquote led by the title, so the note stays set apart instead of collapsing into stray text. Other MkDocs-specific syntax still degrades to plain text: pymdownx tabs, for one, are not part of GitHub Flavored Markdown, so the converter does not understand them.
- Living Handbook is built for single-site installations; on a multisite network, import per site.

## Moving existing WordPress pages into a handbook

Select the pages in **Pages**, pick **Move into a handbook…** from the bulk actions, and choose the handbook in the dropdown that appears beside it; only handbooks you may read are offered and accepted. Three things happen that would not happen if you only changed the post type by hand:

- The pages are assigned to the handbook you picked. Without a handbook a handbook page is not moved but gone: access is fail-closed, and a page with no handbook is not readable by anyone on the front end.
- Their old addresses keep working. `/about/` becomes `/handbook/about/`, and WordPress does not redirect on a type change, so the old path is remembered and answered with a permanent redirect. Every existing link, bookmark and search result survives the move.
- Subpages come along, always. This is not offered as a choice: a child left behind keeps a parent that is no longer a page, and its own address is built from that chain, so it would end up unreachable.

What the moved pages do not bring is review data. They arrive as **Not reviewed** and stay there until someone sets a review date and an interval, which is the honest state for a page that has just become documentation.

The way back is the bundle export, below.

## Importing a bundle as ordinary pages

The bundle import can create ordinary WordPress pages instead of handbook pages: tick **Ordinary WordPress pages instead of handbook pages** on the import screen. The text, the images, the diagrams and the page structure come along. Learning paths are not imported this way.

The run rules hold here as well: a later run finds the pages it created by origin id and bundle key, never by slug, and skips or updates them instead of adding new drafts.

Everything the handbook adds around a page does not, and it is worth naming: no handbook, so no access rule, no navigation, no table of contents, no badges, no feedback and no source note. Those live in the handbook template and in blocks that check their context; on an ordinary page they render nothing.

The pages are created as **drafts**, whatever status the bundle carries. A bundle exported from an internal handbook would otherwise be published by the act of importing it, and that is the one mistake in this direction that cannot be taken back.

## Transport-Metadaten
* Seitentyp: Tool overview
* Zielgruppe: Tech
* Reihenfolge: 9
* Letzte Aktualisierung: 2026-11-10
