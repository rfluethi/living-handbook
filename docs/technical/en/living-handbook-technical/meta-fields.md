# Meta fields

Every custom field the plugin reads or writes, on handbook pages, on learning paths, on media and on handbooks. Read off the code in `src/`; when the two disagree, the code is right and this page is the bug.

Two kinds, told apart by their name (see [architecture.md](architecture.md)):

- **Editorial** fields carry the prefix `living_handbook_`. A person fills them in, and the registered ones are readable over the REST API.
- **Bookkeeping** fields carry the prefix `_lh_`. The plugin keeps them about a page rather than for it. The leading underscore makes them protected: WordPress hides them from the Custom Fields box, where a wrong edit would break the sync without a word.

"REST" below means the field is registered with `show_in_rest` and appears under `meta` in `/wp/v2/handbook`. Every registered field is written only by someone who may edit that object: the auth callback asks `edit_post` for the page, or `manage_categories` and `edit_term` for a handbook; the plugin denies `edit_term` for a handbook the person may not read. A field marked "not registered" is plain post meta that only the plugin's own code touches.

## Handbook pages (`handbook`)

### Editorial fields

| Key | Type | REST | Written by | What it holds |
| --- | --- | --- | --- | --- |
| `living_handbook_last_updated` | string, `YYYY-MM-DD` | yes | `Metadata::set_updated()` on every save, `GitSync` after every successful sync | The date shown as "Last updated". Not entered by hand. |
| `living_handbook_last_reviewed` | string, `YYYY-MM-DD` | yes | The **Handbook maintenance** box, Quick Edit, Bulk Edit; an import from the transport line `Letzte Prüfung`; a bundle import on pages it creates | The last review date. Anything that is not a date is stored as empty. |
| `living_handbook_review_interval` | integer, days | yes | Same as the review date; transport line `Prüfintervall` | How long a review holds. 0 or empty means no interval, and the page counts as not reviewed. |
| `living_handbook_reviewer` | integer, user ID | yes | The maintenance box, Quick Edit, Bulk Edit; a bundle import on pages it creates, resolved from a login or e-mail address | Who reviewed the page last. |
| `living_handbook_toc_depth` | integer, 0 to 6 | yes | The maintenance box; a bundle import on pages it creates | Overrides the table-of-contents depth of this page. 0 uses the block's setting; a value above 6 is stored as 0. |
| `living_handbook_ai_exclude` | boolean | yes, default `false` | The maintenance box; a bundle import on pages it creates | Asks future AI readers to leave the page out. The plugin itself only stores and exposes it. |

The derived status of a page (reviewed, review due, review overdue, not reviewed) is not a field. It is computed on every read and exposed read-only as `living_handbook_status`; see [rest-routes.md](rest-routes.md).

### Source and sync

| Key | Type | REST | Written by | What it holds |
| --- | --- | --- | --- | --- |
| `_lh_source` | string, `wordpress` or `github` | yes, default `wordpress` | The **Source** box, the GitHub import, the app handbook load | Where the page is maintained. Any other value is stored as `wordpress`. |
| `_lh_source_url` | string, URL | yes, default empty | The Source box, the GitHub import | The Markdown file a GitHub page is pulled from. The host allowlist is checked again before every fetch. |
| `_lh_sync_status` | string | not registered | `GitSync` after every pull | The last result as text, shown as "Last sync" in the Source box. |
| `_lh_sync_error` | `'1'` or absent | not registered | `GitSync` on a failed pull, removed on the next good one | Flags the page for the "could not be synced" admin notice. |
| `_lh_github_folder` | string | not registered | GitHub folder import and app handbook load | Marks the page that stands for a folder without a Markdown file of its own, so a second import finds it again. `owner/repo@branch:path` for GitHub, `local:path` for the bundled handbooks. |
| `_lh_app_handbook` | `'1'` or absent | not registered | App handbook load with **Connection to the source** on | Ties the page to the shipped copy: the editor is locked and a later load refreshes it. Absent when the connection was switched off; a later load leaves such a page untouched. |

### Import bookkeeping

| Key | Type | REST | Written by | What it holds |
| --- | --- | --- | --- | --- |
| `_lh_source_path` | string, relative path | not registered | ZIP, MkDocs and folder imports, the app handbook load | The file a page came from, relative to the import root. A re-import finds the page again by it, and internal `.md` links are resolved through it. |
| `_lh_import_parent_title` | string | not registered | `Postprocessor::apply_transport()` from the line `Eltern-Seite` | The title of the intended parent, kept until every page of the import exists. Deleted once the parent is resolved. |
| `_lh_origin_id` | string, `<site URL>#<post ID>` | not registered | Bundle import | Where the page was exported from. The first key a later bundle import matches on. |
| `_lh_bundle_key` | string | not registered | Bundle import | The page's hierarchy key inside the bundle it came from. The second key a bundle import matches on. |
| `_lh_import_protected` | any non-empty value | not registered | Nobody in the plugin: set it yourself, with code or WP-CLI | Read by the bundle import: a matching page carrying it is never overwritten, whatever run rule was chosen. |
| `_lh_moved_from` | string, path | not registered | The bulk action **Move into a handbook…** | The address a page had before it became a handbook page, for example `about/team`. A request to the old address is answered with a permanent redirect. |

### Feedback

| Key | Type | REST | Written by | What it holds |
| --- | --- | --- | --- | --- |
| `_lh_feedback_yes` | integer | not registered | The feedback endpoint | Yes votes. |
| `_lh_feedback_no` | integer | not registered | The feedback endpoint | No votes. |
| `_lh_feedback_voters` | array of user IDs | not registered | The feedback endpoint, for signed-in votes | Who has voted, so a signed-in user counts once per page. Anonymous votes add nothing here. |

All three are removed by the row action **Reset feedback** in the page list. Anonymous votes are also counted per page and hour in a transient, `lh_fb_<post ID>_<YYYYMMDDHH>`, for the hourly ceiling (`living_handbook_anonymous_feedback_limit` in [hooks.md](hooks.md)); it expires after two hours and identifies nobody.

## Learning paths (`lh_training`)

| Key | Type | REST | Written by | What it holds |
| --- | --- | --- | --- | --- |
| `_lh_training_lessons` | array of post IDs | no (`show_in_rest` is off on purpose) | The **Lessons** box on save | The ordered lesson list, at most 200 entries, duplicates dropped. Who sees which lesson is decided when the path is read, not here. |

## Media (`attachment`)

| Key | Type | REST | Written by | What it holds |
| --- | --- | --- | --- | --- |
| `_lh_import_file` | string, file name | not registered | Every image sideloaded by an import or a sync | Marks an image the plugin brought in, so a re-import reuses its own uploads and nobody else's. |
| `_lh_import_hash` | string | not registered | Same | A content hash, so a changed source image is uploaded again instead of reused. |

## Handbooks (`handbook_set` terms)

| Key | Type | REST | Written by | What it holds |
| --- | --- | --- | --- | --- |
| `living_handbook_visibility` | string: `public`, `members`, `restricted` | no | The handbook edit screen (`manage_categories` and `edit_term`, so only whoever may read the handbook); a bundle import creating a handbook always writes `members` | The access level. Empty is read as `members`. |
| `living_handbook_roles` | array of role slugs | no | The handbook edit screen; a bundle import | The roles a restricted handbook admits. |
| `living_handbook_users` | array of user IDs | no | The handbook edit screen only | The people a restricted handbook admits on top of the roles. Never exported and never imported. |
| `living_handbook_comments` | string: `inherit`, `open`, `closed` | no | The handbook edit screen | Comments for the whole handbook: leave them to each page, or force them on or off. |

None of the four is readable over the REST API: who may read a handbook is not something to hand out to whoever asks.

## Older names

Installations from before 0.56.0 carried some of these under other names. `Plugin::rename_meta_keys()`, run once by `Plugin::maybe_upgrade()`, renames them in place: `living_handbook_source` and `living_handbook_markdown_source` became `_lh_source` and `_lh_source_url`, and the feedback counters went from `living_handbook_feedback_*` through `_living_handbook_feedback_*` to `_lh_feedback_*`. Code that still reads an old name reads nothing.

## Transport-Metadaten
* Seitentyp: Tool overview
* Zielgruppe: Tech
* Reihenfolge: 11
* Letzte Aktualisierung: 2026-10-27
