# Installation

How to get the plugin ready to use. The path runs from the ZIP file to the first visible handbook page.

<details>
<summary>Requirements: What your website needs</summary>

* **WordPress 6.7 or newer, with a block theme.** A block theme is a newer kind of WordPress design that is edited entirely in the site editor. Twenty Twenty-Five, which ships with WordPress, is one example. With an older, classic theme the handbook pages will not display properly.
* **PHP 8.1 or newer.** Your website's PHP version is listed under **Tools → Site Health → Info**.
* **A single WordPress site.** If you run a network of several sites (multisite), activate the plugin per site, not network-wide.

</details>

## Steps

1. In WordPress, open **Plugins → Add New → Upload Plugin**.
2. Choose the file `living-handbook.zip` and click **Install Now**.
3. Click **Activate Plugin**. The new entry **Handbook** appears in the menu on the left.
4. Handbook addresses like `/handbook/...` not working right away? Then open **Settings → Permalinks** once. Opening is enough, you do not have to save anything.

## Result

Activation sets up three things for you:

* Its own kind of page for handbook pages, separate from your normal pages and posts.
* Four groups of tags for classifying pages: page type, topic, responsible role and audience. All come filled with starter terms.
* A normal WordPress page called **Handbook**. It will later list your handbooks. You can move, restyle or replace it.

You can tell it worked by two things: the admin menu shows the entry **Handbook**, and the page **Handbook** is reachable on the website. It is empty at first. That is normal, because there is no handbook yet.

<details>
<summary>Pitfalls: If something does not work</summary>

* **The handbook pages look broken or unstyled:** Your theme is probably not a block theme. Check under **Appearance**. If the entry **Editor** is missing there, the theme is classic.
* **Handbook addresses return "page not found":** Open **Settings → Permalinks** once. That refreshes the address rules.
* **You work from the source code instead of the release ZIP:** Then extra steps are needed. They are described in the [developer documentation on GitHub](https://github.com/rfluethi/living-handbook#development).

</details>

## Related pages

* [Create your first handbook](create-your-first-handbook.md)
* [Set visibility](../access/set-visibility.md)

## Transport-Metadaten
* Seitentyp: Guide
* Verantwortliche Rolle: Handbook editors
* Thema: Getting started
* Zielgruppe: All members
* Eltern-Seite: Getting started
* Reihenfolge: 1
* Textauszug: How to get the plugin ready to use, from the ZIP file to the first visible handbook page in your WordPress installation.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 180 Tage
