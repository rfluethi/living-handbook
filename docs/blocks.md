# Blocks

Living Handbook ships nine dynamic blocks, grouped under the **Living Handbook** category in the block inserter. They are server-rendered: each one produces its markup on page load, based on the current context. Most only output something in the context they are meant for (a single handbook page or a handbook entry page); placed elsewhere they render nothing.

You normally do not place most of these blocks by hand. The plugin registers three block templates that place them for you: the handbook overview, the handbook entry and the single handbook page. You can still add or rearrange the blocks yourself in the Site Editor.

## Handbook overview (`living-handbook/overview`)

The landing page of all handbooks. It lists every handbook the current visitor may read as a card (name, description, page count), each linking to that handbook's entry page. Handbooks the visitor may not read are omitted.

Renders on: the handbook archive (`/handbook/`), placed by the "Handbook overview" template. Outputs nothing elsewhere.

## Handbook entry (`living-handbook/entry`)

The entry page of one handbook. It shows a prominent search field, then the areas (the top-level pages of the handbook, with a subpage count) and the most recently updated pages as cards, with a facet filter (page type, topic, responsible role, audience) on the right. When a search or a facet is active, it shows a paginated result list instead of areas and recent pages.

Renders on: a handbook entry page (the `handbook_set` term archive, e.g. `/handbook-set/general/`), placed by the "Handbook entry" template. It reads the queried handbook from the URL, so it only works on a term archive.

## Handbook navigation (`living-handbook/navigation`)

The page tree of the current handbook, output as a core Navigation block carrying a VSN block style. The tree is scoped to the current handbook, so it never lists pages of another handbook, and its assembled markup is cached per handbook (invalidated when a page or handbook changes). The current page is marked automatically.

Settings: **Display** chooses between *Menu* (`is-style-vsn-sidebar`, parent pages stay clickable links, the path to the current page opens) and *Accordion* (`is-style-vsn-sidebar-accordion`, submenus open on click and only one stays open per level; parent items become buttons and are no longer direct links).

Renders on: single handbook pages and handbook entry pages. Requires the **Vertical Sidebar Navigation (VSN)** plugin to be active for the styling and behaviour; without it the markup is an unstyled core navigation.

## Handbook badges (`living-handbook/badges`)

The badge row for a single page: page type, topic and audience.

Renders on: single handbook pages only.

## On this page (`living-handbook/toc`)

A table of contents for the current page. The block outputs an empty, hidden container; a small script fills it from the content headings up to the configured depth (H1 to H6) and highlights the current section while scrolling. The depth is a block setting, and a page can override it in the maintenance meta box. If the page has no headings within the depth, the container stays hidden and nothing is shown.

Settings: **Placement** (desktop or mobile) and **Maximum depth** (H1 to H6). The templates place two instances, a sticky desktop one at the side and a mobile one above the content; CSS shows only the one that fits the screen.

Renders on: single handbook pages only.

## Handbook feedback (`living-handbook/feedback`)

The "Was this helpful?" prompt with Yes and No buttons. The answer is stored per page and counted (one vote per logged-in user and page); the maintenance dashboard reports it.

Renders on: single handbook pages only.

## Handbook page meta (`living-handbook/pagemeta`)

The metadata footer for a single page: created, last updated, last reviewed (with a freshness badge) and the responsible role, each with the person (avatar and name) where available.

Settings: **Show people** toggles the avatar and name.

Renders on: single handbook pages only.

## Mermaid diagram (`living-handbook/mermaid`)

Renders a diagram written in [Mermaid](https://mermaid.js.org/) syntax, drawn live in the browser. The import creates this block automatically from a ```` ```mermaid ```` code fence; you can also add it by hand and paste the diagram source. Unlike the other blocks it is not context-bound, so it renders wherever you place it (including inside the page content).

## GitHub source note (`living-handbook/git-source-note`)

A short, placeable note that marks a page as maintained on GitHub and updated automatically. It only outputs on a page whose source is GitHub; on a page maintained in WordPress it renders nothing. Place it in the single template (or in the content) wherever the note should appear.

## Customizing with CSS

The blocks are styled with CSS custom properties and stable class names, so you can adapt them without touching the plugin. The easiest place is **Site Editor → Styles → Additional CSS** (or your theme's stylesheet).

### Colours and spacing (the `--lh-*` variables)

All plugin colours read from custom properties, so overriding a handful of variables restyles every block at once:

```css
:root {
	--lh-accent: #c0651d;      /* links, card titles, active states */
	--lh-accent-soft: #f6ece2; /* the page-type badge background */
	--lh-ok: #1e8449;          /* freshness: reviewed (green) */
	--lh-due: #b26a00;         /* freshness: review due (amber) */
	--lh-overdue: #c0392b;     /* freshness: unchecked (red) */
	--lh-border: #e2e6ea;      /* card and box borders */
	--lh-muted: #76828d;       /* secondary text */
}
```

### Targeting individual blocks

Each block has stable classes you can style directly.

```css
/* Overview and entry cards */
.living-handbook-card { border-radius: 4px; }
.living-handbook-card__title { font-weight: 700; }

/* The page-type badge on cards and single pages */
.living-handbook-badge--type { text-transform: uppercase; }

/* Hide the audience badge on single pages */
.living-handbook-badge--audience { display: none; }

/* Freshness dot on cards */
.living-handbook-card__dot { width: 8px; height: 8px; }

/* On-this-page box */
.living-handbook-toc { background: #fafbfc; }
.living-handbook-toc__list a.is-active { text-decoration: underline; }

/* Metadata footer */
.living-handbook-metagrid { gap: 1.5rem; }
.living-handbook-person__avatar { display: none; } /* keep names, drop avatars */
```

### The navigation block

The navigation renders as a core Navigation block styled by the VSN plugin, so it is themed through VSN's own `--vsn-*` variables, not the `--lh-*` ones. Set them on the two VSN style classes:

```css
:is(.is-style-vsn-sidebar, .is-style-vsn-sidebar-accordion) {
	--vsn-color-text-hover: var(--wp--preset--color--accent);
	--vsn-active-border-color: var(--wp--preset--color--accent);
	--vsn-indent: 1rem; /* indent per level */
}
```

See the VSN documentation for the full list of `--vsn-*` variables. The plugin adds one wrapper, `.living-handbook-navwrap`, around the navigation; use it if you need to scope a rule to the handbook sidebar only.
