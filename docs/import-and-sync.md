# Import and GitHub sync

How to get Markdown into the handbook, and how a page can stay synced from a
GitHub repository.

## The import screen

Under the Handbook menu, the import screen accepts three kinds of input:

1. **Paste** a Markdown draft into the text area.
2. **Upload a ZIP** of `.md` files. The ZIP may be a flat set of files or a
   structured MkDocs project (see below).
3. **Enter a GitHub URL** in the field below the ZIP upload. Both a single file
   and a whole folder are supported:
   - a file, either a `raw.githubusercontent.com` URL or a `github.com/.../blob/...`
     URL (the blob URL is normalised to raw automatically);
   - a folder, a `github.com/.../tree/...` URL. Every `.md` file in that folder
     (including `README.md`) is imported via the GitHub contents API. Subfolders
     are not descended into.

Conversion path: league/commonmark renders the Markdown to HTML, and WordPress's
own paste conversion turns that HTML into editable blocks. `mermaid` code fences
become a live-rendered Mermaid block, collapsible `<details>` sections become
details blocks, and images referenced from an `assets` folder are sideloaded
into the media library. After all pages exist, a shared post-processor applies
the transport metadata and resolves parents and internal `.md` links (both the
link target and the visible link text).

## Transport metadata

A page can carry a metadata block that maps it onto the handbook's taxonomies
and freshness fields. The block is detected by the German heading marker
`## Transport-Metadaten`; everything above it is the page body, and the first
`# H1` becomes the title. An English heading is not recognised as the marker.

The fields (German labels, one per list item):

```
## Transport-Metadaten
* Seitentyp: Anleitung
* Verantwortliche Rolle: Handbuch-Redaktion
* Themengebiet: Applikation
* Zielgruppe: Alle Mitglieder, Technik
* Eltern-Seite: Übersicht
* Reihenfolge: 3
* Textauszug: Kurz erklärt.
* Letzte Prüfung: 2026-07-08
* Prüfintervall: 90 Tage
```

Notes:

- `Zielgruppe` is a comma-separated list.
- `Eltern-Seite` is matched by title after all pages of the import exist, so the
  parent may appear later in the same import.
- Bracketed placeholders such as `[Rolle]` or `[JJJJ-MM-TT]` are treated as empty;
  `[ANNAHME: FAQ]` resolves to its value (`FAQ`).
- The handbook a page belongs to can also be set in the transport block, so an
  import lands in the right handbook.

## Structured import from MkDocs

If the ZIP contains a `mkdocs.yml`, its `nav` section is the source of truth for
structure: page titles, order and parent-child nesting follow the nav, and a
section's `index.md` becomes that section's page. This preserves a documentation
site's shape on import. Requires the symfony/yaml library, which ships in
`vendor/`.

## GitHub sync (keeping a page in sync)

Each page has a source, set in the **Source** box in the editor:

- **Maintained in WordPress** (default): edited normally in WordPress.
- **Synced from GitHub**: the page carries a Markdown source URL. On save, via
  the **Sync now** button, and on a schedule, the page is pulled from the
  repository, re-rendered to HTML (filtered through `wp_kses`), and its content
  editor is locked so it cannot be edited by hand. The page overview shows the
  source in a column, and a placeable block marks the public page as maintained
  on GitHub.

### How the sync learns of changes

There is no webhook. WordPress pulls: on save, on demand (Sync now), and on a
background schedule (WordPress cron, which fires on site visits). The schedule is
set under Handbook, Sync settings: off, hourly, twice daily, daily (default), or
weekly. Off still syncs on save and via Sync now.

### Public vs. private repositories

Live sync fetches the raw Markdown over HTTP, so it works for public
repositories. For a private repository, import from a ZIP export instead (the
MkDocs structure is preserved), rather than storing an access token.

## Limits

- Folder import reads one folder, not its subfolders; import each folder, or use
  a structured ZIP for a whole tree.
- The transport marker and its field labels are German.
- Synced content is stored as rendered HTML, not editable blocks (a cron job has
  no browser to convert HTML into blocks).
