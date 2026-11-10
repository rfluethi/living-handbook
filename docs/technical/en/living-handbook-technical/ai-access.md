# AI access: abilities and the count of failed searches

From 0.77.0 the plugin registers seven reading abilities with the [Abilities API](https://developer.wordpress.org/news/2025/11/introducing-the-wordpress-abilities-api/) of WordPress. With the [MCP Adapter](https://github.com/WordPress/mcp-adapter) installed, an AI client such as Claude or Cursor can use them as tools: list the handbooks, read the structure, search, read a page, and, for editors, list what is due for review, what readers found unhelpful and what they searched for without finding it. How to connect a client is in the user handbook, under **Access → Connect an AI tool**.

This page is for anyone changing the code. The decisions behind it are E-0056 (reading abilities, no chat on the site, no index of its own) and E-0078 (what the count of failed searches stores).

## Where the code is

| File | What it does |
| --- | --- |
| `src/Abilities/Abilities.php` | Registers the category `living-handbook` and the seven abilities, each with the same meta. Does nothing where `wp_register_ability()` does not exist. |
| `src/Abilities/Ability.php` | The base class: name, texts, schemas, permission, run. |
| `src/Abilities/Pages.php` | The access rule and the page description every ability shares. |
| `src/Abilities/TextConverter.php` | Rendered HTML to Markdown-like text, for `get-page`. |
| `src/Abilities/ListHandbooks.php` … `ListFailedSearches.php` | One class per ability. |
| `src/Search/SearchLog.php` | The table of failed searches: counting, cleanup, reading. |
| `src/Frontend/RenderedContent.php` | A page's content as a reader sees it, shared with the static export. |

## The abilities

| Ability | Who | What it returns |
| --- | --- | --- |
| `living-handbook/list-handbooks` | signed in | The readable handbooks with name, slug, description, address, page count and handbook version. |
| `living-handbook/get-structure` | signed in | The page tree of one handbook, flat, in navigation order, with depth and parent. At most 1000 pages, `truncated` says when there were more. |
| `living-handbook/search` | signed in | Up to 20 pages for a full-text search, optionally in one handbook and by the four classifications. |
| `living-handbook/get-page` | signed in | One page by id or address, its text as Markdown with headings, lists and tables. At most 100 000 characters. |
| `living-handbook/list-due-pages` | `edit_others_posts` | Overdue, due and unreviewed pages and pages without a reviewer, most urgent first. |
| `living-handbook/list-feedback` | `edit_others_posts` | Pages by share of "not helpful" votes, with a minimum number of votes (default 5). |
| `living-handbook/list-failed-searches` | `edit_others_posts` | The most frequent searches without a hit, per handbook and period. |

Every page in an answer carries its address and its review status (`ok`, `due`, `overdue`, `none`, with date and interval), so a client can cite its source and say how current it is. This is precaution 3 of Konzept 04.

## The access rule

`Pages::readable()` is the one check every ability runs for every page. A page is handed out only when all of this holds:

- it is a published handbook page without a password,
- `AccessController::can_view_post()` lets the current account read it, the same rule every other way into a handbook uses,
- it does not carry `living_handbook_ai_exclude`.

Handbooks go through `Pages::handbook()` or `Pages::readable_handbooks()`, which use `AccessController::can_view_term()`. A handbook or page that does not exist and one the account may not read give the same error, `living_handbook_not_found`, with the same message, so an answer does not tell what exists.

The structure follows the navigation: a page the account may not read is not in `PageTree::children_map()`, and its children are not reached. A page that is left out only for AI access (excluded or behind a password) takes nothing with it; its children move up to its parent.

`get-page` leaves out the addresses of uploaded files in every handbook that is not public. Until the file protection of E-0076 exists, an upload is reachable by anyone who has its address, and an address that reached an AI provider is out of the site's hands. The link text and the image's alternative text stay.

`AbilitiesTest` holds all of this. Each condition of `Pages::readable()` was taken out once and a test failed for each.

## The meta of every ability

`Abilities::meta()` gives each ability the same meta:

- `public`, `show_in_rest` and `mcp.public` all `true`. WordPress 7.1 reads `public`, 6.9 only `show_in_rest`, and the MCP Adapter (0.7.1) reads `mcp.public` before `public`. Set together, the exposure is the same everywhere.
- The annotations `readonly: true`, `destructive: false`, `idempotent: true`. The search counts a search without a hit; that is bookkeeping about the search, not a change to anything a reader or an editor sees, and it is not treated as writing.

## The count of failed searches

`SearchLog` keeps one table, `{prefix}living_handbook_failed_searches`, the plugin's first own table:

| Column | Content |
| --- | --- |
| `week` | Monday of the calendar week, in the site's time zone. |
| `term_id` | The handbook searched. |
| `source` | `site` for the handbook's own search, `ai` for the search ability. |
| `term` | The search term, lower case, whitespace collapsed, 3 to 100 characters. Shorter and longer terms are not stored. |
| `hits` | How often. |

There is no user id, IP address, time of day, cookie or referrer, and `SearchLogTest` fails when a column is added. Counted is:

- an empty answer of the quick search (`/living-handbook/v1/search`),
- an empty result list on a handbook's entry page, only on the first page and only without a facet selected, because an empty list with a facet says the combination is empty, not that a page is missing,
- an empty answer of the search ability without a filter, once per handbook searched.

`record()` checks itself that the current person may read the handbook. At most 500 distinct terms per handbook and week enter the table, beyond that only the terms already there count up, so a script cannot fill it. Counting is one `INSERT … ON DUPLICATE KEY UPDATE`, so two searches at the same moment do not lose a count.

A daily cron event (`living_handbook_search_log_cleanup`) deletes every week whose first day is older than the retention period, 90 days unless `living_handbook_search_log_retention_days` says otherwise (see [hooks.md](hooks.md)). The switch is on **Handbook → Settings → Feedback**, on by default. The uninstall drops the table every time, not only with "Remove everything", because it is bookkeeping, not content. The suggested privacy policy describes the count.

The live search sends a request while someone types, so a word that finds nothing arrives as "mül", "müll", "mülle", "müller". `most_frequent()` leaves out a term that is the beginning of another term of the same handbook, source and week; the longer term carries the count. A real short word that begins a longer one is lost with it.

## Versions

The Abilities API is in WordPress from 6.9. The plugin still runs from 6.8 and simply registers no abilities there. The tests run on 6.8, 6.9, 7.1 and trunk; on 6.8 `AbilitiesTest` is skipped.

## Transport-Metadaten
* Seitentyp: Background / Concept
* Zielgruppe: Tech
* Reihenfolge: 14
* Letzte Aktualisierung: 2026-11-10
