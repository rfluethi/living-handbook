# Import and GitHub sync

How to get Markdown into the handbook, and how a page can stay synced from a GitHub repository.

## The import screen

Under **Handbook → Import**, the screen works in two steps. First pick a **target handbook** (and, optionally, a page title). Then choose one **source** in the tab switcher and import it. Only the chosen source is shown, so a pasted draft is never ignored because a URL is still in another field. A short "How the import works" section at the top explains the details on demand.

The three sources are:

1. **Paste text**: paste a Markdown draft, then **Import Markdown**.
2. **ZIP file**: upload a ZIP of `.md` files, then **Import ZIP**. The ZIP may be a flat set of files or a structured MkDocs project (see below).
3. **GitHub**: enter a GitHub URL, then **Import from GitHub**. Both a single file and a whole folder work:
   - a file, either a `raw.githubusercontent.com` URL or a `github.com/.../blob/...` URL (the blob URL is normalised to raw automatically);
   - a folder, a `github.com/.../tree/...` URL. Every `.md` file in that folder (including `README.md`) is imported through the GitHub contents API. Subfolders are not descended into.

Pages that land without a handbook are invisible on the front end, because access is fail-closed.

**How the conversion works:** league/commonmark renders the Markdown to HTML, and WordPress's own paste conversion turns that HTML into editable blocks. A ```` ```mermaid ```` code fence becomes a live-rendered Mermaid block, collapsible `<details>` sections become details blocks, and images referenced from an `assets` folder are sideloaded into the media library. Once all pages exist, a shared post-processor applies the transport metadata and resolves parents and internal `.md` links, both the link target and the visible link text.

## Importing the same source twice

Re-importing the same source **updates the existing pages instead of creating duplicates**. How a page is recognised depends on the import:

- **ZIP and MkDocs imports** are matched by the stored source path. If a page has no source path, it is matched by slug within the chosen handbook.
- **GitHub imports** are matched by the Markdown source URL.
- **A pasted draft always creates a new page.** It carries neither a source path nor an explicit slug, and silently overwriting an existing page just because a title matched would be worse than a duplicate.

When a page is updated this way it keeps its **slug and its publication status**, so URLs stay stable and a published page does not silently drop back to draft. Title, content and parent are refreshed from the source.

## Transport metadata

A page can carry a metadata block that maps it onto the handbook's taxonomies and freshness fields. The block is detected by the German heading marker `## Transport-Metadaten`; everything above it is the page body, and the first `# H1` becomes the title. An English heading is not recognised as the marker.

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

- Folder import reads one folder, not its subfolders. Import each folder, or use a structured ZIP for a whole tree.
- A ZIP is read within limits (at most 2000 entries, 5 MB per file, 50 MB uncompressed in total), so a prepared archive cannot exhaust the server's memory.
- The transport marker and its field labels are German.
- Synced content is stored as rendered HTML, not editable blocks, because a cron job has no browser to convert HTML into blocks.
- MkDocs-specific syntax degrades to plain text: admonitions (`!!! note`) and pymdownx tabs are not part of GitHub Flavored Markdown, so the converter does not understand them.
- Living Handbook is built for single-site installations; on a multisite network, import per site.
