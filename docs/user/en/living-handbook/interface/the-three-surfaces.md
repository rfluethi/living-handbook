# The three surfaces

On your website, a handbook consists of three kinds of pages: the overview, the entry page and the single page. This page explains all three. Once you can tell them apart, you always know where an adjustment belongs.

## What this is about

The diagram shows the readers' path: from the overview through a handbook's entry page to the single page.

```mermaid
graph TD;
  U["Overview: all handbooks"] -->|"pick a handbook"| E["Entry page: one handbook"];
  E -->|"pick a page"| S["Single page: one page"];
  S -->|"navigation on the left"| S;
```

| Surface | What it shows | Address |
|---|---|---|
| **Overview** | All handbooks the visitor may read: name, description, page count. | Your choice. Activation creates the "Handbook" page at `/handbooks/`. |
| **Entry page** | The start page of one handbook: search, filters, area tiles, recently updated pages. Created automatically per handbook. | `/handbook-set/<handbook-name>/` |
| **Single page** | One handbook page: navigation, content, table of contents, badges, feedback, page details. | `/handbook/<page-name>/` |

## The entry page

![Layout sketch of the entry page: title, navigation on the left, search field, area tiles, recently updated pages and the filter column on the right](../assets/einstiegsseite-aufbau.webp)

The search and the filters narrow the page list down without reloading the page. Filtering works by page type, topic, role and audience. Without JavaScript, both keep working as an ordinary form. The area tiles are the top-level pages, see [Organise pages](../getting-started/organise-pages.md).

## The single page

![Layout sketch of the single page: navigation and search field on the left, in the middle title, content, feedback prompt, source note, badges and page-details footer, on the right the table of contents](../assets/einzelseite-aufbau.webp)

The page itself comes first: title, then content. Everything that tells you about the page follows underneath: the feedback prompt, the source note on GitHub-backed pages, the badges and the page-details footer. The handbook search sits on the left, under the navigation.

The table of contents on the right builds itself from the page's headings. While you read, it highlights the current section. On narrow screens it appears above the content instead. The footer shows Created, Last updated, Last reviewed and the responsible role. Next to it sits the [review-status badge](../upkeep/the-review-cycle.md).

## Searching and finding

There are two searches, both limited to the current handbook. The search on the **entry page** filters the page list; it covers the title and the text of the pages, including the content of collapsed sections. The search field on a **single page** suggests matching pages as you type, as direct links. Both searches only show pages the searching person may read.

Two limits are intentional: the normal WordPress site search does not find handbook pages; the handbook search exists for that. And the addresses of the handbook pages (`/handbook/...`, `/handbook-set/...`) are in English by default, whatever language the handbook is written in. They can only be changed with a filter in code, see the hooks in the technical documentation.

## Where the layouts come from

For the entry page and the single page the plugin ships finished page layouts, so-called templates. All building parts already sit in the right place. You can rebuild the templates in the Site Editor, under **Appearance → Editor → Templates**. Examples: navigation to the right, drop the table of contents, widen the content. The overview, by contrast, is an entirely normal WordPress page. Move or replace it as you like.

<details>
<summary>Pitfalls: An overview at /handbook/ has no reachable subpages</summary>

The overview created on activation lives at `/handbooks/`. If the handbook pages themselves live under `/handbooks/`, because a developer filter set their address that way, the overview is called `/handbook-overview/` instead. That way the overview and the handbook pages never get in each other's way.

Installations from before version 0.76.0 keep their overview at `/handbook/`. The handbook pages live under exactly the same word, `/handbook/<page-name>/`. If you hang an ordinary WordPress page under such an overview as a subpage, it gets an address like `/handbook/team/`, and WordPress looks for a handbook page called "team" there. The subpage is then unreachable; visitors see "page not found".

The remedy: give the overview a different address, for example `/handbooks/`. You change it in the overview's editor like for any WordPress page. Afterwards, adjust links that point to the old address, in the menu for instance.

</details>

<details>
<summary>Tip: Templates after a plugin update</summary>

As soon as you save a template in the Site Editor, WordPress keeps your version. That holds across plugin updates too. If a template looks outdated after an update, open it in the Site Editor. Choose **Clear customizations** there. Then the plugin's current version applies again.

</details>

<details>
<summary>Background: All blocks in detail</summary>

The plugin ships sixteen blocks of its own, from the handbook overview to the Mermaid diagram. Most only render in their intended context. Anywhere else they output nothing. The full reference with every setting is in the [developer documentation on the blocks](https://github.com/rfluethi/living-handbook/blob/main/docs/technical/en/living-handbook-technical/blocks.md).

</details>

## Related pages

* [Add handbooks to your menu](add-to-the-menu.md)
* [Customize the design](customize-the-design.md)

## Transport-Metadaten
* Seitentyp: Background / Concept
* Verantwortliche Rolle: Handbook editors
* Thema: Design
* Zielgruppe: All members
* Eltern-Seite: Interface
* Reihenfolge: 1
* Textauszug: On the website a handbook consists of three kinds of pages: the overview, the entry page and the single page; this page explains all three.
* Letzte Aktualisierung: 2026-10-27
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 180 Tage
