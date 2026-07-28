# Add handbooks to your menu

This guide puts your handbooks where visitors expect them: in the website's header. The recommended way is a CSS class on your theme's Navigation block.

<details>
<summary>Concept: Why the handbook list is no normal menu</summary>

Which handbooks a person sees depends on their reading rights. So the list is different for every visitor. A hand-maintained menu cannot express that. That is why the plugin hangs the allowed handbooks into your menu automatically. You only mark the place where they should appear. The marker is a CSS class: a kind of label you attach to a menu item. The plugin looks for that label.

</details>

## Steps

1. Open the Site Editor and select your theme's **Navigation block**.
2. Inside it, select the menu item the handbooks should appear under. An example: a link "Handbook" that points at your overview page.
3. In the right sidebar, open the **Settings** tab (the gear). Scroll all the way down to the collapsed **Advanced** panel. It sits below all other fields and is easy to miss.
4. Under **Additional CSS class(es)**, enter exactly `has-handbook-menu`.
5. Save.

![The Navigation block's sidebar with the Advanced panel open and the class `has-handbook-menu` entered.](../assets/navigation-css-klasse.webp)

## Result

The menu item stays in place and gains a submenu. In it appear the handbooks the current visitor may read, as individual entries. The item's name and link target do not change. All this happens inside the theme menu. On a phone, the handbooks therefore travel along into the hamburger menu automatically.

## The three places for the class

* **On a single menu link (recommended):** The link gains a submenu with the handbooks as entries. It keeps its name and target.
* **On an existing submenu:** Its entries are replaced by the handbooks. You decide the submenu's name and target.
* **On the whole Navigation block:** A submenu "Handbooks" is added as the first item. It points at the overview page created on activation. If that page no longer exists, nothing is inserted. A menu item leading nowhere would be worse than none.

<details>
<summary>Pitfalls: When the integration does not kick in</summary>

* **Only the block called "Navigation" is supported.** The classic menu editor under **Appearance → Menus** is untouched. A class entered there has no effect.
* **The class must match exactly:** `has-handbook-menu`. Variants like `has-handbook-menu-alt` are ignored.
* **Alternative without a theme menu:** The **Handbook menu** block shows the same list as a standalone block. You can place it anywhere, for example in the header template. On narrow screens it collapses behind a button.

</details>

## Related pages

* [The three surfaces](the-three-surfaces.md)
* [Understanding access](../access/understanding-access.md)

## Transport-Metadaten
* Seitentyp: Guide
* Verantwortliche Rolle: Handbook editors
* Thema: Design
* Zielgruppe: All members
* Reihenfolge: 3
* Textauszug: This guide puts the handbooks into your theme's menu, through the CSS class has-handbook-menu on the Navigation block.
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 180 Tage
