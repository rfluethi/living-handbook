# Hand a handbook on as a website

You want to give a handbook to somebody who has no WordPress and no access to this site? Then export it as a website: a ZIP of finished HTML pages. Whoever unpacks it opens `index.html` with a double click and reads the handbook in a browser, with no server, no installation and no internet connection.

<details>
<summary>Concept: How this differs from the bundle</summary>

There are two exports, and they aim at different things.

The **bundle** moves a handbook to another WordPress site running Living Handbook. It carries the pages as data, with their tags, review dates and visibility, so a living handbook can grow out of it again over there. See [Move a handbook](move-a-handbook.md).

The **website** is a snapshot to read. It carries finished pages, not data: one file per page, the images beside them, a page list and a search. It cannot be turned back into a handbook.

Typical cases: an audit that wants the wording as it stood on a date; an external team you do not want to give accounts to; a copy for the archive; a laptop with no network.

</details>

## How it works

1. Open **Handbook → Export**. The section is called **Export as a website**, below the bundle export.
2. Pick the **handbook**, and optionally a single area in the second field.
3. Pick the **look**. There are four:
   * **Like this site:** your theme travels along. Colours, fonts and spacing come from its `theme.json`, the font files sit in the ZIP, and what you set under Appearance comes on top. This is the default.
   * **Plain, neutral:** no house colours. Usually the better choice for a copy that leaves the team.
   * **Dark:** light text on a dark page, for reading at night.
   * **Paper, for printing:** serif text, a narrower measure, high contrast.
   Every look prints cleanly: the page list, the search box and the footer are left off paper, and links to the outside world print with their address.
4. Click **Build the website**. The progress counts the pages, because it is built in several passes: the browser fetches one after another, so a large handbook does not run into a timeout.
5. When it is done, a button appears with the file size. That downloads the ZIP.

## What you get

Inside the ZIP sits a complete little website:

* **`index.html`**: the start page, listing every page of the handbook.
* **One file per page**, in folders that follow the structure. A subpage of "Upkeep" therefore sits under `upkeep/`.
* **`assets/`**: the styling, the images and the search index.
* **`README.txt`**: a short note saying what the file is, for whoever finds it in a year with no context.

What is inside are the pages **you** may read. Links between handbook pages lead to the files beside them, images travel along, and the search box at the top searches every page in the browser. Links to the outside world stay what they were.

**The pages come out of the same template as on the site.** If you rearranged the handbook page in the Site Editor, moved the navigation or put the badges somewhere else, the export looks the same way. Only what cannot work without a server is taken out first: your theme's header and footer, the feedback prompt, the comments and the quick search in the sidebar. The table of contents is filled during the export rather than in the browser, so it is there without JavaScript and on paper.

Two more things work as they do on the site: **images and diagrams enlarge on a click**, and **Mermaid diagrams are drawn**. The library that draws them is 3.5 MB, so it travels only when the export holds a diagram at all, and it loads only on the pages that have one.

<details>
<summary>Pitfalls: What a copy cannot do</summary>

* **A static copy has no access rules.** Whoever holds the file reads every page in it. Treat it like a printed handbook, not like an account.
* **It is out of date from the first minute.** Nothing in it updates itself. For content that stays current, give people access rather than exports.
* **Comments and "Was this helpful?" are missing.** Both need a server, and there is none.
* **There are no classification filters**, only the search. The filter bar lives on queries to WordPress.
* **People are not in it.** A page's footer shows the dates and the responsible role, but no names and no avatars: the file leaves the building, the names stay here.
* **A very large handbook makes a large file.** The images are most of it. When in doubt, narrow the export to one area.

</details>

## Related pages

* [Move a handbook](move-a-handbook.md)
* [Set visibility](../access/set-visibility.md)

## Transport-Metadaten
* Seitentyp: Guide
* Verantwortliche Rolle: Handbook editors
* Thema: Content
* Zielgruppe: All members, Tech
* Eltern-Seite: Content
* Reihenfolge: 5
* Textauszug: Export a handbook as a finished HTML website, for readers who have no WordPress and no access to this site.
* Letzte Aktualisierung: 2026-08-09
* Letzte Prüfung: 2026-08-09
* Prüfintervall: 180 Tage
