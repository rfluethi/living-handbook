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

4. **Bundle**: upload a bundle exported from another site running the plugin (see below). Needs the content-manager role.
5. **App handbook**: pull the app's own handbook from GitHub in one click (see below). Needs edit rights.

Pages that land without a handbook are invisible on the front end, because access is fail-closed.

**How the conversion works:** league/commonmark renders the Markdown to HTML, and WordPress's own paste conversion turns that HTML into editable blocks. A ```` ```mermaid ```` code fence becomes a live-rendered Mermaid block, collapsible `<details>` sections become details blocks, and images referenced from an `assets` folder are sideloaded into the media library. Once all pages exist, a shared post-processor applies the transport metadata and resolves parents and internal `.md` links, both the link target and the visible link text.

### Importing a folder with its subfolders

A folder import reads the whole repository tree in **one** request to the Git trees API, not one request per folder. Unauthenticated GitHub allows 60 requests an hour, and a walk that spends one on every folder runs out on a documentation repository of any size.

The folder structure becomes the page hierarchy, and a folder becomes a page:

- A folder holding an **`index.md`** (or, failing that, a **`README.md`**) is represented by that file: it becomes the folder's page, and everything else in the folder hangs under it.
- A folder holding **neither** gets a page made from its name, carrying the area entries block, so it lists what is inside it. A level that exists in the repository but not in the handbook would leave a hole in the navigation.
- The **`README.md` of the folder you point at** stays an ordinary page. There is no folder page above it to be consumed by.
- Levels that exist only as path segments are filled in, so `docs/one/two/three/page.md` produces three levels and not one.

At most **200 files** are imported in one go. If that limit is reached, or if the repository is too large for GitHub to return its tree in one piece, the result list says so; import the remaining subfolders separately.

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

Under **Handbook → Export**, a content manager can **export a handbook as a bundle**: a single ZIP with a `manifest.json` and a `media/` folder. Pick the **handbook** first; the second field then lists that handbook's **areas** (an area is a top-level page, and it exports together with its subpages). Leave it on *the whole handbook* to export everything. Then **Export bundle**, and the ZIP downloads. It is self-contained, so it can be moved to another site running the plugin without reaching back to this one. An area bundle still carries the handbook's configuration, so the target knows where the pages belong.

The bundle carries the handbook's configuration (visibility and allowed roles), every page as a block-markup snapshot with its place in the hierarchy, the four vocabularies, the freshness metadata, and the referenced media. It deliberately does **not** carry the list of individually allowed people: those are e-mail addresses, a bundle is a file that gets downloaded and passed on, and the target site has a different set of users anyway. If a handbook is restricted to named people, set them again after importing. A GitHub-sourced page keeps its source URL, so on the target site it resumes syncing from the same repository. Local, site-specific data is deliberately left out: the feedback counts and the sync status belong to each site.

## Importing a bundle

On the import screen, the **Bundle** tab takes a bundle exported from another site. Upload the ZIP and choose what should happen when a page already exists:

- **Skip** (the default): existing pages are left completely alone, only new ones are created.
- **Update**: title, content, structure and terms of a matching page are refreshed from the bundle.
- **Always create**: every page in the bundle becomes a new page, useful for cloning into a second handbook.

A page is recognised by the origin id it was exported with, then by its bundle key, then by slug within the target handbook. Two rules hold whatever you choose: a page carrying the **protected** flag (`_lh_import_protected`) is never overwritten, and **nothing is ever deleted** — a page that exists here but is missing from the bundle simply stays.

On update the site's own upkeep is preserved: the feedback counts and the review date, interval and reviewer stay as they are here, because they are local maintenance. A page created by the import does take those values from the bundle.

**Import into** decides where the pages land. By default the bundle goes into its own handbook, the one it was exported from, which is created here if it does not exist yet. Pick an existing handbook instead to put the pages there; that handbook keeps its own access configuration.

If the handbook does not exist yet it is created with visibility **members**, even when the bundle says public, so an import can never silently publish content; raise it by hand afterwards. An existing handbook keeps its own access configuration. Users are matched by e-mail, then login; an allowed user with no account here is dropped and reported. After the pages are in, internal links between them are pointed at the new pages, and GitHub-sourced pages resume syncing from their repository.

A short report at the top of the screen says how many pages were created, updated, skipped or protected, and lists anything that could not be mapped.

Importing needs the content-manager role. A bundle is a file from another site, so its content is treated as external and cleaned on the way in, the same way the Markdown import and the GitHub sync are: scripts, event handlers and unsafe URLs are stripped. The cleaning runs block by block, so the block structure survives untouched. Media is cleaned as well, including SVG. That said, a bundle still brings in content someone else wrote, so it is worth reading before you publish it.

## The app handbook

The plugin comes with a handbook of its own: the documentation of the app, written as a Living Handbook so it doubles as a first example of one. It **ships inside the plugin**, as Markdown under `handbuch/`, and the **App handbook** tab imports it from there. Shipping it means it always matches the installed version and no install depends on a repository staying reachable. The Markdown is authored in a public repository and copied into the plugin at build time, so it still has one editing source; it just travels with the release. Loading it again after a plugin update refreshes the pages.

The pages are read from disk and their images sideloaded into the media library, the same way the GitHub folder import handles a repository. The content is stored as sanitised HTML, so the plugin's own blocks (the entry list, the feedback prompt, the badges) cannot travel this way; they are described in the text, not embedded. Mermaid diagrams do travel.

A fork, or anyone who would rather pull the latest state straight from GitHub, points the tab at a repository through the `living_handbook_app_handbook_url` filter (see [hooks.md](hooks.md)): any tree URL it returns is imported as a GitHub folder instead of the bundled copy. The default is the bundled copy.

The app handbook is **published straight away** rather than left as a draft: it is curated content, and its front-end visibility is governed by the handbook it lands in. Put it in a handbook set to "members" and only logged-in people see it; it becomes public only if you set that handbook to public. A manual GitHub import, by contrast, stays a draft, so you can review the pages before publishing.

**Load into** picks the handbook the pages belong to. Create one first, for example "App handbook", and set who may read it there.

The URL defaults to this plugin's own documentation repository. A fork with its own documentation points the tab elsewhere through the `living_handbook_app_handbook_url` filter (see [hooks](hooks.md)); returning an empty string hides the tab and the setup hint, so there is never a button leading nowhere.

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
- `Eltern-Seite` is matched by title after all pages of the import exist, so the parent may appear later in the same import.
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

The pull on save runs inside the save request: saving a GitHub-synced page fetches the source and re-renders it before the save returns, so the new content is visible right away. The trade-off is that the save waits for the network round-trip to GitHub. For normal page sizes this is not noticeable; only if a source were unusually large or the network very slow could a save feel slow. Moving this pull into a background event (so the save returns at once and the content updates a moment later) is noted as a possible future change; it is not done today because it would break the "pulled when you save" behaviour that makes the editor show the fetched content immediately.

### When a sync fails

A failed pull is recorded on the page and flagged. An admin notice on the handbook screens tells you how many pages could not be synced; open a page and look at "Last sync" in the Source box for the reason (a rate limit, an HTTP error, an unreachable host). The page keeps its previous content, so a failed sync never empties a page.

### Public vs. private repositories

Live sync fetches the raw Markdown over HTTP, so it works for public repositories. For a private repository, import from a ZIP export instead (the MkDocs structure is preserved) rather than storing an access token.

For safety the source URL must point at an allowed host (`raw.githubusercontent.com` by default) over https, so nobody can aim the server at an internal address. Extend the list with the `living_handbook_sync_allowed_hosts` filter, see [hooks.md](hooks.md).

## What the plugin sends where

Only what you ask it to fetch. The import and the sync read from `github.com`, `raw.githubusercontent.com` and the GitHub contents API at `api.github.com`, and only from the addresses you enter yourself. Nothing is sent anywhere, and if you use neither feature the plugin makes no external calls at all.

The unauthenticated GitHub API allows about 60 requests per hour. Importing a large folder can hit that; the import reports it clearly and you can retry later.

## Uninstalling

By default, deleting the plugin keeps your content and removes only the plugin's own settings and caches. On **Handbook → Settings** you can opt in to remove everything the plugin created, including handbook pages, handbooks, their metadata, the seeded vocabularies and any of the plugin's templates you edited in the Site Editor. The option is off by default on purpose: an accidental delete should not cost you the handbook.

## Limits

- A folder import covers subfolders, but at most 200 files in one go. If the limit is hit, import the remaining subfolders separately.
- A ZIP is read within limits (at most 2000 entries, 5 MB per file, 100 MB uncompressed in total), so a prepared archive cannot exhaust the server's memory. The uncompressed total is adjustable in code through the `living_handbook_zip_max_bytes` filter (see [hooks.md](hooks.md)); the real ceiling stays the server's PHP upload and memory limits.
- The transport marker and its field labels are German.
- Synced content is stored as rendered HTML, not editable blocks, because a cron job has no browser to convert HTML into blocks.
- MkDocs admonitions (`!!! note`, `??? tip`) are converted to a blockquote led by the title, so the note stays set apart instead of collapsing into stray text. Other MkDocs-specific syntax still degrades to plain text: pymdownx tabs, for one, are not part of GitHub Flavored Markdown, so the converter does not understand them.
- Living Handbook is built for single-site installations; on a multisite network, import per site.
