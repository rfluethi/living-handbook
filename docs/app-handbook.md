# Writing the app handbook

How the handbook that ships with the plugin is made, and where its parts live. This is for whoever writes or extends that handbook, not for people using the plugin; the user-facing description is in [import and sync](import-and-sync.md).

## The short version

| What | Where |
| --- | --- |
| The content, in both languages | `bin/build-app-handbook.py` |
| The generated files | `assets/app-handbook/app-handbook-en.json`, `-de.json` |
| Images | `assets/app-handbook/media/` |
| The loader | `src/Import/AppHandbook.php` |
| The tests | `tests/Integration/AppHandbookTest.php` |

Write in the Python file, run it, run the tests:

```
python3 bin/build-app-handbook.py
composer test
```

`bin/` is not copied into the release zip, so the generator is a repository tool, not part of the plugin. Everything under `assets/app-handbook/` does ship.

## Why a generator and not just JSON

The pages are stored as Gutenberg block markup, which is HTML wrapped in HTML comments. Putting that into JSON string literals by hand means escaping quotes inside comments inside strings, and a single wrong escape produces a page that imports without error and renders as garbage. The generator has one small helper per block type, so the markup is produced the same way every time.

The JSON files are build output. Do not edit them; the next run overwrites them.

## The format

Each file is a Living Handbook bundle, the same format `HandbookExport` writes, minus the ZIP wrapper and minus the `media/` folder inside it. That is deliberate: the loader can hand it straight to `HandbookImport::import_manifest()`, so shipped content uses the ordinary import path and there is no second one to keep correct.

A page entry looks like this:

```json
{
  "key": "getting-started/writing-a-good-page",
  "origin_id": "app-handbook:getting-started/writing-a-good-page",
  "parent_key": "getting-started",
  "order": 20,
  "title": "Writing a good page",
  "slug": "writing-a-good-page",
  "status": "publish",
  "source": "wordpress",
  "content": "<!-- wp:paragraph -->…",
  "terms": { "handbook_type": ["guide"], "…": [] },
  "meta": { "review_days_ago": 200, "review_interval": 180 }
}
```

The `key` is the identity of a page across loads and the path in the hierarchy: a key with a slash hangs under the key before the slash. `origin_id` is what a second load matches on, so never renumber it casually; changing it means the old page is no longer recognised and a duplicate appears.

## Two rules the content must follow

Both exist because the plugin is translated, and both are easy to get wrong in a way that only shows on a German site.

**Vocabulary terms are referenced by token, never by slug.** The seeded terms (Guide, Process description, All members, …) are translated when they are created, so their slugs depend on the site language: `guide` on an English site, `anleitung` on a German one. A file with fixed English slugs would quietly create a second set of terms next to the real ones. `AppHandbook::tokens()` maps a token to the translated name and looks up the term that actually exists. **A token added in the generator must be added there too**, otherwise it is silently dropped.

**Review dates are an age in days, not a date.** `AppHandbook::resolve_meta()` turns `review_days_ago` into a date at load time. A fixed date would make every page overdue a year after release, which is exactly the wrong first impression of a feature whose point is staying current. Spread the ages so the handbook shows all four states: reviewed, due, overdue, and a page with no review data at all.

## Adding a page

1. Add the text to `EN` and to `DE` under the same key. Both languages must have every key.
2. Add a line to `STRUCTURE`: key, parent key, order, page type token, audience tokens, topic tokens, review age in days, review interval in days. Use `None` for the last two if the page should carry no review data.
3. Optionally add an entry to `ROLES` for the responsible role.
4. Run the generator and the tests.

The two languages must describe the same structure: same keys, same parents, same order, same terms, same review ages. Only the prose and the handbook name differ. `test_both_languages_have_the_same_structure` fails if they drift apart, which is the failure this whole arrangement is most prone to.

## Adding an image

1. Put the file in `assets/app-handbook/media/`.
2. Reference it in the page body with `img("name.png", "alt text")`, optionally with a caption.
3. Run the generator. It fails if the file is not there.

Constraints worth knowing before you produce fifty screenshots:

- **PNG or JPEG, not SVG.** WordPress rejects SVG uploads by default, so a shipped SVG would silently never reach the media library and the page would show a broken image.
- **Two files when translated interface text is visible.** The admin interface is translated, so a German screenshot is not an English one. Name them per language and reference the right one in each language block.
- **Crop tightly.** One panel or one meta box, not a full browser window. Smaller in the zip, readable on a phone, and it goes stale more slowly because less of the interface is in the picture.
- **Alt text is an argument, not an option.** A screenshot without one tells a screen reader nothing.
- **Diagrams do not need images.** The Mermaid block draws them from text, which is translatable, has a written description, and never goes stale.

Under the hood the generator writes a `media` list into the manifest and a placeholder URL (`lh-app-handbook://name.png`) into the content. On load, `HandbookImport` sideloads each file and replaces the placeholder with the URL of the local copy, before the content is sanitised. Sideloading recognises a file again by a content hash, so loading the handbook twice does not duplicate images.

One consequence to be aware of: sideloaded images become attachments in the media library and **stay there when the handbook is deleted**. "Delete the handbook and it is gone" holds for the pages, not for the pictures.

## What the loader does beyond a plain import

`AppHandbook` is deliberately thin. It reads the file for the current admin language (English as fallback), resolves the term tokens and the review ages, and hands the result to `HandbookImport` with the rule "skip existing pages" — always, so a second load can never overwrite an edit someone made while trying the handbook out. The target handbook comes from the form: a handbook of its own by default, or one that already exists.

Media is read through `AppHandbook::read_media()`, which resolves the path and checks it stays inside `assets/app-handbook/`. The manifest is shipped, so that path is not user input today; the check is there because a reader that returns whatever path it is handed is the kind of thing that becomes a hole the moment someone reuses it.

## Testing

`tests/Integration/AppHandbookTest.php` covers the parts that no build step would catch, because content in a file rots differently from code:

- both language files describe the same structure;
- a load creates all pages with the hierarchy intact, in a handbook that is not public;
- it can be loaded into an existing handbook, whose access configuration is left alone;
- the review states are relative to the load, and all four occur;
- term tokens attach to the seeded vocabulary instead of creating a second set;
- loading twice creates nothing and keeps a hand-edited title;
- media is read only from the plugin's own folder, and every image named in a manifest is actually shipped;
- the content survives sanitisation with its block delimiters intact.
