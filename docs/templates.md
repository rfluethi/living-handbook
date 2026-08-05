# Templates

Living Handbook registers block templates (WordPress 6.8 and newer, block themes only). They slot into the template hierarchy automatically and use the active theme's header and footer, so the handbook fits the rest of your site. You can open and edit them in the Site Editor under **Appearance → Editor → Templates**.

Two of them do the work:

| Template | Applies to |
| --- | --- |
| **Handbook entry** | Each handbook's entry page, the `handbook_set` term archive, for example `/handbook-set/general/` |
| **Handbook page** | A single handbook page, for example `/handbook/onboarding/` |

There is **no template for the overview**. The overview is a normal WordPress page with the "Handbook overview" block on it; activation creates one for you, and you can move, restyle or replace it. See [blocks.md](blocks.md).

A note on editing: once you save changes to one of these templates in the Site Editor, WordPress keeps your saved version and stops using the plugin's built-in one, even across plugin updates. If a template looks out of date after an update, open it in the Site Editor and choose **Clear customizations** to fall back to the plugin's current version.

## Handbook entry

Applies to each handbook's entry page. Its layout:

- the handbook title (`core/query-title`) and its description (`core/term-description`),
- a two-column row: the handbook navigation (`living-handbook/navigation`, set to *Accordion*) in a narrow left column, and the entry block (`living-handbook/entry`, with search, filters, areas and recently updated pages) in the wide right column.

## Handbook page

Applies to a single handbook page. A three-column layout, 22 / 54 / 22 percent:

- **Left (narrow):** the handbook navigation (`living-handbook/navigation`, set to *Accordion*) and below it the handbook search (`living-handbook/search`).
- **Centre (wide):** the title (`core/post-title`), the mobile table of contents (`living-handbook/toc` set to *mobile*, shown above the content on small screens) and the content (`core/post-content`). Then everything about the page, at the foot: the feedback prompt (`living-handbook/feedback`), a divider, the source note (`living-handbook/git-source-note`), the badges (`living-handbook/badges`), a second divider and the metadata footer (`living-handbook/pagemeta`).
- **Right (narrow):** the desktop table of contents (`living-handbook/toc`, sticky, shown on wide screens).

Last in the centre column comes the core comments block (`core/comments`, with the comment template, pagination and the reply form). It renders nothing while a page's comments are closed, which is the default, so it costs nothing until a site opens them. It is in the template because without it a page with comments open showed nothing at all, and the setting looked broken.

The two dividers are static `core/separator` blocks carrying the class `living-handbook-divider`. They are static on purpose: two of their neighbours render nothing in some cases, a guest without public feedback gets no prompt and a page maintained in WordPress gets no source note, and the foot should look the same either way.

The two tables of contents are the same block with a different **Placement** setting; CSS shows only the one that fits the current screen width, so you do not have to choose.

## Rearranging

These are ordinary block templates, so you can rearrange them in the Site Editor: move the navigation to the right, drop the desktop table of contents, widen the content, and so on. The blocks are self-contained and render wherever you place them, as long as they stay in their intended context (see "Renders on" in [blocks.md](blocks.md)).

Where the shipped versions come from: `src/Frontend/Templates.php`, as block markup in `entry_content()` and `single_content()`. That is the layout a fresh installation gets and the one **Clear customizations** returns to, so changing it there changes the starting point for every site that has not edited the template itself. `BlockTemplatesTest` guards the markup, because nothing else does: an unknown block name renders as nothing and a broken block comment swallows the rest of the template, both without a word at runtime.

## Removing the templates again

If you delete the plugin and chose "Also delete all handbook content" on the settings page, any versions of these templates you saved in the Site Editor are removed too. Without that option your saved templates stay, which is the safe default. See [import-and-sync.md](import-and-sync.md) for the uninstall behaviour.
