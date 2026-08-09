# Customizing the frontend

The plugin ships default styles and exposes CSS custom properties, so you can adapt the colours to your theme without touching the plugin. Typography and spacing come from your theme. The navigation is a self-contained, collapsible page tree rendered by the plugin; everything the plugin shows is its own markup, styled through the `--lh-*` variables below.

Put your rules in the plugin's own **Custom CSS** field under **Handbook → Settings**: it is added on the handbook pages only, stored with the plugin, and removed when you delete the plugin. Alternatively use the Site Editor under **Styles → Additional CSS** or your theme's stylesheet, but note that CSS kept in the theme stays behind after the plugin is gone.

## Without writing CSS

**Handbook → Settings → Appearance** has the eleven colours that matter and one text size, for the case a theme gets it wrong: a theme whose presets do not match what it actually paints, or one whose contrast is too low to read. The colour picker offers your theme's own palette as swatches.

Two colours are deliberately not fields. The text on an accent-filled control (`--lh-on-accent`) is derived from the accent, black or white, whichever has the higher contrast. And the page-type badge takes the accent itself (`--lh-accent-soft` on `--lh-accent`), which is why setting the topic badge colours only one of the three chips under a page: they are told apart by colour on purpose.

| Field | Variable | Where it lands |
| --- | --- | --- |
| Surface | `--lh-surface` | cards, navigation, table of contents, filter bar, search field |
| Text on the surface | `--lh-surface-text` | the text on them; lines and secondary text are mixed from it |
| Accent | `--lh-accent` | links, current page, filled controls, **and the page-type badge** |
| Topic badge background / text | `--lh-badge-bg`, `--lh-badge-text` | the topic chip only |
| Audience badge background / text | `--lh-badge-audience-bg`, `--lh-badge-audience-text` | the "Audience: …" chip only |
| Reviewed / due / overdue | `--lh-ok`, `--lh-due`, `--lh-overdue` | three of the four freshness chips; the dots and error messages derive from them |
| Not reviewed | `--lh-none` | the fourth chip, for a page with no review date; neutral rather than a warning |

An empty field means the theme decides, which is the shipped state and the design of the plugin. Nothing is printed for it. What is set is printed as `--lh-user-*` on `:root`, and the stylesheet reads each variable as `var(--lh-user-x, <theme preset>, <fallback>)`. That gives three levels, in this order:

1. the plugin's defaults, which follow the theme's presets,
2. the settings fields, which win over the defaults without a specificity fight,
3. CSS you write, which wins over both, because it names `--lh-x` directly and is printed last.

**Text size** is a percentage. Every font size in the stylesheet is a multiple of `--lh-base`, which is deliberately undeclared and therefore falls back to `1rem`, so 100 percent is exactly what the plugin shipped with. The setting prints `--lh-base` on `:root`: 125 percent is `1.25rem`, so 16 px becomes 20 px and every size moves with it, keeping the proportions they were tuned in. This matters on a theme whose own text is not 16 px, because the plugin sizes in `rem` and therefore ignores the theme's text size, and can look small beside it. You can also set `--lh-base` yourself, in any unit:

```css
body { --lh-base: 20px; }
```

The size deliberately does not touch the text of a page itself. That is your theme's, and the plugin has no business resizing it.

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
	--lh-surface: var(--lh-user-surface, var(--wp--preset--color--base, #fff));
	--lh-surface-text: var(--lh-user-surface-text, var(--wp--preset--color--contrast, #1d2327));
	--lh-accent: var(--lh-user-accent, var(--wp--preset--color--accent, #2c5f8a));
	--lh-on-accent: var(--lh-user-on-accent, #fff);
	                           /* text on an accent-filled button; the settings derive it */

	/* Lines and secondary text are mixed from the surface and its text. */
	--lh-border: color-mix(in srgb, var(--lh-surface-text) 14%, transparent);
	--lh-border-strong: color-mix(in srgb, var(--lh-surface-text) 48%, transparent);
	                           /* the stronger line: table head rule, quote bar, input and toggle borders */
	--lh-muted: color-mix(in srgb, var(--lh-surface-text) 62%, var(--lh-surface));
	--lh-accent-soft: color-mix(in srgb, var(--lh-accent) 12%, var(--lh-surface));
	                           /* a tinted accent backdrop: page-type badge, hovered and current rows */

	/* Freshness colours stay fixed, because they are only ever drawn inside a
	   chip that brings its own background. */
	--lh-ok: var(--lh-user-ok, #176e3c);          /* "Reviewed" */
	--lh-due: var(--lh-user-due, #8a5200);        /* "Review due" */
	--lh-overdue: var(--lh-user-overdue, #c0392b); /* "Review overdue" (the escalation state) */

	/* The same three for the places with no chip behind them: the freshness dot
	   on a card and the two error messages. A third of the surface's own text
	   colour is mixed in, which darkens the hue on a light theme and lightens it
	   on a dark one, so it stays legible either way. Derived, so setting the
	   colour above moves these with it. */
	--lh-ok-on-surface: color-mix(in srgb, var(--lh-ok) 65%, var(--lh-surface-text));
	--lh-due-on-surface: color-mix(in srgb, var(--lh-due) 65%, var(--lh-surface-text));
	--lh-overdue-on-surface: color-mix(in srgb, var(--lh-overdue) 65%, var(--lh-surface-text));

	/* Badge chips keep fixed values too: small stickers that must stay legible
	   on any surface. Each freshness chip pairs its background with the matching
	   freshness colour above as its label. */
	--lh-badge-text: var(--lh-user-badge-text, #5f6b75);
	                                   /* label of a neutral badge */
	--lh-badge-bg: var(--lh-user-badge-bg, #eef1f4);
	                                   /* background of a neutral badge */
	--lh-badge-audience-bg: #f3eafc;   /* background of the audience badge */
	--lh-badge-audience-text: #6b3fa0; /* label of the audience badge */
	--lh-badge-ok-bg: #e7f6ec;         /* background of the "Reviewed" badge */
	--lh-badge-due-bg: #fdf0e0;        /* background of the "Review due" badge */
	--lh-badge-overdue-bg: #fdecea;    /* background of the "Review overdue" badge */

	--lh-sticky-top: 2rem;     /* offset for sticky nav and TOC under a fixed header */
	/* --lh-base is not declared here: every font size reads it as
	   var(--lh-base, 1rem). Set it on body to scale the handbook's own text. */
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
| Not reviewed | `--lh-none` | `--none` | No review date set |

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
- `.living-handbook-start__search`, `.living-handbook-start__search-field`, `.living-handbook-start__search-label`, `.living-handbook-search__input`: the search bar. The form's `data-button-position` attribute says where the button sits (`button-outside`, `button-inside`, `no-button`). Its own defaults sit inside `:where()`, which has no specificity, so anything set in the block settings wins without a fight; if you restyle it by hand, a single class is enough.
- `.living-handbook-filterform`, `.living-handbook-facet`, `.living-handbook-facet__opt`, `.living-handbook-reset`: the filter bar, its groups and its reset link. The box (border, background, padding, sticky offset) is declared inside `:where()` for the same reason as the search bar. The filter bar is its own block since 0.66.0, so the box it used to get from the entry page's sidebar (`.living-handbook-aside`, gone with `.living-handbook-layout`) is on the form itself, and the columns of the entry page come from the template's own Columns block.
- `.living-handbook-main`: the result column that is swapped in when a facet or search filters the list. While loading it carries `aria-busy="true"`, which the default styles use to dim it.
- `.living-handbook-anchor`: the `#` link beside an h2 to h4. Do not hide it with `display: none`, or it becomes unreachable by keyboard; the default styles use `opacity` and reveal it on hover and focus.
- `.living-handbook-entry__h`, `.living-handbook-count`, `.living-handbook-empty`: section headings, result count and the empty state.

### Search on a single page

- `.living-handbook-page-search`: the wrapper of the search block on a single page.
- `.living-handbook-page-search__input`: the search field (full column width).
- `.living-handbook-page-search__results`: the list of matches that appears right under the field as you type; `.living-handbook-page-search__empty` is the empty state.

### Card grids

- `.living-handbook-cards`, with `--areas` or `--books`: the responsive grid.
- `.living-handbook-card`, with `--area` or `--book`, plus `.living-handbook-card__link`, `__title`, `__excerpt`, `__meta`.
- `.living-handbook-card__preview`, `.living-handbook-card__more`, `.living-handbook-card__parent`: the page titles under a handbook card, the link to the rest, and the name of the handbook this one belongs to. The preview sits outside the card's own link, because a link inside a link is not markup a browser can make sense of.
- `.living-handbook-cards--children`: the handbooks below another one, set in by a margin.
- `.living-handbook-card__dot`, with `--ok`, `--due` or `--overdue`: the freshness dot. Its shape varies per state (circle, rounded square, diamond) so the status does not rely on colour alone.

In list display the cards lose their box and become flat rows; target them with the parent modifier, for example `.living-handbook-entry--list .living-handbook-card`.

### Handbook menu

- `.living-handbook-menu`, `.living-handbook-menu__list`, `.living-handbook-menu__link`: the compact handbook list for a header.
- `.living-handbook-menu__toggle`: the button that collapses the list on narrow screens. The open state is `.living-handbook-menu.is-open`.

### Navigation

The navigation is styled entirely by the plugin through the `--lh-*` variables above; no other plugin is involved. Its title row has the same shape as any row with children: a toggle button on the left, a link beside it.

- `.living-handbook-navwrap`: wraps the navigation and keeps it left-aligned.
- `.living-handbook-nav`: the bordered, sticky navigation. It carries `.living-handbook-nav--tree` (the **Menu** display, the whole tree open) or `.living-handbook-nav--accordion` (the **Accordion** display, branches collapse), and `.is-collapsed` while the whole navigation is closed.
- `.living-handbook-nav__top`: the title row. It holds the toggle and a link to the handbook's start page. Its weight is set by `--lh-nav-top-weight`.
- `.living-handbook-nav__toggle--all`: the toggle in that row, which opens and closes the whole navigation. **Do not hide it:** on a narrow screen it is how the navigation gets out of the way of the content, and it is the only control that does so.
- `.living-handbook-nav__list`, `.living-handbook-nav__sublist`: the tree and its nested levels.
- `.living-handbook-nav__item`: one page. It carries `.has-children` on a branch, `.is-current` on the current page, and `.is-open` on an open branch.
- `.living-handbook-nav__row`: the row inside an item, holding the toggle (or a spacer) and the page link.
- `.living-handbook-nav__toggle`, `.living-handbook-nav__spacer`: the open/close button on a branch, and the equal-width spacer that keeps leaf labels aligned.

### Table of contents

- `.living-handbook-toc`, with `--desktop` or `--mobile`: the collapsible box.
- `.living-handbook-toc__summary`, `__list`, `__item`. The entry for the section you are reading carries `.is-active`.

### Badges, metadata footer and feedback

- `.living-handbook-badges`, `.living-handbook-badge`, with `--type`, `--audience`, `--ok`, `--due` or `--overdue`.
- `.living-handbook-meta`, `.living-handbook-metagrid`, `.living-handbook-metagrid__item`, `__label`, `__date`. The footer is a description list (`dl`); label and value are `dt` and `dd`.
- `.living-handbook-person`, `__avatar`, `__name`: the responsible person in the footer.
- `.living-handbook-feedback`: the "Was this helpful?" row and its buttons.
- `.living-handbook-visually-hidden`: text shown only to screen readers (for example the freshness label on a card). Keep it visually hidden but readable by assistive technology.

## Accessibility: what not to remove

The default styles include a few rules that exist for accessibility. If you override them, please keep an equivalent.

- **Focus rings.** Every interactive element gets a visible outline on `:focus-visible`. Themes often strip the browser default; the plugin restores it. If you restyle it, keep an outline that is clearly visible against your background.
- **Reduced motion.** Card hover motion and the loading fade are switched off under `prefers-reduced-motion: reduce`, and the table of contents then jumps instead of scrolling smoothly. If you add your own animations, wrap them in the same media query.
- **The enlarge button.** An image or a diagram that can be enlarged sits inside a real `<button class="living-handbook-zoom">`, so it can be reached and triggered with the keyboard and announces what it does. A diagram's button takes the full column width, because a diagram is drawn to the width it is given and would otherwise collapse. If you restyle the button, do not turn it back into a plain element, and leave the width of `.living-handbook-zoom--diagram` alone.
- **The touch targets.** The small controls keep a minimum height of 24 pixels (WCAG 2.5.8). Making them smaller makes them hard to hit on a touchscreen and for anyone with an unsteady hand.

```css
@media (prefers-reduced-motion: reduce) {
	.my-custom-animation { transition: none; animation: none; }
}
```

Each block can also be styled through `theme.json` under `styles.blocks`.
