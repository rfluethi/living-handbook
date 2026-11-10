# Move a handbook

You want to move a handbook to another website? Export it as a bundle and import that bundle on the other site. This also works for a single area. Requirement: Living Handbook runs on both websites.

<details>
<summary>Concept: What a bundle is</summary>

A bundle is a single ZIP file. It holds the handbook's pages: all pages with their order, the images, the classification (page type, topic, responsibility, audience), the review data and the visibility setting. The target site needs no contact with the old site. Two things are missing on purpose. First, the individually allowed people, because that is personal data, and a bundle is a file that gets passed around. Second, the feedback numbers, because they belong to the old site.

The handbook's [learning paths](learning-paths.md) travel along too. On the target site their lessons point at the imported pages. If you export only an area, a path keeps only the lessons in that area; a path without a lesson in the area stays behind.

</details>

## Exporting

1. Open **Handbook → Export**.
2. Choose the **handbook**. The handbooks you may read are offered. In the second field you can then narrow down: the whole handbook or just one area. An area exports together with its subpages. An area you may not view cannot be exported.
3. Click **Export bundle**. The ZIP file downloads.

## Importing

1. On the target site, open **Handbook → Import**, tab **Bundle**. Your account there needs at least the WordPress role Editor, because the import also changes pages by other authors.
2. Upload the ZIP file.
3. Choose what should happen to pages and learning paths that already exist there:
   * **Skip** (default): Existing pages are left untouched. Only new ones are created.
   * **Update:** Existing pages are refreshed from the bundle.
   * **Always create:** Every page in the bundle becomes a new page. Useful for cloning.
4. Under **Import into**, choose which handbook the pages go to. The handbooks you may read are offered. Without a choice, the handbook from the bundle is created anew.
5. Start the import and read the report. It lists what was created, updated or skipped, for pages and learning paths separately, and names the plugin version the bundle was made with.

## Result

The pages and the learning paths are on the target site. Links between the pages lead to the new pages. Two protection rules always hold: nothing is ever deleted. And a page marked as protected on the target site is never overwritten.

If the import creates a new handbook, it takes the version from the bundle. An existing handbook keeps counting its own version, see [The version of a handbook](../upkeep/the-version-of-a-handbook.md).

<details>
<summary>Pitfalls: Safety beats convenience</summary>

* **An imported handbook always starts with visibility "All members".** That holds even if it was public on the old site. An import must never publish anything by accident. Raise the visibility by hand afterwards.
* **Individually allowed people must be entered again.** They do not travel along.
* **The files belong to their pages afterwards.** Each file from the bundle is attached to the first imported page that shows it. If the handbook is not public, it lands in the protected folder right away, see [Protect files](../access/protect-files.md).
* **You do not import into a handbook you may not read.** If the bundle's handbook already exists on the target site and you may not read it there, the import stops with "You are not allowed to import into this handbook." before it touches a page. An update would otherwise overwrite pages you may not see. Choose another target or ask someone who may read that handbook.
* **An existing page you may not edit is left alone**, even with **Update**. Nor does it serve as a parent or a lesson. The report says how many, for learning paths too.
* **An import as ordinary WordPress pages follows the run rule too.** A later run finds the pages it created and skips or updates them instead of adding new drafts. It knows them by their origin in the bundle, not by name: another page that happens to share the name is left alone.
* **A learning path loses lessons that are not in the bundle.** They drop out of the path, and the report points this out. If you import the bundle as ordinary WordPress pages, no paths come along at all.
* **Learning paths appear only with the module.** If the learning paths module is off on the target site, the paths are imported anyway. They become visible as soon as you switch it on under **Handbook → Settings → Learning paths**.
* **On update, the target site's upkeep data survives.** Feedback numbers and review data are not overwritten.
* **Read bundles from others before you publish them.** The content is cleaned on import like any external content. But it remains a text someone else wrote.

</details>

## Related pages

* [Import Markdown](import-markdown.md)
* [Hand a handbook on as a website](export-as-a-website.md)
* [Set visibility](../access/set-visibility.md)

## Transport-Metadaten
* Seitentyp: Guide
* Verantwortliche Rolle: Handbook editors
* Thema: Content
* Zielgruppe: All members, Tech
* Eltern-Seite: Content
* Reihenfolge: 4
* Textauszug: A handbook can be exported as a self-contained bundle and imported again on another website; here is how.
* Letzte Aktualisierung: 2026-11-10
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 90 Tage
