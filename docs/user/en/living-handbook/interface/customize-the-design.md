# Customize the design

The handbook pages take font, spacing and colours from your theme. Usually you do not have to do anything.

If you want to change something anyway, there are two ways. The eight colours that matter most and the text size need no CSS: they are fields under **Handbook → Settings → Appearance**, see [The settings](../the-settings.md). Everything else goes through CSS, which is what this page is about. CSS is the language websites are styled with. You can also simply copy the example below and swap the colour values.

The two mix: custom CSS wins over the fields, and the fields win over the plugin's defaults.

<details>
<summary>Concept: Why recolouring works this way</summary>

All colours of the plugin hang on central control values, so-called CSS variables. Their names start with `--lh-`. Change one control value and the colour changes everywhere at once. Without your own values, the colours follow your theme. A dark theme makes the handbook pages dark automatically. A plugin update does not overwrite your values.

</details>

## Steps

1. Open [the settings](../the-settings.md) under **Handbook → Settings** and find the **Custom CSS** field. It only affects the handbook pages. Deleting the plugin removes it too.
2. Enter the variables you want to change. This example acts exactly where you are reading right now: on the single page. It turns the search field, the table of contents, the badges and the active navigation entry dark. It also shrinks the body text a little:

   ```css
   /* Dark boxes on the single page */
   .living-handbook-nav,
   .living-handbook-toc,
   .living-handbook-badge,
   .living-handbook-page-search {
     --lh-surface: #1d2733;      /* surface */
     --lh-surface-text: #f2f5f8; /* text */
     --lh-accent: #ffb84d;       /* accent */
     --lh-on-accent: #1d2733;
     --lh-badge-bg: #33414f;
     --lh-badge-text: #d7dee5;
   }

   /* Slightly smaller body text on handbook pages */
   .living-handbook-page .wp-block-post-content {
     font-size: 0.92em;
   }
   ```

   The second rule uses the class `living-handbook-page`. It sits on every handbook view. With it you also style normal theme elements without changing the rest of the website.

3. Save and reload a single page. You see it immediately: the search field at the top and the table of contents are dark with light text. The badges are dark chips. The active entry in the navigation glows amber. The body text is a touch smaller. If you do not like it, empty the field again; then the theme's colours apply once more.

4. To recolour the whole handbook, add the remaining surfaces to the front of the selector list: `.living-handbook-overview`, `.living-handbook-entry`, `.living-handbook-cards`, `.living-handbook-card`. Then the overview and the entry page with its tiles switch too.

## Result

The selector list decides where the new values apply; every listed surface takes them on uniformly. The most important variables:

| Variable | Controls |
|---|---|
| `--lh-surface`, `--lh-surface-text` | Surface and text of cards, table of contents, filter column and search field |
| `--lh-accent`, `--lh-on-accent` | Accent colour and text colour on accent surfaces |
| `--lh-badge-bg`, `--lh-badge-text` | Surface and text colour of the badge chips |
| `--lh-ok`, `--lh-due`, `--lh-overdue` | The three review-status colours (Reviewed, due, overdue) |
| `--lh-sticky-top` | Top offset of the sticky navigation and table of contents |

<details>
<summary>Pitfalls: What to watch when recolouring</summary>

* **Two spots deliberately do not follow along.** The navigation box has no background of its own; it always shows the page background through. And links in the page content belong to the theme, not the plugin; you change their colour in the Site Editor.
* **Keep the three review-status colours distinguishable**, ideally not by hue alone. The shapes (circle, rounded square, diamond) help as well. Do not rely on them alone though.
* **Check the contrast when you lighten greys.** The defaults meet the accessibility requirements (WCAG AA).
* **Do not remove the focus rings.** They make keyboard use visible. If you restyle them, keep a clearly visible replacement.

</details>

<details>
<summary>Background: All variables and class names</summary>

Every block additionally offers its own CSS class and an HTML anchor under **Advanced**, for styling or linking single instances. The full reference of all `--lh-` variables and stable class names is in the [developer documentation on customization](https://github.com/rfluethi/living-handbook/blob/main/docs/technical/en/living-handbook-technical/customization.md).

</details>

## Related pages

* [The three surfaces](the-three-surfaces.md)
* [The review cycle](../upkeep/the-review-cycle.md)

## Transport-Metadaten
* Seitentyp: Guide
* Verantwortliche Rolle: Handbook editors
* Thema: Design
* Zielgruppe: Tech
* Eltern-Seite: Interface
* Reihenfolge: 4
* Textauszug: The handbook pages follow your theme's colours; through CSS variables with the --lh- prefix you adjust them deliberately.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 365 Tage
