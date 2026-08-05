# Changelog

Full version history of Living Handbook, in the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
format. This file is the long version: what was wrong, what changed, and why it
was decided that way. `readme.txt` carries the short entry a person reads in the
WordPress update dialog, written by hand rather than generated, because the two
are deliberately different lengths. Both must have an entry for the released
version; a test checks that.

From 0.60.0 on, a new entry groups its bullets under **Added**, **Changed**,
**Fixed** and **Removed**. Older entries are left as they were written: their
bullets are narrative rather than categorised, and sorting them after the fact
would mean guessing what each one was.

Dates come from the release tag where there is one, otherwise from the commit
that raised the version. Four early entries (0.7.0, 0.8.0, 0.10.0 and 0.39.0)
carry no date, because the repository's history does not record one and a
plausible date is worse than none.

## [0.62.1] - 2026-08-05

### Fixed

* Plugin Check reported three findings on 0.62.0 that the plugin's own `composer lint` did not: an unsanitised `$_POST['as_pages']` in the bundle import, and the `meta_key`/`meta_value` lookup in the redirect for moved pages. The input is sanitised. The lookup stays, with the reason written where it sits: it runs on a 404 only, `meta_key` is indexed so the scan covers this site's moved pages rather than all of `wp_postmeta`, and the alternative (an autoloaded option holding every old path) would be read on every request instead of a rare one.
* The cause behind all three, which matters more than the three: `phpcs.xml.dist` used `WordPress-Extra`, and Plugin Check runs the full `WordPress` standard, which carries two rule groups Extra does not. Local lint was therefore weaker than the check that decides a wordpress.org submission, and this class of finding could only surface after a release. `WordPress.Security` and `WordPress.DB` are now in the ruleset, which brought up exactly these three across the whole codebase and nothing else, so "composer lint is green" now means "Plugin Check is green".

### Changed

* The redirect for a moved page asks the database nothing on a site where no page has ever been moved. A single option decides, set by the first move. Before this, every 404 on every installation ran a meta lookup whose answer on almost every installation is no. Counter-checked.

## [0.62.0] - 2026-08-05

### Added

* **Existing WordPress pages can be moved into a handbook**, from the bulk actions on the page list, one entry per handbook. Changing `post_type` is the whole move and is exactly why doing it by hand goes wrong, so three things happen with it. The pages get the handbook, because access is fail-closed and a handbook page without one is not moved but gone. Their old paths are remembered and answered with a 301, because WordPress does not redirect on a type change and every link, bookmark and search result pointing at `/about/` would otherwise die the day it became `/handbook/about/`. And subpages always come along, not as an option: a bulk dropdown has no room for the question, and the answer "no" leaves children whose parent is no longer a page and whose own address is built from that chain. Moved pages arrive as "Not reviewed", which is the honest state for a page that has just become documentation.
* **A bundle can be imported as ordinary WordPress pages.** The importer's reading, unpacking, sanitising, media sideload and hierarchy are all independent of the post type; one line decided it. What comes along is the text, the images, the diagrams and the structure. What does not is everything the handbook adds around a page: no handbook, so no access rule, and no navigation, table of contents, badges, feedback or source note, because those live in the handbook template and in blocks that check their context. Pages created this way are always drafts, whatever the bundle says, because a bundle exported from an internal handbook would otherwise be published by the act of importing it. Counter-checked.

### Changed

* The first level of the navigation is indented under the handbook title, like every level below it. Since 0.61.0 the title is the root of the tree, but the level under it still sat flush with it, which made the handbook name read as a label above a list rather than the top of it.
* The filters above the handbook page list follow the order of the columns they filter: handbook, the four vocabularies, review status, source. WordPress prints its own date dropdown before the hook and cannot be reordered, so the date stays first.

### Removed

* The duplicate handbook column from 0.61.0. `handbook_set` was registered with `show_admin_column`, so the list already had one; 0.61.0 added a second, in a better place and with a better empty state, and shipped both. The taxonomy's own column is switched off and the plugin's stays.

## [0.61.0] - 2026-08-05

### Added

* A **Handbook** column in the handbook page list, directly after the title. The list mixes every handbook and you could filter by handbook without ever being able to read one off a row. A page with no handbook shows a dash with a screen-reader note saying what that means, because such a page is invisible on the front end and this list is where that gets noticed.
* **Bulk Edit** for the review date, the review interval and the reviewer. Quick Edit answers "I reviewed this page today"; a handbook of two hundred pages raises a different question, "these forty are reviewed yearly by the same person", and answering it page by page is a large part of what makes a big handbook feel unmaintainable. The responsible role was already there, because WordPress offers flat taxonomies in Bulk Edit by itself; meta it offers nothing for. Every field defaults to "leave unchanged": the form submits all of them, so "not filled in" and "cleared" look identical on the wire and are told apart in the handler. Counter-checked, because that distinction is the one mistake on this screen that cannot be undone from the screen.

### Changed

* The handbook title in the navigation is an ordinary link to the handbook's start page, and the small arrow that used to lead there is gone. The arrow existed for a technical reason and testers read it as noise: the whole navigation was a native `<details>` with the title as its `<summary>`, and a `<summary>` can toggle or it can link, not both dependably. The title row now has the same shape as every other row with children, a toggle button on the left and a link beside it, so the handbook name behaves like every other name in the list. The block is no longer a `<details>`; `.living-handbook-nav.is-collapsed` marks the closed state, and `.living-handbook-nav__home` is gone.
* The freshness threshold idea from the same conversation was dropped rather than built. The measurement behind it was real (WordPress loads every page of a hierarchical post type into memory to draw the list, 2441 posts and 323 ms against 20 posts and 12 ms with an explicit sort order, `wp-admin/includes/post.php`), but the conclusion was not: 323 ms once in wp-admin is not worth trading a view for, and switching the tree off above a threshold would have removed it exactly where a large handbook needs it most.

## [0.60.0] - 2026-08-05

### Added

* A fourth freshness state, **Not reviewed**, for a page with no review date. It existed as a value (`FreshnessStatus::NONE`) but had no label, and every place that draws a badge or a dot skipped it explicitly, so a freshly imported handbook showed nothing at all where its freshness belongs and a reader could not tell "fresh" from "forgotten". It now appears in the page footer, on the card, in the status filter of the page list and as its own colour field in the settings. Neutral grey on purpose, measured at 5.59:1: a page nobody has looked at is not overdue, and colouring it like a warning would make every fresh import look like a failing handbook. The dot is an outlined circle rather than a filled one, because the four states must be told apart without relying on colour (WCAG 1.4.1), and an empty shape is the honest picture of a field nobody has filled in.
* Documentation for seven filters the plugin has been firing with no mention anywhere: `living_handbook_access_denied_title`, `living_handbook_access_denied_message`, `living_handbook_anonymous_feedback_limit`, `living_handbook_archive_allowed_hosts`, `living_handbook_archive_max_bytes`, `living_handbook_import_time_budget` and `living_handbook_export_user_identifier`. An extension point nobody can find is the same as not having one.
* `HookDocumentationTest`, which compares `docs/hooks.md` against every `apply_filters` and `do_action` in the source, in both directions, and requires each one to have a section of its own rather than a passing mention. Counter-checked. Its search matches across line breaks, because the first version of it did not and reported a clean result while seven hooks were missing.

### Changed

* `CHANGELOG.md` is now in the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format: `## [version] - date` instead of the readme syntax it borrowed, which left this file without structure and without a single date. Dates come from the release tag where there is one and otherwise from the commit that raised the version; four early entries carry no date at all, because the history does not record one and a plausible date would be a made-up one. Older entries keep their narrative bullets: sorting them into Added and Fixed after the fact would mean guessing. New entries group theirs, starting with this one. The file header now states what each of the two changelogs is for, and the smoke test checks that both carry an entry for the released version rather than only this one.

### Fixed

* The feature list said "a responsible role mapped to a current person", which promises more than exists: there is a responsible role per page (the `handbook_role` vocabulary) and a reviewer as a WordPress user, but no running role-to-person mapping. wp.org reads that description at submission, so it now names what is built. Rico's own framing of why the promise was wrong in the first place: the assignment has to be settled per page anyway.

### Removed

* Three hooks that were announced as planned and never built: an action after an import, a filter on the metadata output, and one on the freshness evaluation. A hook is a commitment whose signature cannot be changed later without breaking whoever relies on it, and these three came from a list rather than from a use. Kept as a note with signature and purpose in the project's own workspace, to be built when something actually needs them.

## [0.59.0] - 2026-08-05
* Three colours were fixed values in places that have no background of their own: the freshness dot on a handbook card, and the two error messages (the one the feedback block shows when its request fails, and the one the facet filter shows). Measured on a dark surface (#1e1e1e) the dots reach 2.65:1 and 2.61:1 against the 3:1 that a meaningful graphic needs, and the error text 3.07:1 against 4.5:1. Each now mixes a third of the theme's own text colour into the status colour, which darkens the hue on a light theme (8.4 to 9.0:1) and lightens it on a dark one (5.3 to 5.6:1), with one expression and no second set of values to keep in step. The hue survives, so overdue still reads as red. Measured in a browser afterwards, not only calculated: the values a real engine computes for --lh-ok-on-surface and its two siblings are the ones the arithmetic predicted. This closes the "contrast on theme colours" question from 2026-08-02 in the direction decision 42 set: what can follow the theme, follows it. What stays fixed are the badges, and they stay fixed for a reason that is now written into the stylesheet: a badge brings its own background, so its pair is self-contained and clears 4.5:1 on any theme, the tightest being 4.76:1.
* The German translations are checked by the test suite instead of by somebody remembering. 0.56.0 shipped a whole settings tab in English on a German site and nobody noticed for a release; an untranslated string is invisible to everyone except the person reading the wrong language. `TranslationCoverageTest` asserts that every entry in both shipped catalogues has a translation (bar the plugin's own URLs and the author's name), that de_DE and de_CH cover the same strings, and that de_CH really is de_DE with the sharp s resolved to ss rather than a second text drifting away from it. Counter-checked: blank one translation, or put a ß back into de_CH, and the matching test fails. It is a unit test with its own .po parser, so it runs in CI without gettext installed.
* Documentation: `docs/releasing.md` no longer claims that `Stable tag` runs ahead of what is released. It does not, and the reason is worth writing down: the version is raised in the release commit itself, so between releases `main` carries the last released version. Verified across v0.53.0 to v0.58.0, where the header, the constant and `Stable tag` all read the tag's own number at every tag. What the page now says instead is the condition under which that would stop being true. `CONTRIBUTING.md` says plainly what "welcome but not actively solicited" means in practice: open an issue before writing code, because the task list is not in this repository, so an issue tracker with nothing in it does not mean there is nothing to do.

## [0.58.0] - 2026-08-05
* Comments can be decided for a whole handbook, not only page by page. WordPress switches comments per page, which is the right size for a page and the wrong size for a handbook: turning them on across two hundred pages means opening two hundred pages. A handbook now carries a Comments setting under Handbook, Handbooks, with three values. "Each page decides" is the default and changes nothing, so an existing site notices no difference. "Open" and "Closed" override every page of that handbook. Deliberately an override and not a default: a default would have to be written onto every existing page the moment it is set, and would then be wrong for every page imported afterwards. Closing hides the comment form, exactly as closing comments on a single page does; comments already written stay readable and are not deleted, because deleting somebody's writing should be an act, not a side effect. Anything the setting does not recognise reads as "each page decides", so a stray value in the database cannot open comments on a whole handbook.
* The import no longer breaks links to other websites. `Postprocessor::convert_md_links()` matched every link whose address ends in `.md` and, finding no page of that name in the import, stripped it to plain text and listed it as a dead link. That rule is right for a relative link and wrong for an absolute one: the shipped handbook links to the developer docs on github.com, whose file names end in .md like anybody else's, so every fresh installation lost six of those links and reported them as broken. A link that names a host, whether by scheme or by a leading //, is now left exactly as it is. Three tests, all counter-checked, and the middle one guards the other direction: a relative link sitting beside an external one is still resolved.
* Documentation, the part a newcomer meets first. README and CONTRIBUTING said CI ran the coding standards, the static analysis and the unit tests; it also runs the whole integration suite against MySQL on every push and pull request, and CONTRIBUTING went as far as calling those tests "kept separate", which reads as optional for the four fifths of the suite that catches most regressions. Both now say what actually runs. New: `docs/README.md`, an index with a reading order, because the order existed only in a sentence in README and anybody arriving from a search landed in the middle of it. New: `docs/releasing.md`, because the release rules lived in two shell scripts and a workflow file, and the first thing anybody gets wrong is the version number, which sits in three files that a build script compares and then refuses to explain. New: `.github/CODEOWNERS`. The pull request checklist gained the integration tests, the counter-check rule for new tests, and the second changelog, which it had never mentioned.
* New: `.wp-env.json`, so a working WordPress with the plugin in it is `composer install` and `npx wp-env start`. There was no documented way to run the plugin at all, only to test it. Two things are set up because both look like a broken plugin rather than a missing setting: pretty permalinks, without which a handbook entry page at /handbook-set/<slug>/ cannot be served, and the note that the plugin needs a block theme, without which its templates are ignored and the front end silently falls back to the theme's own layout.

## [0.57.0] - 2026-08-05
* Comments on a handbook page work now. They never did: the post type has supported them since the beginning and the access filters cover them (comments_clauses, comment_feed_where, rest_prepare_comment), but no shipped template ever rendered a comment block, and the import wrote comment_status => closed onto every page it created, on top of the site default. So a page with comments open showed nothing, and the setting looked broken because from the outside it was. The single-page template now ends with the core comments block, which renders nothing while comments are closed, and the two import paths no longer decide about comments behind the site's back: the default comes from one place, Settings, Discussion, as it does for every other post type. Existing pages keep whatever they have; a re-import no longer forces them shut.
* Mermaid diagrams follow the page instead of always drawing light. The plugin started mermaid with nothing but startOnLoad, so it used the light default: connecting lines in #333333, which on a dark theme are all but invisible. Both the front end and the editor preview now pick the scheme from the background they are drawn on. Plain brightness decides, because the question is light or dark and the full WCAG luminance would not answer it differently.
* An imported diagram keeps its text alternative. Mermaid has accTitle and accDescr for exactly this, and the block and the viewer already read data-title and data-description, but the import threw the directives away: the accessible label then fell back to the diagram source, so a screen reader was read "graph TD; A-->B" instead of what the picture says. Affects the seven diagrams in the shipped handbook, which get their labels back on the next load.
* The Custom CSS field can no longer make every handbook page call a foreign host. It stripped "<" and nothing else, so an @import or a url(https://...) pasted from somewhere turned every reader of every handbook page into a request to a third party, with a referrer. Both are now removed unless they point at the site itself or are a data: URL. Only an administrator can write in that field, so this was never a hole another account could use; it is the accident that it prevents, and a handbook is the wrong place for that to happen unnoticed.
* Anonymous feedback has a ceiling of 200 votes per page and hour, filterable through living_handbook_anonymous_feedback_limit and switched off with 0. An anonymous vote is deliberately not tied to a person, so it cannot be deduplicated, which means each one is a database write with nothing in the way. The ceiling protects the page and says so with a 429; it is not a one-vote-per-person rule and does not pretend to be, that is what signing in is for.
* An export bundle carries the reviewer's login instead of their e-mail address. Both identifiers serve the same purpose, re-attaching the reviewer on the target site, and one of them is more personal than the other in a file that leaves the site. The importer reads both, so bundles written before this still resolve. The trade-off, stated rather than hidden: matching by login fails where the same person has a different login on the target site; living_handbook_export_user_identifier puts the old behaviour back.
* Internal: the settings page slug lived in three places (a constant in GitSync, a constant in Settings and a literal in the post type). One of them decides now.
* Corrected from the review: Navigation::branch() does not need a depth limit. A page has one parent, so a cycle in post_parent is a component with no path from the root, and the walk starts at the root. Such pages never render, which is its own kind of wrong, but it is not a recursion and no guard was added. The reasoning is written into the method so the question is not asked a third time.
* Documentation: the minimum WordPress version is 6.8 in README.md, docs/getting-started.md, docs/templates.md and CONTRIBUTING.md, which all still said 6.7 while the plugin refuses to run there. CONTRIBUTING.md now also states the spelling rule for the shipped German content.
* The German translation catches up with the settings. The template in the repository was last generated before the appearance tab existed, so the ten colour fields, the text size and every explanation beside them reached a German site in English: that is what 0.56.0 shipped, and a screenshot of the settings page is how it surfaced. The template is regenerated from source, de_DE carries the 23 strings that were missing, de_CH is derived from it with the sharp s resolved to ss, and the compiled catalogues (.l10n.php and the per-script JSON) are rebuilt from both. Checked in a running WordPress set to de_DE, not assumed from the file.
* Internal: bin/check-and-build.sh no longer rewrites the translation files when nothing about them changed. make-pot and msgmerge stamp POT-Creation-Date on every run, so a rebuild that found no new string still left the working tree differing from the commit it was built at, by one line. The script now keeps the previous file whenever everything apart from that stamp is identical, which is what makes "the zip matches the tag" a statement one can check rather than hope for.

## [0.56.0] - 2026-08-04
* The settings screen is split into five tabs: GitHub sync, Appearance, Feedback, Access, Uninstall. Each tab is its own settings group, which is not cosmetic: options.php walks the group of the submitted form and calls update_option() for every option in it, with null for the ones the form did not send, so one group across five tabs would empty the four tabs that were not on screen, on every save. A test pins that no option appears in two groups and that every registered option belongs to one, and an unknown tab in the address falls back to the first rather than rendering a form whose save writes nothing.
* Handbook → Settings → Appearance has the ten colours that matter and one text size, so a theme that gets it wrong no longer sends people to the Custom CSS field with a list of variable names. The rule the plugin is built on does not change: an empty field means the theme decides, that is the shipped state, and nothing is printed for an empty field. What is set is printed as --lh-user-* on :root, and the stylesheet reads every variable as var(--lh-user-x, <theme preset>, <fallback>), which gives three levels in the order of intent: the defaults follow the theme, the fields beat the defaults without a specificity fight, and CSS written by hand beats both because it names --lh-x directly and is printed last. The picker offers the theme's own theme.json palette as swatches, so a site picks a colour the theme already uses; a value that is not a hex colour is dropped rather than repaired, because it ends up inside a style element. Two colours are deliberately not fields: the page-type badge takes the accent, so the three chips under a page stay told apart by colour, and the topic and audience chips have a pair each. The text colour on a filled control is not a field: it is derived from the accent, black or white, whichever has the higher contrast. Text size is a percentage of the plugin's own text, not of a page's content, which belongs to the theme. Every font size in the stylesheet is now a multiple of --lh-base, which stays undeclared and therefore falls back to 1rem: at 100 percent the rendered sizes are identical to before, measured in a browser, and one value moves all 23 of them together and keeps the proportions they were tuned in. That is what a theme like Nodes needs, whose own text is 30.75px while the plugin sizes against 16px and looks small beside it. Eighteen tests, counter-checked on the failures that would be silent: a value that is not a colour getting through, a stylesheet variable renamed out from under a field, a font size left in rem, and two tabs sharing a settings group.
* Fixed: the settings a site made were missing on a handbook block rendered outside a handbook page, in a header, a footer or a template part. The Custom CSS was attached to the one place that enqueues the stylesheet on handbook views, not to the stylesheet itself, so the same block was styled one way inside the handbook and another way beside it. Both the settings and the Custom CSS now travel with the handle, like the script data since 0.56.0.
* Fixed: deleting the plugin left the no-access page setting behind in the database.
* The two page layouts the plugin ships are rearranged, and the navigation ships as an accordion. On a single page the page itself comes first, title then content, and everything about the page follows it in one block at the foot: the feedback prompt, the source note, the badges and the metadata footer. The badges used to sit above the title, where they are the first thing read on a page whose title has not been read yet; the source note was in no shipped template at all, so a GitHub-backed page said nowhere that editing it in WordPress is pointless. Two static separator blocks carry the dividers, so a divider is there whether or not its neighbour renders: a guest without public feedback gets no prompt, a page maintained in WordPress gets no source note, and the foot does not collapse either way. The handbook search moves into the left column under the navigation, because both answer the same question, and both templates ask the navigation for the accordion display: a handbook six levels deep does not fit a 22 percent column as a full menu. An installation that has saved either template in the Site Editor keeps its own version, which is the point of a plugin template; Design → Editor → Templates → Clear customizations brings the plugin's back. BlockTemplatesTest pins what a fresh installation gets, counter-checked on the three failures that say nothing at runtime: an unknown block name, a broken block comment, and a navigation that is not an accordion.
* A handbook view no longer costs a database query per page in the handbook. The reader filter runs on the_posts, which WordPress applies before it fills its own caches, so deciding access read every row back from the database twice, once for the post and once for its handbook membership. Both caches are now filled for the whole result set at once, and the membership is read through get_the_terms() instead of wp_get_object_terms(), which bypasses that cache. Measured on a seeded handbook of 2000 pages: the entry page went from 2027 queries and 1.48 seconds to 24 queries and 0.45 seconds, a single page from 2015 queries to 17, the navigation tree from 2009 to 10. The rule is unchanged, only what it costs.
* Fixed: a page could end up in two handbooks, and then showed the navigation of a handbook it is not in. The data model says one handbook per page and everything built on it assumes exactly that, but nothing enforced it: the block editor renders the handbooks as a list of checkboxes and lets you tick two. The result was not an error message but a page whose navigation tree does not contain it, whose entry page is the wrong one, and which appears in two handbooks at once. The rule is now enforced where the terms are written: ticking a second handbook moves the page instead of adding to it, because ticking a box is a deliberate act. Which handbook a page belongs to is also answered in one place now (`Handbooks::for_post()`); the three copies of that expression did not agree, they read the handbooks in name order, so renaming a handbook could move pages from one navigation tree into another. An assignment made before this stays as it is until the page is saved again, resolves to the same handbook everywhere in the meantime, and stays fail-closed for access: every handbook of a page must allow the reader.
* Exporting a handbook no longer costs four database queries per page. Each exported page was asked for its terms in the four vocabularies one page at a time, around the cache that the query fetching the pages had already filled. On a handbook of 2000 pages that was 8011 queries and 3.4 seconds, in the request that also has to build the ZIP; it is now 11 queries and 0.3 seconds, and 13 MB less memory. ExportQueryCostTest holds it there, counter-checked: with the old lookup, eight times the pages cost five times the queries and the test fails.
* The custom fields the plugin writes follow one rule now, and two of them were in the wrong place. There were three prefixes: living_handbook_ for the editorial fields, _lh_ for the plugin's bookkeeping, and _living_handbook_ for the three feedback keys, left over from an earlier rename. Worse, the two fields that say where a page comes from, living_handbook_source and living_handbook_markdown_source, carried the public prefix, which put them in the Custom Fields box of every handbook page: switching the source from GitHub to WordPress there stops the sync without a word, and the other way round hands a hand-written page to the next sync, which overwrites it. Both are now protected, _lh_source and _lh_source_url, as are the feedback counters. The rule is: a field a person fills in is public and named living_handbook_, a field the plugin keeps about a page is protected and named _lh_. Both remain readable and writable over REST, where the permission check is unchanged. Plugin::maybe_upgrade() renames the rows of an existing installation, in order, so an installation from before 0.16.0 arrives at the current name in one run; the affected pages are dropped from the object cache afterwards, because the rows changed underneath it. Four tests cover the renames, that running the upgrade twice changes nothing, that an editorial field and another plugin's key are left alone, and that the moved keys really are protected.
* An import that runs into GitHub's request limit now stops instead of carrying on. GitHub allows 60 requests an hour without a login and says with every answer how many are left and when the count resets. Those two headers were never read, so an import that ran out of quota kept going and wrote an error onto every remaining page, leaving a handbook that looks imported and is not. It now stops on a whole page, reports how many pages it managed and when the limit comes back, and tells the screen not to ask again in the meantime. Nothing is lost: starting the import again updates the pages that exist rather than duplicating them. The headers come from api.github.com; the raw file host and the archive download have their own, opaque limits and report nothing, which is one more reason the archive path exists.
* Fixed: a link to a page that the import had not created yet was turned into plain text for good. Every page resolves its links the moment it is rendered, and a link with no target becomes text, which is what keeps a handbook free of dead links. During an import that rule fired too early: page one links to page two, page two does not exist yet, so the link was defused, and the closing pass, whose entire purpose is to resolve links once every page is there, found nothing left to resolve. Whether a link survived depended on the order of the work list. While an import is creating pages, an unresolved link is now left exactly as it is, and only the closing pass decides. Covered by a test that imports four pages in a ring, each linking to the next.
* The closing pass of an import runs in the same time budget as the import itself. It resolves the links of every imported page, which on a couple of thousand pages is tens of seconds of work, and it used to run in the request that had just spent the full import budget: the one request that has to finish, or the links stay raw. It is now the second phase of the same job and pauses between two pages exactly like the import does. The import screen says what it is doing, "checking the links on 240", instead of appearing to hang after the last page.
* An import no longer resolves each internal link with its own database lookups. Every .md link asked for its target by source path, then by slug, and the link text asked for the target's handbooks on top, so the closing pass of a folder import ran thousands of queries in the one request that has to finish for the links to work at all. The handbook is now read into lookup tables once per run, and the pages of the run are cached in one go. Measured on 200 pages with 5 links each: 5807 queries and 3.6 seconds became 3607 queries and 2.4 seconds. What is left is what wp_update_post costs per changed page, about six queries, not the links. Single pages converted on their own keep the old path, where one query beats reading the whole handbook.
* Internal: the navigation cache that never was one is out of the code. The version counter was bumped on every change and read by nobody, its docblock promised a cache for later, and uninstall deleted a transient prefix (lh_nav_) that has never once been written. The counter stays, because the area cards cache really does key off it, and it is now documented where it is actually used. Why the navigation is not cached is written down with the measurement instead of left open: its markup carries the current page and its open branches, so a cache would need an entry per page and per viewer, and what the navigation costs is the query behind it, 327 of 470 milliseconds on 2000 pages, which is shared with everything else that reads the handbook.
* Internal: the performance work has a measurement now, because optimising without one is guessing. bin/seed-performance.php seeds 2000 pages by default (300 hid exactly this class of bug), sets review dates so all three freshness states really render, defers term counting while it works and takes LH_SEED_RESET=1 to clear a previous run. The new bin/measure-performance.php renders the entry page, a single page and the navigation tree from the plugin's own block templates, cold and warm, and reports queries, time and every query that repeats, which is what makes an N+1 visible. Both are documented in CONTRIBUTING.md. AccessQueryCostTest pins the result: eight times the pages must not cost eight times the queries, and a guest still gets nothing out of a members handbook.
* Accessibility: an image or diagram that can be enlarged is now a real button (a diagram's button takes the full column, because a diagram is drawn to the width it is given and would otherwise collapse), so it is reachable with the keyboard, announces what it does and takes focus; the overlay no longer closes when the enlarged picture itself is clicked, and Tab stays inside it, which is what aria-modal already claimed. The page search no longer declares role=combobox with only an Escape key behind it: it is a list of links with arrow-key navigation, Escape to close, and a status line for the number of matches, so the results keep working as links. The result column of a handbook entry is no longer one live region around two dozen cards, which read the whole list again after every keystroke; a status line repeats the count sentence the list already shows.
* Corrected from the review: the two Table of Contents landmarks are not both exposed. The mobile and desktop variants are hidden by complementary media queries with display:none, so exactly one is in the accessibility tree at any width. No change was needed.
* The blocks now declare their own assets in block.json (editorScript, viewScript, style), so WordPress loads the handbook stylesheet and script exactly where a block is rendered. Before, one place decided from the current query whether this looked like a handbook view, which a block in a template part, a header or a footer is invisible to: such a block was rendered unstyled and without its script. The two shared handles are registered once, with their endpoints and labels attached to the handle rather than to one call site, and the editor bundle is named by the blocks instead of being enqueued on every editor screen from a global hook. A page with none of the plugin's blocks loads nothing.
* Internal: the handbook access form is covered by tests, ten of them. This is where the three term meta fields the whole access model reads are written, so the tests pin the closed side: an unknown or missing visibility falls back to members and never to public, a request without a valid nonce or without manage_categories changes nothing, only roles this site has are stored, people are resolved by login or id with unknown ones dropped and duplicates collapsed, and clearing the list clears it. The capability guard is counter-checked: removing it makes the test fail.
* Internal: the freshness rule and the page tree are covered by tests, fifteen of them. FreshnessStatus gains status(), the rule without WordPress and without the clock, so the boundaries can be pinned: on the due date a page still counts as reviewed, a second later it is due, and at twice the interval it escalates. A missing or unreadable date says nothing rather than "fine". PageTree is pinned on grouping by parent, ordering by menu order then title, publish only, one handbook at a time, and on the thing that matters most: it is read through the ordinary query, so a guest gets nothing out of a members handbook.
* Internal: MarkdownImportPage is covered by tests, twelve of them. The ZIP reading is extracted into read_zip() so its bounds can be tested without a real upload (entry count, per-file and total size, hidden and __MACOSX entries, a file that is not an archive), and the page-writing endpoint is pinned on the decisions that cost content: a re-import matches by source path and updates instead of duplicating, a different path is a different page, a pasted draft never overwrites anything, a slug match stays inside its handbook, a re-import keeps the publication status, the written content is sanitized on this path too, and a contributor cannot take over a page another author published.

## [0.55.0] - 2026-08-02
* A folder import no longer has to finish inside one request. After IMPORT_BUDGET seconds (20, capped at 60 percent of max_execution_time, filter living_handbook_import_time_budget) it stops between two pages, saves the rest of its work list in a transient and answers with a job id; the import screen asks again with that id until the queue is empty. Links are resolved once at the end, over every page of the run. A paused import keeps its archive download and reopens the same file, so the repository is fetched once for the whole import, not once per pass. A job belongs to the user who started it, and an archive nobody came back for is collected on the next scheduled sync.
* A folder import of 20 pages or more (ARCHIVE_THRESHOLD) reads the files from one repository archive download instead of one request per Markdown file and per image. The file list still comes from the tree API, so a small import keeps the per-file path and does not fetch the whole repository for a handful of pages. A failed archive download is not fatal: the import falls back to single requests and notes why it is slow.
* Fixed: a MkDocs project was imported flat, with file names as titles, whenever its mkdocs.yml configured a Python plugin. The recommended Mermaid setup writes !!python/name:pymdownx.superfences.fence_code_format, a built-in YAML tag no PHP parser resolves, so Symfony YAML threw and the whole file, nav included, was discarded without a word. The nav block is now read on its own when the file as a whole cannot be parsed. That case is not reported: the navigation arrived, which is all the import wanted from the file. Reported are the cases where the structure did not arrive, no nav, an unreadable file, no YAML reader on the server, so a flat import is never a silent surprise.
* Fixed: the media endpoint described every image of an internal handbook to anyone: an attachment takes its status from its parent, the parent is published, so title, alt text and file URL were handed out in the collection and in a single read. An attachment now inherits the visibility of the handbook page it belongs to. The file in wp-content/uploads is a separate matter, the web server delivers it without asking WordPress; see the FAQ.
* Translations: the German .po is complete (26 strings filled, 25 stale fuzzy entries reviewed and confirmed), and de_CH is added, derived from de_DE with the sharp s resolved to ss. Both locales ship .po, .l10n.php and the per-script JSON.
* The bundled Composer libraries are moved into LivingHandbook\Vendor\ by PHP-Scoper during the release build, so a second plugin shipping league/commonmark, symfony/yaml or enshrined/svg-sanitize in another version can no longer decide which copy the import uses. Only vendor/ is prefixed, never src/; the three places that name a library class ask the new Support\Vendored, which answers with the prefixed name in a release and the plain one in a development checkout. The build fails without PHP-Scoper (LH_SKIP_SCOPER=1 overrides it for local tests) and verifies afterwards that the prefix took and the libraries still work, see bin/verify-vendor-prefix.php.
* Internal: uninstall.php is covered by tests, seven of them, pinning what a deletion does and what it must leave alone; its own lookups are marked internal like every other maintenance query, so the content removal cannot be narrowed by the reader filter in a context where the plugin's hooks are registered.
* Internal: the release is gated behind the full check suite, the WordPress test matrix is pinned to 6.8 and latest, and the CI actions are updated.

## [0.54.0] - 2026-08-01
* A signed-in visitor who opens a handbook they may not read now gets an explanation and the status 403 instead of a 404. New setting Access, No-access page; the built-in message is filterable through living_handbook_access_denied_title and _message. The singular main query is no longer narrowed on pre_get_posts, because that produced the 404 before the guard could decide.
* Fixed: the app-handbook import publishes at once and now requires edit_others_posts, not edit_posts.
* Fixed: oEmbed described internal pages. The type is registered with embeddable => false (honoured from WordPress 6.8) and the lookup and response are filtered. Requires at least raised to 6.8.
* Fixed: the area-card cache is keyed per viewer.
* Fixed: a cross-handbook link keeps the file name as its text instead of the target title.
* Hardening: edit_others_posts on back-end AJAX reads, JSON_HEX_TAG on the export screen, is_uploaded_file on the ZIP import, auth_callback with the object id, export bundle in the system temp directory, no wp_cache_flush on uninstall, sanitize_callback on every REST-writable meta field.

## [0.53.0] - 2026-08-01
* Fixed: the scheduled sync skipped every handbook that is not public. Cron runs without a logged-in user, and the reader filter narrowed the maintenance lookup to public handbooks, so a members or restricted handbook was never updated, silently and without an error. Internal lookups now opt out of that filter; front-end reads stay filtered exactly as before.
* Fixed: an internal link on a page of a non-public handbook could be degraded to plain text during a sync and written back, only to reappear on the next manual sync.
* Fixed: re-importing into a non-public handbook through the REST import endpoints created duplicate pages instead of updating the existing ones.
* Internal: the access layer gained AccessController::internal(), which marks the plugin own lookups, plus integration tests covering the scheduled sync across all three visibilities.

## [0.52.0] - 2026-07-28
* Mermaid diagrams can now be clicked to enlarge in the lightbox, like the images. The enlarged diagram gets a light backing so its lines and text stay readable on the dark overlay.
* The bundled app handbook now sits under a single top page, "Living Handbook", with every area and page nested beneath it, one clean tree instead of many top-level entries.
* Fixed the entry filter list on themes that render checkboxes as block or full-width elements: the checkbox is kept small and native, so its label stays beside it instead of dropping below or stretching across the column.
* During this cycle the entry list contrast was raised and then reverted, so the lists use the theme's own colours; tune them through the Custom CSS setting if you want more.

## [0.51.1] - 2026-07-27
* The bundled app handbook is now complete in German and available in English as well, translated from it. The English pages still carry the German screenshots for now; localized ones will follow.

## [0.51.0] - 2026-07-27
* Images in handbook content can now be clicked to enlarge, in a dark overlay like the core Image block's lightbox, closed by a click, the close button or Escape. This is a handbook-only feature because handbook images are stored as plain HTML, not Image blocks, so the core setting never reaches them. A raster image becomes clickable only when it is shown smaller than its real size, so small icons are left alone; an SVG is always clickable, since it stays sharp at any size.
* Mermaid diagrams now render on the bundled app handbook too. The script that draws them only loaded on GitHub-synced pages, so on a locally loaded app handbook (a WordPress-source page) the diagrams stayed as code. It now loads on any handbook page whose content holds a diagram.

## [0.50.4] - 2026-07-27
* The bundled app handbook was expanded: the German pages now carry screenshots and diagrams, and the text stresses more clearly that a page can be tailored in look and function through the blocks and templates. Reload the app handbook to get the new version.

## [0.50.3] - 2026-07-27
* An imported image is now attached to the page it belongs to, so the media library shows it as uploaded to that page instead of unattached. A shared image keeps the first page it landed on.

## [0.50.2] - 2026-07-27
* Imported SVG images now sideload even when the site does not allow SVG uploads: the plugin permits the SVG mime type only for the moment it stores its own already-sanitised SVG, not for user uploads in general. This is why the app handbook's diagram images did not appear.
* An image on a handbook page is now capped at the column width, so a large screenshot no longer overflows the page.

## [0.50.1] - 2026-07-24
* App handbook pages are now locked in the editor, like GitHub-synced pages: they are managed content that a re-load replaces, so editing them by hand would only be lost on the next load. A notice on the page says so.
* Fixed a relative image reference with a percent-encoded path (a space as %20) not resolving to the file on disk, so such an image is now sideloaded too.

## [0.50.0] - 2026-07-24
* The app handbook now ships with the plugin instead of loading from GitHub, so it always matches the installed version and no install depends on a repository staying reachable. The "App handbook" tab imports it from the bundled folder; loading again after a plugin update refreshes the pages. A fork can still point the tab at a GitHub repository through the living_handbook_app_handbook_url filter.
* The GitHub folder import now brings images along: an image a page references by a relative path (like ../assets/x.svg) is fetched from the repository and sideloaded into the media library, so it is no longer a link that 404s on the site. The same happens on every later sync, and shared images are stored once.

## [0.49.0] - 2026-07-24
* Public feedback: a new setting lets logged-out visitors vote "Was this helpful?" on public pages. To stay privacy-friendly it stores nothing personal, no cookie, no IP, no identifier, so the same visitor can vote again after reloading; the trade-off is no per-person limit. Off by default. On internal pages, logged-in users still vote once each regardless of this setting.
* Feedback can be reset per page: a "Reset feedback" action in the handbook list clears a page's counters, for a page reworked after weak feedback.
* The URL bases of a handbook page and a handbook grouping (/handbook/, /handbook-set/) can now be changed with two filters, living_handbook_post_type_slug and living_handbook_taxonomy_slug, for a site that needs a localized base. Changing them on a live site rewrites URLs and needs the permalinks flushed.
* New handbook pages default to comments closed, so a handbook is not a comment thread unless you want one. Imported pages, the app handbook included, are created with comments off; switch them on per page in the Discussion panel.
* Documented how to detach a GitHub-synced page and keep it in WordPress: switch its Source to "Maintained in WordPress" and the content stays, the sync stops and the editor unlocks.

## [0.48.0] - 2026-07-24
* Import: a "## Transport-Metadaten" heading inside a fenced code block is no longer mistaken for the page's own transport block, so a page documenting the metadata format keeps its code block instead of being cut off there.
* Import: MkDocs admonitions ("!!! note", "??? tip") now become a blockquote led by the title, instead of collapsing into stray text with lost indentation.
* The GitHub source note now links to the source file on GitHub, next to the "maintained on GitHub" line.
* Handbook tables render with visible lines between rows and columns, a wider first column and top-aligned cells, so an imported Markdown table reads as a table; a wide code block scrolls instead of stretching the page.
* The handbook navigation takes the surface background, matching the cards and the on-this-page box, and a separator line sets off the "Was this helpful?" block.

## [0.47.0] - 2026-07-24
* The import screen now shows its notes (links that resolved to no page, or an import cut short by a limit) in a highlighted block above the page list, instead of a plain line at the end where a large import buried them.

## [0.46.0] - 2026-07-24
* Internal links can no longer become 404s, by two mechanisms. First, a folder import now records each page's repository path, so an internal .md link resolves to its page exactly by path, regardless of slug; this fixes links to a folder's README, whose page takes the folder name as its slug, not "readme". Second, a link that still resolves to no page is turned into plain text instead of a raw .md link: the text stays, the dead link cannot reach the browser, and the link comes back on its own once the target page exists. The import still lists every such link so a typo or missing page stays visible.

## [0.45.0] - 2026-07-24
* Fixed internal links turning into 404s on GitHub-sourced pages. The import resolved a page's internal .md links to real pages, but every later sync re-rendered the Markdown and left the links raw again, so the first scheduled sync after an import broke every cross-link. The sync now resolves the links too, the same way the import does.

## [0.44.0] - 2026-07-23
* The GitHub folder import now reports internal .md links that point at no page. After it wires up every link whose target exists, anything left pointing at a .md file is a dead link, a typo or a page not yet written, and the result list names the page it is on and the file it points at. This makes a large handbook with many cross-references far easier to keep whole.

## [0.43.0] - 2026-07-23
* The GitHub source note ("This page is maintained on GitHub…") now reads as a note rather than body text: smaller, muted, with a subtle accent bar.
* Fixed a self-contradiction in the developer docs, where the limits section still said the folder import does not descend into subfolders, which it has since 0.40.0.

## [0.42.0] - 2026-07-23
* The GitHub folder import now respects the transport "Reihenfolge" of a page for its order, instead of overwriting it with an automatic value. Number only the pages whose order matters, keep the numbers small; everything else falls back to its import position and sorts after them. An area's order lives in its README.
* An index or README file that stands for a folder now takes its slug from the folder name, so an area page gets a clean URL instead of "readme".
* The app handbook is published on load rather than left as drafts. It is curated, editor-locked content, and its visibility is governed by the handbook it lands in, so a "members" handbook keeps it behind the login. Any other GitHub import still stays a draft for review.

## [0.41.0] - 2026-07-23
* The app handbook now comes from GitHub instead of being shipped inside the plugin. The documentation of the app lives in a public repository, so it has one source, can be read and edited where it is written, and every install pulls the current state instead of a snapshot frozen at release time. The import screen keeps its "App handbook" tab and one-click button; behind it is an ordinary GitHub folder import against a fixed URL, chosen by the admin language.
* This drops the bundled example handbook added in 0.39.0. A separate demo made little sense next to real documentation: a good handbook explains the app well enough that the reader can try it, the same way any application's manual does. The bundle export and import between sites is untouched; only the shipped copy is gone.

## [0.40.0] - 2026-07-22
* The GitHub folder import now descends into subfolders, and the folder structure becomes the page hierarchy. Previously only the files directly in the chosen folder were imported and everything landed side by side.
* A folder becomes a page: an index.md or README.md inside it becomes that folder's own page and its siblings hang under it; a folder with neither gets a page made from its name, carrying the area entries block, so a level that exists in the repository does not go missing from the navigation.
* The whole repository tree is now read in a single request to the Git trees API instead of one request per folder. Unauthenticated GitHub allows 60 requests an hour, so the old approach would have run out on any real documentation repository. At most 200 files are imported at once, and the result says so when the limit or GitHub's own tree limit was hit.
* On a re-import the repository decides the structure again, the same way it already decides the content of a synced page.

## [0.39.0]
* New: the plugin brings its own handbook along. Nine pages in three areas that document how a Living Handbook is built, written as a working handbook so it is at the same time an example: several page types, filled vocabularies and pages in every review state, so the page type, the filters and the freshness badges can be seen doing their job instead of being described. It is meant to be taken apart: read it, change it, delete it.
* It is loaded on request, on the import screen under the new tab "App handbook", and never on activation. Content that appears by itself is content nobody asked for. By default it goes into a handbook of its own with visibility "members", so nothing becomes public, and loading it twice never overwrites an edit. You can also load it into an existing handbook. The notice after activation points at it, because an empty install cannot show what a page type or a freshness badge is for.
* It can carry images: they ship in the plugin, are sideloaded into the media library on load, and are recognised again by a content hash so a second load does not duplicate them.
* It exists in English and German and follows the admin language. Its vocabulary terms attach to the seeded ones instead of creating a second set next to them, and its review dates are relative to the moment it is loaded, so it does not turn entirely overdue as the release ages.

## [0.38.1] - 2026-07-22
* Test quality: the new REST access tests got a counter-check. A test that only asserts "the answer does not contain this" passes just as happily when the answer is empty for everyone, which would prove nothing. The counter-check confirms that a content manager does get the content over the same four routes, so the negative tests fail if the routes ever stop returning anything.

## [0.38.0] - 2026-07-22
* Privacy: an export no longer writes the list of individually allowed people into the bundle. Those are e-mail addresses, and a bundle is a file that gets downloaded and passed on; the target site has its own users anyway. Visibility and allowed roles still travel. If a handbook is restricted to named people, set them again after importing.
* The separation of internal content over the REST API is now covered by tests: a logged-out guest and a subscriber get nothing from a members-only or restricted handbook, over the plugin's own filter and search routes as well as the core collection and single-item routes. Those two plugin routes are open on purpose so a public handbook stays searchable; the docblock now says so, and the tests make sure the checks inside them cannot quietly go missing.

## [0.37.0] - 2026-07-22
* Security: content that arrives already converted to blocks is now cleaned before it is stored. This closes a hole in the bundle import, where a prepared bundle could have carried active markup into the handbook and run it in the browser of every logged-in reader. Cleaning runs block by block, so the block structure is untouched; scripts, event handlers and unsafe URLs are removed. The same cleaning now also runs on the import screen's create endpoint, so it is a property of writing rather than of converting.
* This corrects an earlier decision. The bundle import used to insert content as it came, on the argument that block markup cannot pass through the HTML filter and that the required role was safeguard enough. Both were wrong: parsing the blocks first solves the first, and on a single site editors and administrators hold unfiltered_html, so the core filter does not run for exactly the people who may import. Found by a security review of 0.36.0.

## [0.36.0] - 2026-07-22
* Export moved to its own screen under Handbook, Export, the way WordPress keeps its own import and export tools apart.
* The import screen now holds everything a source needs inside that source's own tab: its field, its options and its import button. Nothing from another source is on screen, and there is only ever one button.
* Importing a bundle is now one of those tabs, next to pasted text, a ZIP and GitHub, instead of a separate block at the bottom of the page.
* The German translation of the export and import screens is complete again; the new strings had been left untranslated while the layout was still moving.

## [0.35.0] - 2026-07-22
* Tests for the bundle export and import: a round trip into another handbook, each of the three conflict rules, the protected flag, the vocabularies travelling with a page, and an area export carrying only its own subtree.
* Fixed, found by those tests: when a page was matched by slug, the restriction to the target handbook did not apply, because a query by name is treated as a lookup for a single page. An import into one handbook could therefore match a page of the same slug in a different handbook and, depending on the rule, skip or overwrite it. Both the bundle import and the Markdown re-import used that lookup and are corrected.

## [0.34.0] - 2026-07-22
* The bundle import can now be pointed at an existing handbook instead of the one named in the bundle. The chosen handbook keeps its own access configuration.

## [0.33.0] - 2026-07-22
* New: import a bundle. Upload a bundle exported from another site and choose what happens when a page already exists: skip it (the default, never overwrite), update it, or always create a new one. A page marked as protected is never overwritten either way, and nothing is ever deleted.
* On update the local upkeep stays put: the feedback counts and the review date, interval and reviewer belong to this site. A page created by the import does take those from the bundle.
* An imported handbook that did not exist here is created with visibility "members", even when the bundle says public, so an import can never silently publish content. An existing handbook keeps its own access configuration.
* Internal links between the imported pages are rewired to the new pages, GitHub-sourced pages resume syncing from their repository, and media travels with the bundle. Importing requires the content-manager role.

## [0.32.0] - 2026-07-22
* The export picker now works in two dependent steps: choose the handbook, and the second field lists only that handbook's areas. Previously it offered every handbook's areas at once, which is unusable once a site has more than a few.

## [0.31.0] - 2026-07-22
* Export now also does a single area: pick a top-level page and export just it and its subpages, instead of the whole handbook. The bundle still carries the handbook's configuration.

## [0.30.0] - 2026-07-21
* Housekeeping: the review-status filter no longer sets suppress_filters on its internal lookup, which the Plugin Check flags. No functional change.

## [0.29.0] - 2026-07-21
* New: export a handbook as a self-contained bundle (a ZIP with a manifest and the media) from Handbook, Import, at the bottom of the page. First half of the export/import feature; the matching import follows. The bundle carries the pages, the handbook configuration, the vocabularies and the freshness data, and keeps GitHub-sourced pages pointing at their repository. Requires the content-manager role.

## [0.28.0] - 2026-07-21
* The block editor now loads the handbook stylesheet only when you edit a handbook page, not in every editor. No visible change on handbook pages; other post-type editors just load a little less.

## [0.27.0] - 2026-07-21
* The handbook list now has a filter dropdown for every vocabulary too: page type, topic, responsibility and audience, next to the existing handbook and source filters, the same way the category filter works for posts. Filtering by taxonomy is standard; sorting the taxonomy columns stays off, because a page can belong to several terms.
* A review-status filter (reviewed, due, overdue, never reviewed) narrows the list by freshness. The Last reviewed column keeps sorting by date; the status, which also depends on each page's review interval, is now a filter of its own.
* Fixed the Last reviewed sort: pages without a review date used to split to both ends of the list. They now stay grouped and always follow the dated pages, in both directions.

## [0.26.0] - 2026-07-21
* The handbook list now has a Feedback column that sorts by net feedback (yes votes minus no votes), so the best and worst received pages are one click away.
* Two filter dropdowns above the handbook list: filter by handbook, and by source (GitHub or WordPress). Taxonomy columns stay unsorted on purpose, because a page can belong to several handbooks; filtering is the reliable way to group them.
* The two warnings on the handbook list, pages without a handbook and GitHub pages that failed to sync, now list the affected pages as direct links, so you reach each one in a click instead of hunting for it.

## [0.25.0] - 2026-07-21
* Fixed: the import progress messages ("Creating 3 pages", "2 links converted" and the like) are translatable again. Seven plural strings were calling the translation function without the plugin's text domain, so on a German site they stayed English; they now read in the site language.
* Access: back-end tools that run over admin-ajax, such as the classic editor's "link to existing content" search, again find handbook pages for users who may edit posts. The frontend visibility rules are unchanged; a page's content stays guarded, and comment visibility keeps its stricter rule.

## [0.24.0] - 2026-07-21
* Every handbook block now offers an HTML anchor and an additional CSS class under the block's Advanced panel. The anchor becomes the id of the block's root element and the class is added to it, so you can link to a block or target a single instance from the Custom CSS field or your theme.

## [0.23.1] - 2026-07-21
* Packaging: the build now strips every hidden file from the release ZIP, so a stray .fuse_hidden orphan from a network or FUSE mount, which the plugin repository check rejects, never ships. Silenced a false-positive lint warning on the one-time feedback-meta migration. No functional change.

## [0.23.0] - 2026-07-21
* Code review F7 completed: the Mermaid diagram block and the GitHub source-note block now also take their metadata from a block.json file, so every handbook block uses the same single-source registration. The render callbacks are unchanged, so nothing changes on the page. The Mermaid block shows a sample diagram in the inserter preview, and an empty Mermaid block no longer loads the diagram library until it has code.

## [0.22.0] - 2026-07-21
* Code review F7: the nine handbook blocks now take their metadata (title, category, icon, attributes, supports, keywords) from a block.json file each, a single source, instead of duplicating the definitions between the PHP registration and the editor script. The server render callbacks are unchanged, so nothing changes on the page, and the blocks gain a preview in the inserter.

## [0.21.0] - 2026-07-21
* The ZIP import's uncompressed size limit is now adjustable in code through the `living_handbook_zip_max_bytes` filter (default 100 MB), and the "too large" message reflects the active limit. It stays a safety limit; the real ceiling is the server's PHP upload and memory configuration.
* Code review F9: the navigation-injection helpers use the HTML API (WP_HTML_Tag_Processor) to add classes instead of regular expressions on core markup, so a change to the core navigation block's attribute order no longer silently breaks the handbook submenu. Inserting the submenu container stays a string operation, which the HTML API does not cover.

## [0.20.0] - 2026-07-21
* Code review round 3. Import errors that abort a whole operation (CommonMark missing, an unreadable or oversized ZIP, a GitHub API failure) are now returned as WP_Error with an HTTP status, so a failure shows up as a failed request in logs and dev tools instead of a 200 with an error field. Per-page failures within a batch still list the page and let the rest continue.
* Imported images are reused only when the plugin imported them before and their content is unchanged (matched by an import marker and a content hash), so a foreign upload that happens to share a file name is never picked up, and an updated source image is re-imported.
* The result count and pagination on a handbook now reflect the pages actually shown after the access filter, so the number matches the cards on a single page.
* The ZIP import limit is raised to 100 MB uncompressed. A GitHub file URL that cannot be fetched now reports the error on the import screen and creates no page, instead of leaving an empty draft that only reveals the sync error once opened.

## [0.19.0] - 2026-07-21
* Access-control hardening (code review F1): a coarse pre_get_posts layer now restricts handbook queries to the handbooks the current user may view. A third-party query that sets suppress_filters (the get_posts default), or a front-end read over admin-ajax, can no longer list the titles or excerpts of handbooks the user may not read. The precise, fail-closed per-page check on the display path is unchanged; this closes the two channels that bypassed it.

## [0.18.0] - 2026-07-21
* Security hardening from the 0.16.0 code review. GitHub fetches now use wp_safe_remote_get with redirects disabled, so a redirect cannot lead the sync to an unchecked host. Imported SVG images are sanitised (enshrined/svg-sanitize) before they reach the media library. The HTML sanitiser no longer allows raw input elements from imported Markdown.
* Fixed the background sync follow-up: a large sync continues on its own one-off event instead of a guard that never matched, so a full pass no longer stalls until the next scheduled run.
* Uninstall uses the plugin's own option-name constants and flushes the object cache, so versioned caches are also cleared on sites with Redis or Memcached.
* Housekeeping: the build verifies that the version matches in the header, the constant and the readme, and a redundant weekly-schedule shim was removed (WordPress ships it since 5.4).

## [0.17.0] - 2026-07-21
* Internationalisation of the JavaScript now follows the WordPress standard: translations load through wp_set_script_translations() and per-script JSON files generated from the .po at build time, replacing the previous hand-maintained bridge and its two string lists.
* The importer's counts use plural forms (_n): pages, images, drafts and converted links each read correctly in the singular or plural, in German and any other language.
* No visible change on an English site. On a translated site the block editor labels, the import screen and the progress messages read correctly.

## [0.16.0] - 2026-07-21
* Appearance: the handbook now follows your theme's colours. Surfaces, text and accent default to the theme's colour presets, and borders and secondary text are derived from them, so a dark theme, or a dark style variation a visitor selects, turns the cards, navigation and table of contents dark on their own. The stylesheet is consolidated and its breakpoints are documented.
* The entry search and the on-page search field now take the surface colour with a thin border, matching the navigation and the table of contents, and stay legible on a dark theme.
* Quick Edit: the last-reviewed date, the reviewer and the review interval can be set straight from the handbook page list, without opening each page.
* The metadata footer places each person below its date instead of beside it.
* Performance: the navigation and the area tiles load a whole handbook in a single query instead of one query per branch, and the shared list of readable handbooks is built in one place; the navigation cache is refreshed only for handbook changes.
* Continuous integration runs the integration test suite automatically on push and pull request; the database service, WordPress core and the test config are set up in the workflow.
* Privacy and housekeeping: the feedback counters use hidden meta keys (migrated automatically), the readme notes that a logged-in voter's user ID is stored, and the GitHub sync clears all of its scheduled events on deactivation.
* Inserter and internationalisation: the handbook blocks carry a description and search keywords, and small fixes ("OK" now shows the sync date, the sync-failed marker is translatable).

## [0.15.0] - 2026-07-20
* The import screen is reorganised into two steps: choose the target handbook, then pick one source in a tabbed switcher (paste, ZIP, or GitHub). Only the chosen source is shown, with a single import button, and the explanation moved into a collapsible help section.
* New "Handbook search" block: a search-as-you-type box for a single page. It shows matching pages as links, so the visitor jumps straight there without leaving the page.
* New "Custom CSS" field on the settings page: style the handbook from the plugin instead of the theme, so the styling is removed when the plugin is deleted.
* The default background sync frequency for a new install is now weekly (was daily). Existing sites keep their configured setting.
* The handbook admin menu shows a divider between the three usage pages and the six configuration pages.
* Polish: the import explanation is now a screen Help tab; live search on the entry page is debounced and shows real results (title and body matches); importing without a target handbook asks first; the review column is sortable and shown in the site date format; the overdue dashboard caps its list with a link to all pages; and small controls meet the minimum touch-target size.

## [0.14.0] - 2026-07-20
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

## [0.13.0] - 2026-07-17
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

## [0.12.0] - 2026-07-14
* Interface polish: the admin menu is ordered and clearly labelled, taxonomy edit screens use the right labels, the handbook list shows each handbook's access, the on-this-page block is titled "Table of Contents", and the overview and entry blocks can show their items as cards or as a list. Block controls that had no effect were removed, and a body class lets a site style the handbook without affecting the rest.
* Facets now filter without a page reload through an access-checked REST endpoint, so the separate filter button is gone; search still works without JavaScript.
* New "Handbook menu" block that lists the handbooks a visitor may read, collapsing behind a toggle on small screens. Optionally, the accessible handbooks are injected into a core Navigation block, or one of its submenus, that carries the "has-handbook-menu" class, so they ride the theme's own navigation and mobile menu.
* Settings: an uninstall option to keep or delete all handbook content (including templates you edited in the Site Editor). Sync failures are surfaced as an admin notice.
* The duplicate post type archive was disabled; the overview lives on a normal page holding the overview block.
* Internationalisation: the editor labels and the import screen's status messages are now translatable in any language (English source), and the build generates the translation template (living-handbook.pot).
* Renamed the GitHub source meta keys to the living_handbook_ prefix for clean, unique naming.
* Disclosed the bundled third-party libraries in the readme (mermaid.js 11.16.0 MIT, league/commonmark BSD-3-Clause, symfony/yaml MIT).

## [0.11.0] - 2026-07-12
* GitHub sync (concept 06, way 1): a page can carry a Markdown source URL and is pulled on save, via Sync now, and on a schedule set on a new settings page (off, hourly, twice daily, daily, weekly; default daily). Its content editor is locked, the page overview shows the source, and a placeable block marks the public page. A whole GitHub folder can be imported from a tree URL via the contents API.
* Security: the handbook content type is now registered non-public (kept out of the XML sitemap, feeds and oEmbed) while single pages and the archive stay reachable and access-guarded. The feedback endpoint now requires a logged-in user and counts one vote per user and page. Synced GitHub HTML is filtered through wp_kses before it is stored.
* Robustness: the Markdown source is limited to an allowlist of hosts (raw.githubusercontent.com, filterable) to prevent server-side request forgery; the scheduled sync runs in bounded batches instead of pulling every page in one run; the folder import reports a GitHub API rate limit clearly; a versioned upgrade routine runs migrations after an update; uninstall.php clears the plugin's options and caches (content removal is opt-in via a filter). Added an "Area overview" page type for area start pages.
* The build now ships the production Composer dependencies (vendor/) so import and sync work in an installed copy.

## [0.10.0]
* Markdown import overhaul: one import UI (paste, ZIP, or a GitHub file/folder URL), support for github.com blob URLs and raw URLs, whole-folder import, and MkDocs nav-driven structure from mkdocs.yml (titles, order, parents). Transport metadata and README are applied on import and sync; internal .md links and their visible titles are resolved across a directory import; Mermaid diagrams and collapsible details render correctly. New blocks appear under a "Living Handbook" category.

## [0.9.0] - 2026-07-08
* Navigation is now available on handbook entry pages as well as single pages, and offers a Menu or Accordion display.
* Feedback and metadata are now two separate blocks.
* On-this-page is a collapsible box covering H1 to H6, with a depth setting (overridable per page), a mobile placement above the content, and smooth scrolling.
* The metadata block can show or hide the people (avatar and name); the top navigation item is bold and adjustable via CSS.
* New documentation for the blocks and templates, with CSS customization examples.

## [0.8.0]
* The navigation is rendered as a core Navigation block with a VSN block style, built fresh per handbook from the page hierarchy. Removed the single global generated menu.

## [0.7.0]
* Multi-handbook structure: the handbook archive is a chooser of readable handbooks; each handbook has its own entry page (term archive) with search, taxonomy filters, area tiles and recently updated cards.
* Single-page template with navigation, badges, an on-this-page column, feedback and a metadata footer, provided as block templates.
* Access is enforced on the handbook entry pages as well as single pages.

## [0.6.0] - 2026-07-07
* Fixed: the frontend stylesheet now loads on the overview page, so the cards are styled.
* Navigation menu generated from the page hierarchy, ready to be styled by the VSN plugin.

## [0.5.0] - 2026-07-07
* Frontend design following the prototype: overview cards with a freshness dot, a bordered navigation tree, and styled metadata footer, badges and feedback.
* Colours exposed as CSS custom properties for theme adaptation; see https://github.com/rfluethi/living-handbook/blob/main/docs/customization.md.

## [0.4.0] - 2026-07-07
* Overview and navigation blocks, and a "Living Handbook" block category.

## [0.3.0] - 2026-07-07
* Maintenance dashboard widget and handbook list columns.

## [0.2.0] - 2026-07-07
* Access configuration UI, maintenance metadata, freshness status, feedback counter, default frontend rendering, and a German translation.

## [0.1.0] - 2026-07-06
* Initial scaffold, data model, frontend access control, and internationalisation.
