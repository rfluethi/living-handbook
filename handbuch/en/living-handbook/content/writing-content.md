# Writing content

This page helps you write handbook pages. The first part applies to everyone. The second part only concerns pages that come from Markdown files.

## Writing in WordPress

You write a handbook page like any other WordPress page. Everything familiar works: headings, lists, tables, images, quotes. On top of that, the plugin brings its own building blocks, for example for diagrams. You find them when inserting a block, under the **Living Handbook** category.

Two recommendations for readable pages:

* **Start with a short opening sentence.** It says what the page delivers and who it is for. The same sentence works well as the short text on the overview tiles.
* **Structure with subheadings.** Use headings of level 2 and below. The "On this page" list builds itself from them.

## Using diagrams

Does a workflow have several steps or decisions? Then a diagram often shows more than a long paragraph. Living Handbook draws diagrams itself, right on the page. They are described in [Mermaid](https://mermaid.js.org/), a simple text language for diagrams. Insert the **Mermaid** block in the editor and write the diagram description into it. How the block works, with an example, is on [The blocks](../interface/the-blocks.md); a larger diagram is on [The review cycle](../upkeep/the-review-cycle.md).

## Writing in Markdown files

This part only concerns you if your pages come from Markdown files. The two ways for that are the [import](import-markdown.md) and the [GitHub sync](github-sync.md). On the way in, the text is converted and cleaned for safety. Most things survive that without a scratch. A few things get lost.

### What works

* Headings, paragraphs, lists, tables, quotes, bold and italics.
* Links between the files. The import turns them into links between the finished pages.
* Images. They are added to the WordPress media library. That includes SVG graphics, the losslessly scalable vector images.
* Diagrams. Write the Mermaid description in the file as a code block with the language `mermaid`. On the finished page it becomes a drawn diagram.
* Collapsible sections, like the "Requirements" and "Pitfalls" boxes in this handbook. In the file you write them as a `<details>` section. On the finished page they stay collapsible.

### What does not work

* The plugin's own blocks, such as the feedback prompt or the badges. They cannot be written in a Markdown file. You do not need to, though: the plugin places them in the right spots by itself.
* Program code that would run something on the page. The cleaning removes it. That protects your website.
* Special formats of other tools, for example coloured note boxes from MkDocs (`!!! note`). They degrade to plain text.

## Related pages

* [Import Markdown](import-markdown.md)
* [Create your first page](../getting-started/create-your-first-page.md)

## Transport-Metadaten
* Seitentyp: Tool overview
* Verantwortliche Rolle: Handbook editors
* Thema: Content
* Zielgruppe: All members
* Eltern-Seite: Content
* Reihenfolge: 1
* Textauszug: What belongs on a good handbook page, how to use diagrams, and what survives when Markdown files are read in.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 180 Tage
