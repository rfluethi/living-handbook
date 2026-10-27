# REST routes

Every route the plugin registers, all under the namespace `living-handbook/v1`, so a route `/feedback` is reached at `/wp-json/living-handbook/v1/feedback`. Read off the `register_rest_route()` calls in `src/`; when the two disagree, the code is right and this page is the bug.

Two kinds of route live here. The **reader routes** (filter, search, feedback) serve the front end and are open to guests on purpose, with the access check inside the handler. The **editor routes** (import, export, lesson search) serve admin screens and are closed by their permission callback. All of them are driven by the plugin's own scripts; none of them is a stable public API, and their response shape may change with the screen that uses it.

Write routes need the REST nonce (`X-WP-Nonce`) like any WordPress REST call with cookie authentication; the plugin's scripts send it through `apiFetch`.

## Reader routes

| Method | Path | Permission | Parameters | Returns |
| --- | --- | --- | --- | --- |
| GET | `/filter` | everyone (`__return_true`) | `term_id` (required, handbook term ID); `lh_s` (search text); `lh_type`, `lh_topic`, `lh_role`, `lh_audience` (arrays of term slugs, only for the taxonomies this site uses); `paged` | `{ html }`: the result column of the entry page for that selection. Empty HTML when the handbook does not exist or the caller may not read it. |
| GET | `/search` | everyone (`__return_true`) | `term_id` (required); `q` (search text) | `{ results: [ { title, url, snippet } ] }`, at most 8 published pages of that handbook. Empty when `q` is empty or the handbook is not readable. `snippet` is the text around the hit, as a list of `{ text, mark }` pieces rather than markup. |
| POST | `/feedback` | signed in, or **Public feedback** switched on | `post_id` (required, integer); `value` (required, `yes` or `no`) | `{ ok, counted }`. 400 for anything that is not a handbook page or not `yes`/`no`; 403 when the caller may not read the page; 429 when the hourly ceiling for anonymous votes is reached. |

"Everyone" is deliberate: a public handbook must stay searchable for guests. The decision sits one level down, and it is the one that matters: both `/filter` and `/search` ask `AccessController::can_view_term()` for the handbook and `can_view_post()` for every single hit before anything is returned. `/feedback` asks `can_view_post()` for the page, so a guest can only ever vote on a page of a public handbook. `tests/Integration/RestAccessTest.php` holds this line.

A signed-in vote is counted once per user and page (see `_lh_feedback_voters` in [meta-fields.md](meta-fields.md)); a second one answers `counted: false`. An anonymous vote is never deduplicated, by design, and is capped per page and hour instead (`living_handbook_anonymous_feedback_limit`, see [hooks.md](hooks.md)).

## Editor routes

| Method | Path | Permission | Parameters | Returns |
| --- | --- | --- | --- | --- |
| POST | `/convert` | `edit_posts` | `markdown` (required, string) | `{ title, html, transport }`: one pasted draft converted to HTML, with its transport block parsed. Nothing is written. |
| POST | `/import-zip` | `upload_files` | the file `zip`, as a multipart upload | `{ files: [ { name, slug, title, html, transport } ], images, notes }`, or for a MkDocs project `{ mode: "mkdocs", pages, images, notes }`. The images are sideloaded here; the pages are created afterwards through `/create`. A Markdown file, an image or an `mkdocs.yml` above 5 MB is left out and named in `notes`. |
| POST | `/create` | `edit_posts` | `title`, `content` (block markup, sanitised here), `handbook` (term ID), `transport` (object), `parent` (post ID), `sourcePath`, `slug`, `order` | `{ id, sourcePath, updated, editUrl }`. Creates a draft, or refreshes the page a re-import matches, but only one the caller may edit. When `handbook` names a handbook the caller may not read, the answer is `{ error }` and no page is created. |
| POST | `/finalize` | `edit_posts` | `ids` (array of post IDs) | `{ converted }`: resolves parents and internal `.md` links. Only handbook pages the caller may edit are touched. |
| POST | `/import-github` | `edit_posts` | `url` (a file or `tree` URL), `handbook`, `title`; or `job` to continue a folder import; or `app_handbook` with `app_entry` and `keep_source` | `{ pages: [ { id, title, editUrl } ] }`, plus `notes` when there is something to report. A folder import that runs out of time answers with `job`, `phase` (`pages` or `links`), `remaining` and `total`, and is asked again with that `job`; a used-up GitHub quota adds `retry_after` in seconds. Loading the app handbook additionally requires `edit_others_posts` and answers 403 without it. Also 403 when `handbook` names a handbook the caller may not read. |
| POST | `/static-export` | `edit_others_posts` | `handbook` (term ID), `area` (top-level page ID, optional), `theme` (`site`, `plain`, `dark`, `paper`, or one added through `living_handbook_static_export_themes`); or `job` to continue | One pass of a website export: `{ job, done: false, total, remaining }` until it is finished, then `{ job, done: true, total, url, name, size }`, where `url` downloads the ZIP through `admin-post.php`. 403 when the caller may not read the handbook; a job belongs to the user who started it. Parameters of the wrong kind answer 400 `rest_invalid_param`; an archive that cannot be written answers 500 `living_handbook_export_unwritable`. |
| GET | `/training-lessons` | `edit_post` on the learning path named by `training_id` | `training_id` (required); `q` (title search) | `{ results: [ { id, title } ] }`, at most 10 published pages of the path's own handbook, matched by title only. 400 when `training_id` is not a learning path; 403 when the caller may not read that handbook. Every hit is checked with `can_view_post()`. |

The permission callback is the first gate, not the only one. `/create` and `/finalize` check `edit_post` for every page they would touch, because `edit_posts` alone is what a Contributor has; `/import-github` publishes nothing except the app handbook, and that path asks for more.

## What the plugin adds to core routes

The plugin does not only register routes, it also narrows and extends core ones. Worth knowing when you read `/wp/v2` yourself:

- **`/wp/v2/handbook`** (the page type, `show_in_rest`) carries the editorial fields under `meta` (see [meta-fields.md](meta-fields.md)), and a read-only field `living_handbook_status`: `{ freshness, reviewed, interval, permalink, ai_exclude }`. A single page the caller may not read answers 404, in every context, including `context=edit`; collections are filtered the same way.
- **`/wp/v2/lh_training`** exists only while the learning paths module is switched on, and follows the same access rules as the pages.
- **`/wp/v2/handbook_set`** answers an empty list, and a single term with 403, to anyone without `edit_posts`. Someone with `edit_posts` gets only the handbooks they may read, and 403 for any other single term, so the block editor offers only those in its handbook picker. The access configuration of a handbook is not exposed there at all.
- **`/wp/v2/media`** and **`/wp/v2/comments`**: an attachment or a comment on a page the caller may not read is left out of collections and answers 404 on its own.
- **`/oembed/1.0/embed`** answers nothing for a handbook page the caller may not read.

## Transport-Metadaten
* Seitentyp: Tool overview
* Zielgruppe: Tech
* Reihenfolge: 12
* Letzte Aktualisierung: 2026-10-27
