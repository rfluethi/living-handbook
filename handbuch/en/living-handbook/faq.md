# Frequently asked questions

Short answers to the most common questions about Living Handbook. Every answer points to the page where the information lives in full.

<details>
<summary>Why is my page not showing up on the website?</summary>

Almost always it is one of three causes. Check them in this order: The page has no handbook assigned. The handbook is not visible to you or your test person. The page is still a draft. Details: [Understanding access](access/understanding-access.md) and [Create your first page](getting-started/create-your-first-page.md).

</details>

<details>
<summary>Why can logged-out visitors not see my handbook?</summary>

A new handbook starts with the visibility "All members (logged in)". Set it to "Public" if everyone should read it. Details: [Set visibility](access/set-visibility.md).

</details>

<details>
<summary>Why do the handbook pages look broken in my theme?</summary>

The plugin needs a block theme and WordPress 6.7 or newer. An older, classic theme cannot display the handbook pages properly. Details: [Installation](getting-started/installation.md).

</details>

<details>
<summary>How do I load this handbook into my own installation?</summary>

Through **Handbook → Import**, tab **App handbook**, with one click. The handbook ships inside the plugin and therefore matches the installed version. After a plugin update, simply load it again. Details: [Load the app handbook](getting-started/load-the-app-handbook.md).

</details>

<details>
<summary>How do I change the order of pages in the navigation?</summary>

Through each page's Page Attributes: parent page and order. For imported handbooks, organise in the source files instead. There, the "Reihenfolge" line in the transport metadata counts. Details: [Organise pages](getting-started/organise-pages.md).

</details>

<details>
<summary>Can I edit a page synced from GitHub in WordPress?</summary>

No, its editor is locked. Otherwise the next sync would overwrite your changes. Edit the Markdown file on GitHub. Or switch the page's source to "Maintained in WordPress". Details: [GitHub sync](content/github-sync.md).

</details>

<details>
<summary>What happens when I import the same source again?</summary>

The existing pages are updated, not duplicated. Address and publication status stay. Only a pasted text draft always creates a new page. Details: [Import Markdown](content/import-markdown.md).

</details>

<details>
<summary>Does "Review due" mean the page is wrong?</summary>

No. It only means: nobody has confirmed the page within its review interval. Read the page. If it still holds, set the review date anew. Details: [The review cycle](upkeep/the-review-cycle.md).

</details>

<details>
<summary>Why can I not see the "Was this helpful?" feedback buttons?</summary>

By default the buttons only appear for logged-in people, with one vote per person and page. Public feedback can be switched on in [the settings](the-settings.md); then logged-out visitors vote on public pages too. Details: [Reading feedback](upkeep/reading-feedback.md).

</details>

<details>
<summary>Does deleting the plugin delete my content?</summary>

No, by default all handbooks and pages survive. Only the plugin's settings are removed. Clearing everything is a deliberate option under **Handbook → Settings**. An accidental delete therefore never costs you the handbook. Details: [The settings](the-settings.md).

</details>

<details>
<summary>Does the plugin send data to GitHub or anywhere else?</summary>

No. It only reads the addresses you enter yourself, during an import or in a page's source. It sends nothing out. If you use neither import nor sync, the plugin makes no external requests at all. Details: [GitHub sync](content/github-sync.md).

</details>

## Related pages

* [Living Handbook](README.md)
* [Getting started](getting-started/README.md)

## Transport-Metadaten
* Seitentyp: FAQ
* Verantwortliche Rolle: Handbook editors
* Thema: Overview
* Zielgruppe: All members
* Reihenfolge: 9
* Textauszug: Short answers to the most common questions about Living Handbook, each pointing to the page with the full story.
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 180 Tage
