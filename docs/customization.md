# Customizing the frontend

The plugin outputs plain markup with stable CSS classes and uses your theme's theme.json presets (colors, spacing) where it can. You control the look from the theme, not from the plugin, so it stays consistent with your block theme and survives plugin updates.

## Where to make changes, from preferred to quick

1. **Your theme's `theme.json`** (`settings` and `styles`): preferred, versioned with the theme, update-safe.
2. **Site Editor, Styles, Additional CSS:** quick, no file, good for experimenting.
3. **From the block version onward:** style each block through `styles.blocks` in `theme.json` and the block supports; you will need almost no custom CSS.

## Classes

- `.living-handbook-nav`: the per-handbook generated navigation.
- `.living-handbook-meta`: the metadata footer.
- `.living-handbook-meta__label` and `.living-handbook-meta__value`: the label and value of each field.
- `.living-handbook-badge`: the freshness badge; status via the modifiers `--ok`, `--due`, `--overdue`.
- `.living-handbook-feedback`: the "was this helpful?" row.

## Example CSS

```css
/* Living Handbook: defaults, override in theme.json or Additional CSS */
.living-handbook-meta {
  margin-block-start: var(--wp--preset--spacing--50, 2rem);
  padding: var(--wp--preset--spacing--30, 1rem);
  border: 1px solid var(--wp--preset--color--contrast, #e0e0e0);
  border-radius: 12px;
}
.living-handbook-meta__label { font-size: .8rem; opacity: .7; }
.living-handbook-badge {
  display: inline-block; border-radius: 999px; padding: .1em .6em; font-size: .75rem;
  background: var(--wp--preset--color--accent-2, #f3e4c3);
  color: var(--wp--preset--color--contrast, #5a4300);
}
.living-handbook-badge--overdue {
  background: var(--wp--preset--color--accent-5, #f3d0c3);
}
.living-handbook-feedback { margin-block-start: 1rem; display: flex; gap: .5rem; }
```

## Color slugs

The preset slugs (`contrast`, `accent-2`, `accent-5`, ...) are named differently in each theme. Adjust them to your theme's palette, or override the classes entirely with your own values. The fallback values in parentheses only apply when the theme does not define the slug.
