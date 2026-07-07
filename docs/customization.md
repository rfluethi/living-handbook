# Customizing the frontend

The plugin follows the prototype design and exposes CSS custom properties so you can adapt the colours to your theme without touching the plugin. Typography and spacing come from your theme.

## Overriding the colours

Set these variables in the Site Editor under Styles, Additional CSS, or in your theme's stylesheet:

```css
.living-handbook-overview,
.living-handbook-cards,
.living-handbook-card,
.living-handbook-nav,
.living-handbook-meta,
.living-handbook-feedback {
	--lh-accent: var(--wp--preset--color--accent, #2c5f8a);
	--lh-ok: #1e8449;
	--lh-due: #b26a00;
	--lh-overdue: #c0392b;
	--lh-border: #e2e6ea;
	--lh-muted: #76828d;
}
```

Point `--lh-accent` at your theme's accent, for example `var(--wp--preset--color--accent)`, to match your palette.

## Classes

- `.living-handbook-overview`, `.living-handbook-overview__group`, `.living-handbook-overview__title`: the overview and its per-handbook sections.
- `.living-handbook-cards`, `.living-handbook-card`, `.living-handbook-card__title`, `.living-handbook-card__dot`: the page cards; the dot carries the freshness via `--ok`, `--due`, `--overdue`.
- `.living-handbook-nav`, `.living-handbook-nav__title`: the navigation tree (bordered, sticky); the current page is `.is-current`.
- `.living-handbook-meta`, `.living-handbook-meta__label`, `.living-handbook-meta__value`: the metadata footer.
- `.living-handbook-badge` with `--ok`, `--due`, `--overdue`: the freshness badge.
- `.living-handbook-feedback`: the "was this helpful?" row.

From the block version onwards you can also style each block through `theme.json` `styles.blocks`.
