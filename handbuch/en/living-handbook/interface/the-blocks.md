# The blocks

Living Handbook ships blocks of its own, building parts for the editor. You find them when inserting a block, under the **Living Handbook** category. This page shows which ones you use yourself and which ones the plugin places on its own.

Through the blocks you decide what a page can do. You combine them freely with the normal WordPress blocks and configure each one through its settings. That way you give a page exactly the function it needs: a diagram here, an in-page search there, a badge row or an area list. Where the blocks sit in the shipped page layouts is set by the templates, which you can rebuild in the Site Editor. A handbook page is nothing rigid; it can be tailored to its purpose.

## Blocks you use yourself

### Mermaid

![Icon of the Mermaid block](../assets/bloecke-mermaid.webp)

Draws diagrams right on the page: workflows, decision paths, hierarchies. You describe the diagram as text in the diagram language [Mermaid](https://mermaid.js.org/); it is drawn when the page is shown. Insert the block and write the description into the **Mermaid code** field. A title becomes the caption. The text description is read aloud when someone uses the page with a screen reader. This is what the description of a small workflow looks like:

```text
graph LR;
  A["Write a draft"] --> B["Have it reviewed"];
  B --> C["Publish"];
```

And this is how it is drawn:

```mermaid
graph LR;
  A["Write a draft"] --> B["Have it reviewed"];
  B --> C["Publish"];
```

The block works everywhere, including in the middle of page content. If a page comes from a Markdown file, you do not need to place it by hand: a code block with the language `mermaid` becomes this block automatically on [import](../content/import-markdown.md).

### Handbook overview

![Icon of the Handbook overview block](../assets/bloecke-uebersicht.webp)

Lists every handbook the current visitor may read, with name, description and page count. Activation creates a page with this block. You can also put it on any page you like. Setting: display as **List** or as **Cards**.

### Handbook menu

![Icon of the Handbook menu block](../assets/bloecke-menu.webp)

Shows the same handbook list compactly, meant for the website's header. On narrow screens it collapses behind a button. Usually the [menu integration](add-to-the-menu.md) is the better choice; this block is the alternative without a theme menu.

### GitHub source note

![Icon of the GitHub source note block](../assets/bloecke-github.webp)

Marks a page as [maintained on GitHub](../content/github-sync.md) and also links the source file on GitHub, so readers can open it there directly. The note's text is editable. The block only shows itself on pages with a GitHub source; everywhere else it stays invisible. So you can place it once in the page layout and forget about it.

## Blocks the plugin places for you

The following blocks already sit in the right place in the shipped page layouts. You only meet them when you rebuild the layouts in the Site Editor. Most of them also only show something in their context; anywhere else they stay empty.

### Handbook entry

![Icon of the Handbook entry block](../assets/bloecke-eintrag.webp)

Builds a handbook's entry page: search, filters, area tiles and the recently updated pages. Works only on the entry page.

### Handbook navigation

![Icon of the Handbook navigation block](../assets/bloecke-navigation.webp)

Shows the handbook's collapsible page tree. Display as **Menu** (everything open) or **Accordion** (branches collapse individually). Works on the entry page and on single pages.

### Handbook badges

![Icon of the Handbook badges block](../assets/bloecke-badges.webp)

Shows a page's badges: page type, topic, audience. Works only on single pages.

### Table of Contents

![Icon of the Table of Contents block](../assets/bloecke-inhaltsverzeichnis.webp)

Builds "On this page" from the page's headings and highlights the current section while you read. Works only on single pages.

### Handbook search

![Icon of the Handbook search block](../assets/bloecke-suche.webp)

A search field that suggests matching pages of the handbook as you type. Works only on single pages.

### Handbook feedback

![Icon of the Handbook feedback block](../assets/bloecke-feedback.webp)

Asks "Was this helpful?" with Yes and No. Works only on single pages. By default only logged-in people see the buttons; public feedback can be switched on in [the settings](../the-settings.md).

### Handbook page meta

![Icon of the Handbook page meta block](../assets/bloecke-seiten-meta.webp)

Shows Created, Last updated, Last reviewed and the responsible role, including the review-status badge. Works only on single pages.

<details>
<summary>Tip: Styling single blocks deliberately</summary>

Every block offers two fields in the sidebar under **Advanced**: an **additional CSS class** and an **HTML anchor**. With the class you style exactly this one block, with the anchor you link straight to it. The full technical reference of all blocks is in the [developer documentation on the blocks](https://github.com/rfluethi/living-handbook/blob/main/docs/blocks.md).

</details>

## Related pages

* [The three surfaces](the-three-surfaces.md)
* [Add handbooks to your menu](add-to-the-menu.md)
* [Writing content](../content/writing-content.md)

## Transport-Metadaten
* Seitentyp: Tool overview
* Verantwortliche Rolle: Handbook editors
* Thema: Design
* Zielgruppe: All members
* Reihenfolge: 2
* Textauszug: Which blocks of the plugin you use yourself, first of all the Mermaid diagram, and which ones the plugin places on its own.
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 180 Tage
