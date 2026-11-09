# Export a handbook as Markdown

You want a handbook as ordinary text files, to keep, for a repository on GitHub or to read back in later? Then export it as Markdown: a ZIP file with one file per page, the images and a navigation. The Markdown import reads it back, with the same structure and the same details.

<details>
<summary>Concept: Why a third export</summary>

The **bundle** moves a handbook to another WordPress site running Living Handbook, see [Move a handbook](move-a-handbook.md). The **website** is a snapshot for reading without WordPress, see [Hand a handbook on as a website](export-as-a-website.md).

**Markdown** is the way out of the plugin and back in. The files can be read and changed in any text editor, put into a repository and turned into a handbook again with the Markdown import or the GitHub sync. So your handbook is never trapped in Living Handbook.

</details>

## How it works

1. Open **Handbook → Export**. The section is called **Export as Markdown**, at the very bottom.
2. Choose the **handbook**, and in the second field a single area if you like. The handbooks you may read are offered.
3. Click **Export Markdown**. The ZIP file downloads right away.

## Result

The ZIP file holds a folder named after the handbook:

* **`docs/`**: the pages. A page with subpages becomes a folder, its own text is in `README.md` inside it. Every other page is a `.md` file.
* **`docs/assets/`**: the images and files the pages refer to.
* **`mkdocs.yml`**: the navigation, the titles and order of all pages. The tool MkDocs builds a website from the files with it too.
* **`EXPORT.txt`**: when and with what the export was made, and what it left out.

Each page starts with its title and ends with the **Transport-Metadaten** block: page type, classification, excerpt, review date and review interval. Links to other pages of the handbook point at the file beside them, Mermaid diagrams are in it as code, collapsible sections stay collapsible.

To bring the files back into a handbook, use **Handbook → Import**, tab **ZIP file**. The `mkdocs.yml` sets the structure, see [Import Markdown](import-markdown.md).

<details>
<summary>Pitfalls: What does not come along</summary>

* **The files have no access rules.** Whoever holds the ZIP file reads every page in it, images from protected handbooks included. Treat it like a printout.
* **Blocks of the plugin without a Markdown form are left out**, such as feedback, badges or the area list. `EXPORT.txt` names them per page. Design such as colours or alignment is lost too.
* **Pages with a password are missing.** Their subpages move up one level.
* **Learning paths are missing.** They have no form in Markdown.
* **On a new import, only images come back into the media library.** Links to other files, such as PDFs, then point into the `assets` folder and have to be set again.
* **Pages from GitHub** come along as they are stored here. `EXPORT.txt` names their source on GitHub.

</details>

## Related pages

* [Import Markdown](import-markdown.md)
* [Move a handbook](move-a-handbook.md)
* [Hand a handbook on as a website](export-as-a-website.md)

## Transport-Metadaten
* Seitentyp: Guide
* Slug: export-as-markdown
* Verantwortliche Rolle: Handbook editors
* Thema: Content
* Zielgruppe: All members, Tech
* Eltern-Seite: Content
* Reihenfolge: 6
* Textauszug: How to export a handbook as Markdown files that the Markdown import reads back, and what is lost on the way.
* Letzte Aktualisierung: 2026-11-09
* Letzte Prüfung: 2026-11-09
* Prüfintervall: 180 Tage
