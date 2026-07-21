# Customizing the frontend

The plugin ships default styles and exposes CSS custom properties, so you can adapt the colours to your theme without touching the plugin. Typography and spacing come from your theme. The navigation is a self-contained, collapsible page tree rendered by the plugin; everything the plugin shows is its own markup, styled through the `--lh-*` variables below.

Put your rules in the plugin's own **Custom CSS** field under **Handbook → Settings**: it is added on the handbook pages only, stored with the plugin, and removed when you delete the plugin. Alternatively use the Site Editor under **Styles → Additional CSS** or your theme's stylesheet, but note that CSS kept in the theme stays behind after the plugin is gone.

## Colours and a few sizes

The custom properties are declared on the plugin's frontend wrappers. The quickest way to restyle everything is to override them:

```css
.living-handbook-overview,
.living-handbook-entry,
.living-handbook-cards,
.living-handbook-card,
.living-handbook-nav,
.living-handbook-toc,
.living-handbook-meta,
.living-handbook-feedback,
.living-handbook-badge {
	/* Surface, text and accent default to your theme's colour presets. */
	--lh-surface: var(--wp--preset--color--base, #fff);
	--lh-surface-text: var(--wp--preset--color--contrast, #1d2327);
	--lh-accent: var(--wp--preset--color--accent, #2c5f8a);
	--lh-on-accent: #fff;      /* text on an accent-filled button */

	/* Lines and secondary text are mixed from the surface and its text. */
	--lh-border: color-mix(in srgb, var(--lh-surface-text) 14%, transparent);
	--lh-muted: color-mix(in srgb, var(--lh-surface-text) 62%, var(--lh-surface));

	/* Freshness colours stay fixed. */
	--lh-ok: #176e3c;          /* "Reviewed" */
	--lh-due: #8a5200;         /* "Review due" */
	--lh-overdue: #c0392b;     /* "Review overdue" (the escalation state) */

	--lh-sticky-top: 2rem;     /* offset for sticky nav and TOC under a fixed header */
	--lh-nav-top-weight: 700;  /* weight of the top navigation level */
}
```

Dark mode follows your theme automatically. Because `--lh-surface`, `--lh-surface-text` and `--lh-accent` default to the theme's own colour presets, a dark theme, or a dark style variation a visitor selects, turns the cards, navigation and table of contents dark with it; the borders and secondary text adapt too, because they are mixed from the surface. Themes that expose no such presets (many classic themes) keep the light fallback. To pin a fixed palette regardless of the theme, set `--lh-surface`, `--lh-surface-text` and `--lh-accent` to fixed colours in the Custom CSS field.

`--lh-sticky-top` controls the top offset of the sticky navigation and table of contents, and the scroll offset when jumping to a heading, so raise it if your theme has a fixed header. `--lh-nav-top-weight` sets how bold the navigation title is.

### A note on the freshness names

The three freshness colours carry meaning. The variable and class names use short internal words that mostly match the badges:

| Badge in the interface | Variable | Class modifier | Meaning |
| --- | --- | --- | --- |
| Reviewed | `--lh-ok` | `--ok` | Within the review interval |
| Review due | `--lh-due` | `--due` | The interval has passed |
| Review overdue | `--lh-overdue` | `--overdue` | Twice the interval has passed |

So `--lh-ok` is the colour of the badge that reads **Reviewed**, and `--lh-overdue` the one that reads **Review overdue**. The variable names are internal; the badges are what your readers see.

Keep the three distinguishable from each other, and ideally not by hue alone, since the state is also conveyed by the shape of a small dot on the cards.

`--lh-muted` is used for small secondary text; the default meets WCAG AA (4.5:1 on white), so if you lighten it, check the contrast.

## Scoping to the handbook only

Every handbook view carries the body class `living-handbook-page`. Use it to style standard blocks inside the handbook without touching the rest of your site:

```css
.living-handbook-page .wp-block-quote { border-left-color: var(--lh-accent); }
.living-handbook-page .wp-block-table { font-size: 0.9rem; }
```

## Classes

Every handbook block also offers, under its **Advanced** panel in the editor, an **Additional CSS class** and an **HTML anchor**. The class is added to the block's own root element and the anchor becomes its `id`, so you can target one specific instance (for example one navigation block) or link straight to a block, without touching the shared classes below.

### Overview and entry pages

- `.living-handbook-overview`, `.living-handbook-entry`: the block wrappers. Each also carries a `--list` or `--cards` modifier reflecting the block's Display setting, for example `.living-handbook-entry--list`.
- `.living-handbook-layout`: the two-column grid (results plus filter sidebar); a single column below 781px.
- `.living-handbook-start__search`, `.living-handbook-search__input`: the full-text search row.
- `.living-handbook-aside`, `.living-handbook-facet`, `.living-handbook-facet__opt`, `.living-handbook-reset`: the facet sidebar and its reset link.
- `.living-handbook-main`: the result column that is swapped in when a facet or search filters the list. While loading it carries `aria-busy="true"`, which the default styles use to dim it.
- `.living-handbook-entry__h`, `.living-handbook-count`, `.living-handbook-empty`: section headings, result count and the empty state.

### Card grids

- `.living-handbook-cards`, with `--areas` or `--books`: the responsive grid.
- `.living-handbook-card`, with `--area` or `--book`, plus `.living-handbook-card__link`, `__title`, `__excerpt`, `__meta`.
- `.living-handbook-card__dot`, with `--ok`, `--due` or `--overdue`: the freshness dot. Its shape varies per state (circle, rounded square, diamond) so the status does not rely on colour alone.

In list display the cards lose their box and become flat rows; target them with the parent modifier, for example `.living-handbook-entry--list .living-handbook-card`.

### Handbook menu

- `.living-handbook-menu`, `.living-handbook-menu__list`, `.living-handbook-menu__link`: the compact handbook list for a header.
- `.living-handbook-menu__toggle`: the button that collapses the list on narrow screens. The open state is `.living-handbook-menu.is-open`.

### Navigation

The navigation is a native `<details>` element, styled entirely by the plugin through the `--lh-*` variables above; no other plugin is involved.

- `.living-handbook-navwrap`: wraps the navigation and keeps it left-aligned.
- `.living-handbook-nav`: the bordered, sticky navigation. It carries `.living-handbook-nav--tree` (the **Menu** display, the whole tree open) or `.living-handbook-nav--accordion` (the **Accordion** display, branches collapse).
- `.living-handbook-nav__top`: the title (the `<summary>`), which opens and closes the whole navigation. Its weight is set by `--lh-nav-top-weight`.
- `.living-handbook-nav__home`: the small arrow link next to the title that leads to the handbook's start page.
- `.living-handbook-nav__list`, `.living-handbook-nav__sublist`: the tree and its nested levels.
- `.living-handbook-nav__item`: one page. It carries `.has-children` on a branch, `.is-current` on the current page, and `.is-open` on an open branch.
- `.living-handbook-nav__row`: the row inside an item, holding the toggle (or a spacer) and the page link.
- `.living-handbook-nav__toggle`, `.living-handbook-nav__spacer`: the open/close button on a branch, and the equal-width spacer that keeps leaf labels aligned.

### Table of contents

- `.living-handbook-toc`, with `--desktop` or `--mobile`: the collapsible box.
- `.living-handbook-toc__summary`, `__list`, `__item`. The entry for the section you are reading carries `.is-active`.

### Badges, metadata footer and feedback

- `.living-handbook-badges`, `.living-handbook-badge`, with `--type`, `--audience`, `--ok`, `--due` or `--overdue`.
- `.living-handbook-meta`, `.living-handbook-metagrid`, `.living-handbook-metagrid__item`, `__label`, `__date`.
- `.living-handbook-person`, `__avatar`, `__name`: the responsible person in the footer.
- `.living-handbook-feedback`: the "Was this helpful?" row and its buttons.
- `.living-handbook-visually-hidden`: text shown only to screen readers (for example the freshness label on a card). Keep it visually hidden but readable by assistive technology.

## Accessibility: what not to remove

The default styles include a few rules that exist for accessibility. If you override them, please keep an equivalent.

- **Focus rings.** Every interactive element gets a visible outline on `:focus-visible`. Themes often strip the browser default; the plugin restores it. If you restyle it, keep an outline that is clearly visible against your background.
- **Reduced motion.** Card hover motion and the loading fade are switched off under `prefers-reduced-motion: reduce`, and the table of contents then jumps instead of scrolling smoothly. If you add your own animations, wrap them in the same media query.

```css
@media (prefers-reduced-motion: reduce) {
	.my-custom-animation { transition: none; animation: none; }
}
```

Each block can also be styled through `theme.json` under `styles.blocks`.
