# Move a handbook

You want to move a handbook to another website? Export it as a bundle and import that bundle on the other site. This also works for a single area. Requirement: Living Handbook runs on both websites.

<details>
<summary>Concept: What a bundle is</summary>

A bundle is a single ZIP file. It holds everything that makes up the handbook: all pages with their order, the images, the tags, the review data and the visibility setting. The bundle is complete in itself. The target site needs no contact with the old site. Two things are missing on purpose. First, the individually allowed people, because those are e-mail addresses, and a bundle is a file that gets passed around. Second, the feedback numbers, because they belong to the old site.

</details>

## Exporting

1. Open **Handbook → Export**.
2. Choose the **handbook**. In the second field you can then narrow down: the whole handbook or just one area. An area exports together with its subpages.
3. Click **Export bundle**. The ZIP file downloads.

## Importing

1. On the target site, open **Handbook → Import**, tab **Bundle**. Your account there needs at least the WordPress role Editor, because the import also changes pages by other authors.
2. Upload the ZIP file.
3. Choose what should happen to pages that already exist there:
   * **Skip** (default): Existing pages are left untouched. Only new ones are created.
   * **Update:** Existing pages are refreshed from the bundle.
   * **Always create:** Every page in the bundle becomes a new page. Useful for cloning.
4. Under **Import into**, choose which handbook the pages go to. Without a choice, the handbook from the bundle is created anew.
5. Start the import and read the report. It lists what was created, updated or skipped.

## Result

The pages are on the target site. Links between the pages lead to the new pages. Two protection rules always hold: nothing is ever deleted. And a page marked as protected on the target site is never overwritten.

<details>
<summary>Pitfalls: Safety beats convenience</summary>

* **An imported handbook always starts with visibility "All members".** That holds even if it was public on the old site. An import must never publish anything by accident. Raise the visibility by hand afterwards.
* **Individually allowed people must be entered again.** They do not travel along.
* **On update, the target site's upkeep data survives.** Feedback numbers and review data are not overwritten.
* **Read bundles from others before you publish them.** The content is cleaned on import like any external content. But it remains a text someone else wrote.

</details>

## Related pages

* [Import Markdown](import-markdown.md)
* [Set visibility](../access/set-visibility.md)

## Transport-Metadaten
* Seitentyp: Guide
* Verantwortliche Rolle: Handbook editors
* Thema: Content
* Zielgruppe: All members, Tech
* Eltern-Seite: Content
* Reihenfolge: 4
* Textauszug: A handbook can be exported as a self-contained bundle and imported again on another website; here is how.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 90 Tage
