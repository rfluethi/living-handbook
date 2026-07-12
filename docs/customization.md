# Customizing the frontend

The plugin ships default styles that follow the prototype design and expose CSS
custom properties, so you can adapt the colours to your theme without touching
the plugin. Typography and spacing come from your theme. The navigation is a core
Navigation block styled by the VSN plugin; the rest is plugin markup.

## Overriding the colours and a few sizes

The custom properties are declared on the plugin's frontend wrappers. Set them in
the Site Editor under Styles, Additional CSS, or in your theme's stylesheet:

```css
.living-handbook-entry,
.living-handbook-cards,
.living-handbook-card,
.living-handbook-nav,
.living-handbook-toc,
.living-handbook-meta,
.living-handbook-feedback,
.living-handbook-badge {
	--lh-accent: var(--wp--preset--color--accent, #2c5f8a);
	--lh-accent-soft: #eaf1f8;
	--lh-ok: #1e8449;
	--lh-due: #b26a00;
	--lh-overdue: #c0392b;
	--lh-border: #e2e6ea;
	--lh-muted: #76828d;
	--lh-sticky-top: 2rem; /* offset for sticky nav/TOC under a fixed header */
	--lh-nav-top-weight: 700; /* weight of the top navigation level */
}
```

Point `--lh-accent` at your theme's accent to match your palette. `--lh-sticky-top`
controls the top offset of the sticky navigation and table of contents (and the
scroll offset when jumping to a heading). `--lh-nav-top-weight` sets how bold the
top level of the sidebar navigation is.

## Classes

Entry page (a handbook's start page):

- `.living-handbook-layout`: the two-column grid (content plus filter sidebar), single column below 781px.
- `.living-handbook-start__search`, `.living-handbook-search__input`: the full-text search row.
- `.living-handbook-aside`, `.living-handbook-facet`, `.living-handbook-facet__opt`, `.living-handbook-reset`: the taxonomy filter sidebar and its reset link.
- `.living-handbook-entry__h`, `.living-handbook-count`, `.living-handbook-empty`: section headings, result count, and the empty state.

Card grids (areas, books, and pages):

- `.living-handbook-cards` with `--areas` or `--books`: the responsive grid.
- `.living-handbook-card` with `--area` or `--book`, plus `.living-handbook-card__link`, `__title`, `__excerpt`, `__meta`.
- `.living-handbook-card__dot` with `--ok`, `--due`, `--overdue`: the freshness dot.

Single page navigation (VSN sidebar):

- `.living-handbook-navwrap`: wraps the core Navigation block; keeps it left-aligned.
- `.living-handbook-nav-top`: the top-level navigation item, weighted via `--lh-nav-top-weight`.
- `.living-handbook-nav`, `.living-handbook-nav__title`: the bordered, sticky navigation tree; the current page's list item carries `.is-current`.

On-this-page table of contents:

- `.living-handbook-toc` with `--desktop` or `--mobile`: the collapsible box (desktop sticky at the side, mobile above the content).
- `.living-handbook-toc__summary`, `__list`, `__item`; the active entry link carries `.is-active`.

Badges, metadata footer and feedback:

- `.living-handbook-badges`, `.living-handbook-badge` with `--type`, `--audience`, `--ok`, `--due`, `--overdue`.
- `.living-handbook-meta`, `.living-handbook-metagrid`, `.living-handbook-metagrid__item`, `__label`, `__date`.
- `.living-handbook-person`, `__avatar`, `__name`: the responsible person in the footer.
- `.living-handbook-feedback`: the "was this helpful?" row and its buttons.

Each block can also be styled through `theme.json` under `styles.blocks`.
