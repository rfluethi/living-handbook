# Code overview

A plain-language tour of how the plugin is built, for anyone who wants to understand the code without already knowing it. It does not assume you have written a WordPress plugin before. The developer-facing summary is in [architecture.md](architecture.md); this page is the gentle version.

## What the plugin is, in one paragraph

Living Handbook turns WordPress into an internal team handbook. A handbook is a set of pages that belong together, with an owner, a review date, and an access rule for who may read it. The plugin adds a new kind of page (a "handbook page"), groups those pages into handbooks, controls who sees what, tracks how fresh each page is, and can pull pages in from Markdown files or from a GitHub repository. Everything the visitor sees is assembled fresh on each page load from small building blocks.

## The words you need

A handful of terms come up everywhere. Once these click, the rest of the code reads easily.

- **Handbook page** (`handbook`): one page of a handbook. Technically a WordPress "custom post type", which just means "a kind of content the plugin defines, like posts or pages but its own".
- **Handbook** (`handbook_set`): the group a page belongs to. Technically a "taxonomy term", the same mechanism as a category. Each page belongs to exactly one handbook, and each handbook has its own access rule and its own start page.
- **Taxonomy**: a way to classify pages. Besides the handbook grouping there are four classifying vocabularies: page type, topic, responsible role, and audience. These drive the filters and the badges.
- **Access**: whether the current visitor may read a handbook. Three levels: public, all logged-in members, or restricted to named roles and people. It is checked only on the front end; editing in wp-admin uses the normal WordPress roles.
- **Freshness**: how up to date a page is, worked out from its last review date and its review interval. Three states: reviewed, review due, review overdue.
- **Block**: a piece of the page (navigation, table of contents, badges, and so on). The plugin ships its own blocks; each one builds its own HTML on the server.
- **The three surfaces**: the three kinds of page a handbook has. The **overview** lists all handbooks; a handbook's **entry page** is its start page (search, filters, sections, recently updated); a **single page** is one handbook page with its navigation, content and footer.

## How the code is laid out

All the plugin's own code lives in `src/`, one folder per area of responsibility. The file `living-handbook.php` at the root is the entry point: WordPress loads it, it sets up an autoloader (a small function that finds a class file when the class is first used, so nothing has to be required by hand), and then it hands over to `src/Plugin.php`.

`src/Plugin.php` is the wiring. Its `boot()` method creates one object per module and calls `register()` on each, which is where every module hooks itself into WordPress. If you want to know everything the plugin does, read `boot()`: it is the table of contents for the code. `Plugin.php` also holds the `activate()` and `deactivate()` steps that run when the plugin is switched on or off.

The modules under `src/`:

- **`PostType/`** defines the handbook page type. `Handbook.php` registers it and keeps it out of search engines, sitemaps and feeds, so an internal handbook does not leak.
- **`Taxonomy/`** defines the four classifying vocabularies (page type, topic, role, audience) in `Taxonomies.php`.
- **`Handbook/`** defines the handbook grouping. `Handbooks.php` registers the grouping and its access settings; `HandbookAdmin.php` is the small editor screen where you set a handbook's visibility, roles and people.
- **`Access/`** is the gate. `AccessController.php` answers one question, "may this user read this page?", and every read path in the whole plugin asks it here. It is deliberately strict: a page that belongs to no handbook is not readable. It also closes the side doors, so comments and REST requests cannot reveal a page the visitor may not see.
- **`Meta/`** registers the per-page fields in `Metadata.php`: last updated, last reviewed, review interval, reviewer, a "hide from AI" flag, and the on-this-page depth. It also exposes a single read-only summary of a page's freshness over the REST API.
- **`Frontend/`** is everything the visitor sees. `AccessController` aside, this is the biggest folder:
  - `FrontendRenderer.php` loads the stylesheet and script and can inject the handbook list into the theme's own menu.
  - `Templates.php` provides the page layouts for a handbook's entry page and single pages.
  - `Cards.php` draws the cards and tiles (a handbook, a page, an area).
  - `Entry.php` builds the entry page body: search, filters, area tiles, recently updated.
  - `Filters.php` builds the search and facet filters and the endpoint that returns the filtered list.
  - `Navigation.php` builds the page tree for one handbook, the collapsible sidebar.
  - `PageTree.php` loads all published pages of one handbook in a single query and groups them by parent, so the navigation and the area tiles build their hierarchy from the same map instead of querying per branch.
  - `PageMeta.php` builds the "was this helpful?" prompt and the metadata footer.
  - `FreshnessStatus.php` works out the reviewed / due / overdue state and its label.
- **`Blocks/`** registers the plugin's blocks. `Blocks.php` registers most of them and renders them on the server; `MermaidBlock.php` and `SourceNoteBlock.php` are two special ones (a live diagram, and a note shown only on GitHub-synced pages). The blocks call into `Frontend/` to produce their HTML.
- **`Feedback/`** handles the "was this helpful?" votes and their counters (`Feedback.php`).
- **`Admin/`** is the backend maintenance surface. `Maintenance.php` is the dashboard widget that lists pages whose review is overdue, and the review and feedback columns and the filter bar in the page list. `MoveToHandbook.php` is the bulk action that turns ordinary pages into handbook pages. `ListScreen.php` answers the two questions the filter bar asks before drawing a control: is this column visible, and does this vocabulary have any term at all.
- **`Import/`** brings Markdown in. `MarkdownImportPage.php` is the import screen and its endpoints; `MarkdownConverter.php` turns Markdown into HTML; `TransportBlock.php` reads the small metadata block a draft carries; `MkDocsImport.php` reads a `mkdocs.yml` to keep a project's structure; `Postprocessor.php` applies that metadata and rewrites internal links after the pages exist; `ImageRefs.php` collects the relative image references in a Markdown draft, both Markdown syntax and raw `<img>` tags, so the files next to the page travel with it; `HtmlSanitizer.php` is the shared allow-list that strips anything unsafe from imported HTML. Two files in the same folder work on whole handbooks rather than single documents: `HandbookExport.php` writes a handbook, or one area of it, into a self-contained bundle, and `HandbookImport.php` reads such a bundle back in on another site. `AppHandbook.php` is a small third one: it points the import screen at the app's own handbook, which ships inside the plugin as Markdown under `handbuch/` and is imported from that local folder; a fork can point it at a GitHub repository instead through the `living_handbook_app_handbook_url` filter.
- **`Git/`** is the GitHub sync. `GitSync.php` lets a page be sourced from a Markdown URL, pulls it on save, on demand and on a schedule, stores the result safely, and locks the editor for such pages.
- **`Setup/`** is the first-run and settings code. `Seeder.php` fills in the default vocabularies on activation; `Onboarding.php` creates the overview page and the welcome notice; `Settings.php` is the settings screen (sync frequency, uninstall behaviour).

The browser-side code lives in `assets/`: `frontend.css` and `frontend.js` for the visitor pages, `blocks.js` for the block editor, and a few small scripts under `assets/js/` for the import screen and the diagrams. Translations live in `languages/`.

## How a page view flows through the code

Follow a visitor opening a single handbook page. It touches the modules in a fixed order, and seeing that order is the fastest way to understand the whole thing.

1. WordPress matches the URL and decides it is a handbook page.
2. **Access** steps in first (`AccessController`): may this visitor read it? If not, a guest is sent to the login and everyone else gets a "not found". Nothing further runs.
3. If access is granted, the **template** for a single handbook page (`Templates`) lays out three columns: navigation on the left, content in the middle, on-this-page on the right.
4. Each **block** in that template now renders on the server by calling into `Frontend/`: `Navigation` builds the handbook's page tree, `Cards`/`PageMeta` build the badges and the footer, the table-of-contents block outputs an empty container.
5. The **stylesheet and script** load (`FrontendRenderer`). In the browser, `frontend.js` fills the table of contents from the page's headings, wires the navigation's open and close, and handles the feedback buttons.
6. When the visitor votes on "was this helpful?", the script calls the **feedback** endpoint (`Feedback`), which checks access again before counting the vote.

An entry page (a handbook's start page) is the same idea with a different template: search, filters, area tiles and recently updated, where selecting a filter calls the **filter** endpoint (`Filters`) and swaps the list in place.

## How an import flows

The import screen (`MarkdownImportPage`) accepts a pasted draft, a ZIP of Markdown files, or a GitHub URL. Text is turned into HTML by `MarkdownConverter`, the browser turns that HTML into editable blocks, and the small metadata block on each draft is read by `TransportBlock`. Once the pages exist, `Postprocessor` runs a second pass: it sets each page's handbook, taxonomies, review data and parent, and rewrites links that point at other imported files so they lead to the right pages. GitHub-sourced pages take a shorter path through `GitSync`, which stores rendered HTML rather than editable blocks, because a scheduled job has no browser to do the conversion.

Moving a whole handbook to another site works differently, because there is nothing to convert. `HandbookExport` walks the handbook's pages and writes them, their structure, their vocabularies, their review data and their media into one ZIP with a `manifest.json`. `HandbookImport` reads that file on the other site and puts the pages back, giving every one of them a new id and rewiring parents and internal links to match. What happens when a page is already there is a choice the person importing makes; the default never overwrites anything.

## The one rule that matters

There is a single, non-negotiable rule in the code: **every read of handbook content goes through `AccessController::can_view_post()`**. Never read a page's handbook and decide access yourself, and never query the database around the check. If you add a new way to read or list handbook pages (a widget, a REST field, an AI integration), route it through that method. It is the one place that decides visibility, it is filterable, and it fails closed, so forgetting it is the one mistake that can leak content.

## Where to look for what

| You want to change... | Look in |
| --- | --- |
| Who may see a handbook | `Access/AccessController.php`, `Handbook/HandbookAdmin.php` |
| The page tree / sidebar | `Frontend/Navigation.php`, `assets/frontend.css`, `assets/frontend.js` |
| The entry page (search, filters, tiles) | `Frontend/Entry.php`, `Frontend/Filters.php`, `Frontend/Cards.php` |
| Freshness (reviewed / due / overdue) | `Frontend/FreshnessStatus.php`, `Meta/Metadata.php` |
| The overdue dashboard | `Admin/Maintenance.php` |
| A block's markup | `Blocks/Blocks.php` (server), `assets/blocks.js` (editor) |
| The Markdown import | `Import/` (start at `MarkdownImportPage.php`) |
| Moving a handbook between sites | `Import/HandbookExport.php`, `Import/HandbookImport.php` |
| The app handbook | `Import/AppHandbook.php` (which source is used), `Git/GitSync.php` (`import_local_folder` for the bundled copy, `import_folder` for a GitHub override) |
| The GitHub sync and settings | `Git/GitSync.php`, `Setup/Settings.php` |
| What runs on activation | `Plugin.php` (`activate`), `Setup/Seeder.php`, `Setup/Onboarding.php` |
| What runs after an update | `Plugin.php` (`maybe_upgrade`, `rename_meta_keys`) |
| How fast a page is, and why | `bin/seed-performance.php`, `bin/measure-performance.php`, the `*QueryCostTest` files under `tests/Integration/` |

## How the code documents itself

- Every class, method and function carries a doc comment; the coding-standards check (`composer lint`) fails if one is missing, so this never drifts.
- Comments explain the reasoning, not the obvious. When a piece of code looks odd, the comment above it says why it is that way.
- There are almost no settings on purpose: visual choices belong in the Site Editor, behaviour has a few justified options, and everything else is a hook (see [hooks.md](hooks.md)) rather than a checkbox.
- English is the language of the repository; the German source of these developer docs (`docs-de/`) lives in the team's workspace. The German app handbook is the exception: it ships in the repository, under `handbuch/de/`.
