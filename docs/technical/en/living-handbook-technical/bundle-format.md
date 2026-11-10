# Bundle format specification

This page defines the format and the rules of the export and import bundle, so that both sides are mechanical rather than a matter of interpretation. It is written for developers who work against the bundle format. How to use export and import is described in the user handbook under [Move a handbook](../../../user/en/living-handbook/content/move-a-handbook.md).

## The bundle

A ZIP file with `manifest.json` in the root and a folder `media/` holding every referenced file. Self-contained and therefore usable offline; no access to the source site is needed.

## manifest.json, schema version 1

```json
{
  "format": "living-handbook-bundle",
  "version": 1,
  "exported": { "site": "https://source.example", "plugin_version": "0.28.0", "date": "2026-07-21T12:00:00+00:00" },
  "scope": "handbook",
  "handbook": {
    "slug": "operations",
    "name": "Operations handbook",
    "visibility": "members",
    "roles": ["editor"],
    "users": ["person@example.com"],
    "version": { "edition": "2.1", "count": 14, "version": "2.1.14" }
  },
  "pages": [
    {
      "key": "getting-started/emergency",
      "origin_id": "https://source.example#123",
      "parent_key": "getting-started",
      "order": 2,
      "title": "Emergency",
      "slug": "emergency",
      "status": "publish",
      "source": "github",
      "markdown_url": "https://raw.githubusercontent.com/org/repo/main/emergency.md",
      "content": "<!-- block markup snapshot -->",
      "terms": { "handbook_type": ["howto"], "handbook_topic": ["safety"], "handbook_role": ["it"], "handbook_audience": ["all"] },
      "meta": { "last_reviewed": "2026-06-01", "review_interval": 180, "reviewer": "person@example.com", "toc_depth": 0, "ai_exclude": false },
      "media": ["media/a1b2-plan.png"]
    }
  ],
  "paths": [
    {
      "key": "path:onboarding",
      "origin_id": "https://source.example#456",
      "title": "Onboarding",
      "slug": "onboarding",
      "status": "publish",
      "content": "<!-- block markup snapshot -->",
      "excerpt": "The first week, in order.",
      "lessons": ["getting-started", "getting-started/emergency"],
      "media": []
    }
  ],
  "media": [
    { "file": "media/a1b2-plan.png", "original_url": "https://source.example/wp-content/uploads/2026/06/plan.png", "alt": "Escape plan" }
  ]
}
```

## Identity and matching

Every page carries a `key` (a stable hierarchy path built from slugs, the identity inside the bundle) and an `origin_id` (source site URL plus the original post ID). On import the `origin_id` is stored as the meta field `_lh_origin_id`, which is how a re-import finds the same page again.

Matching happens only within the target handbook: primarily by `origin_id`, secondarily by `key`, and for GitHub pages additionally by `markdown_url`. Terms are looked up by slug (falling back to name) and created if missing; the reviewer is looked up by email, falling back to the login.

## Learning paths

`paths` lists the handbook's learning paths. A path is identified like a page: its `key` is `path:` plus its slug, with `-<ID>` appended when two paths would share one, and its `origin_id` is the source site URL plus the original post ID. `lessons` is the ordered list of page keys from `pages`. On import each key is resolved to the page created or found in the same run; a key that resolves to nothing is dropped and named in the report.

A path follows the same matching, the same run rules and the same protection switch as a page, and an existing path the importing person may not edit is left alone. The result screen counts paths separately from pages: created, updated, skipped, protected and refused. The export leaves out paths the exporting person may not read, and an area export carries only the paths with at least one lesson in that area. The schema version stays 1: an older plugin ignores `paths`.

## Version of the handbook

`handbook.version` carries edition and count (E-0084); `version` is both together, for reading. If the import creates a new handbook, it takes edition and count over after the pages are created; creating published pages would otherwise already have counted. An existing target handbook keeps its version and counts the import as a change. A bundle without the key, from a version before 0.81.0, starts a new handbook at 1.0. The schema version stays 1.

## Password-protected pages

A page or path protected by a password carries it in `password`; the key is absent for an unprotected one, so such a bundle looks exactly like one from an older version. The import sets the password on a new post and on an updated one alike. An entry without the key leaves the post data alone, so an update keeps a password set on the target site.

The protected text travels in the bundle in plain text either way. The password does not make the file more confidential than it already is; it only keeps the protection on the site the bundle goes to. Handle a bundle with protected pages like the pages themselves.

## What travels

The handbook configuration (visibility, roles, users as email or login, best effort, and the version of the handbook), every page with a content snapshot, hierarchy, order, slug and status, the four vocabulary terms, the metadata (review date, interval, reviewer, heading depth, AI exclusion), the per-page source plus the Markdown URL for GitHub pages, the password of protected pages, the learning paths with their lesson order, and the media.

GitHub pages carry both the snapshot (which works offline) and the source URL (which lets the target site resume syncing).

## What does not travel

Site settings (sync schedule, site-wide custom CSS, ZIP limit); the feedback counters, which belong to the readers of the target site; sync status and error flags; the editor lock, which the target site derives from the source; and all IDs, which are assigned anew. Comments are deliberately out of scope in version 1.

## Conflict handling

One rule per run, chosen at import time:

- **Skip (default):** existing pages are left untouched, only new ones are created. Never overwrites.
- **Update:** on a match, title, content (for WordPress pages), slug, order, parent and term assignments are replaced from the bundle.
- **Create as new:** always creates a new page, even on a match (cloning).

In addition there is a per-page protection switch (meta `_lh_import_protected`) which is never overwritten, whatever the run rule says.

An update never touches local operational data: the feedback counters and the review date, interval and reviewer, all of which the target site maintains itself. On first creation, by contrast, the review metadata is taken from the bundle. Nothing is ever deleted: pages that are missing from the bundle but exist on the target site stay.

## Safe import defaults

Visibility on creation defaults to "members" even when the bundle says "public", and the report flags this, so that nobody publishes something by accident. When an existing handbook is updated, its access configuration is left as it is. Media go through the existing sideload with duplicate protection. Export and import are restricted to authorised users (at least `edit_others_posts`).

## Sanitisation: block by block

A bundle is a file from another site, so its content is treated like any other external source, the same way as the Markdown import and the GitHub sync. It cannot go through kses in one piece, because that destroys the block delimiters (`<!-- wp:... -->`) and the pages break. `HtmlSanitizer::clean_blocks()` therefore parses the blocks first, cleans only the HTML inside each block and serializes them again; the delimiters and the block attributes survive. This holds for pages and learning paths alike.

Block attributes are not filtered: a dynamic block renders from them, and the plugin's own render callbacks escape them, while a core or third-party block relies on its own escaping. Media go through the existing sideload including SVG cleaning, and importing needs the content-manager capability (`edit_others_posts`). Anyone importing a bundle from a third party still has to trust that source for what the attributes of its blocks carry.

## Not yet specified

Three questions are open and deliberately left out of version 1:

- whether the export covers all statuses (including drafts) or only published pages,
- how the target handbook is chosen on import (assign an existing one or create a new one),
- whether the export sits on the handbook list or on a page of its own.

## Transport-Metadaten
* Seitentyp: Tool overview
* Zielgruppe: Tech
* Reihenfolge: 13
* Letzte Aktualisierung: 2026-11-10
