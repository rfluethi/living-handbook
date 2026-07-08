# Templates

Living Handbook registers three block templates (WordPress 6.7 and newer, block themes only). They slot into the template hierarchy automatically and use the active theme's header and footer, so the handbook fits the rest of the site. You can open and edit each of them in the Site Editor under **Appearance → Editor → Templates**.

A note on editing: once you save changes to one of these templates in the Site Editor, WordPress keeps your saved version and stops using the plugin's built-in one, even after a plugin update. If a template looks out of date after an update, open it in the Site Editor and choose **Clear customizations** to fall back to the plugin's current version.

## Handbook overview

Applies to the handbook archive at `/handbook/`. It contains a single block, `living-handbook/overview`, which lists the handbooks the visitor may read as cards.

## Handbook entry

Applies to each handbook's entry page (the `handbook_set` term archive, e.g. `/handbook-set/general/`). Its layout:

- the handbook title (`core/query-title`) and its description (`core/term-description`),
- a two-column row: the handbook navigation (`living-handbook/navigation`) in a narrow left column, and the entry (`living-handbook/entry`, with search, filters, areas and recently updated pages) in the wide right column.

## Handbook page

Applies to a single handbook page. It is a three-column layout:

- **Left (narrow):** the handbook navigation (`living-handbook/navigation`).
- **Centre (wide):** the badges (`living-handbook/badges`), the title (`core/post-title`), the on-this-page box for mobile (`living-handbook/toc` set to *mobile*, shown above the content on small screens), the content (`core/post-content`), the feedback prompt (`living-handbook/feedback`) and the metadata footer (`living-handbook/pagemeta`).
- **Right (narrow):** the on-this-page box for desktop (`living-handbook/toc`, sticky, shown on wide screens).

The two on-this-page boxes are the same block with a different **Placement** setting; CSS shows only the one that fits the current screen width.

## Rearranging

Because these are ordinary block templates, you can rearrange them in the Site Editor: move the navigation to the right, drop the desktop on-this-page column, widen the content, and so on. The blocks are self-contained and render wherever you place them, as long as they stay in their intended context (a handbook page or a handbook entry page).
