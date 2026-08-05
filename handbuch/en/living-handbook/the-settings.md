# The settings

Every option of the plugin at a glance. You find them under **Handbook → Settings**; the screen there has five tabs. Saving always writes the tab you are looking at; the others are left alone.

## GitHub sync

**Automatic sync:** How often WordPress refreshes the [pages connected to GitHub](content/github-sync.md) in the background. The choices: off, hourly, twice daily, daily or weekly (the default). Independently of this, a page always syncs when saved and through the **Sync now** button. The screen also shows when the next automatic sync is scheduled.

## Appearance

**Text size:** A percentage for the text the plugin sets itself: navigation, table of contents, badges, cards and the page-details footer. The text of a page is untouched, that one belongs to your theme. 100 percent is 16 pixels, the size the plugin is designed at. If your theme sets larger text, the handbook looks small beside it; a value around 120 to 130 percent helps then. All sizes move together, so their proportions stay.

**Ten colour fields:** surface, text on the surface, accent, a background and a text colour each for the topic and the audience badge, plus the three review-status colours. Empty means the theme decides. That is how the plugin ships and how it is meant to work. Fill a field in only where your theme gets it wrong, because its colour values do not match what it actually paints, or because the contrast is too low. The colour picker offers your theme's own palette, and **Clear** takes you back to the theme. You do not choose the text colour on filled buttons: the plugin takes black or white, whichever reads better on your accent colour.

A page carries up to three of these small badges, and they are told apart by colour on purpose: the **page type** takes the accent, the **topic** and the **audience** each take their own pair. So if you change only the topic background, exactly one badge changes colour; that is not a bug. The page type follows the accent.

**Custom CSS:** Styling rules that load on the handbook pages only. They are stored with the plugin and removed when you delete the plugin. Custom CSS wins over the colour fields above, so you can mix the two. How to change the colours with them is shown in [Customize the design](interface/customize-the-design.md). Examples sit right on the settings screen, in the **Help** tab at the top right.

## Feedback

**Public feedback:** Off by default. When on, logged-out visitors also see the question "Was this helpful?" on public pages and can vote. To protect privacy, such a vote is linked to no person: no cookie, no IP address, nothing personal. In return there is no one-vote limit there; the same person can vote again after reloading. On internal pages, independently of this, only logged-in people vote, one vote each. How you read and reset the votes is under [Reading feedback](upkeep/reading-feedback.md).

## Access

**No-access page:** Where a signed-in person lands who opens a handbook they may not read. The default is the built-in message. Choose a page of your own here if you want to explain in your own words who grants access, with a contact form for instance. Logged-out visitors still go to the login screen and on to the address they asked for.

## Uninstall

**When the plugin is deleted:** By default, deleting the plugin keeps all handbooks and pages. Only the plugin's settings and caches are removed. An accidental delete therefore never costs you the handbook. Only when you tick **"Also delete all handbook pages, handbooks and their data"** does deleting really clear everything away, including templates edited in the Site Editor.

> **Note:** Only administrators can open this settings screen.

## Related pages

* [GitHub sync](content/github-sync.md)
* [Customize the design](interface/customize-the-design.md)

## Transport-Metadaten
* Seitentyp: Tool overview
* Verantwortliche Rolle: Handbook editors
* Thema: Overview
* Zielgruppe: Tech
* Eltern-Seite: Living Handbook
* Reihenfolge: 8
* Textauszug: Every option of the plugin at a glance: automatic GitHub sync, text size, colours and custom CSS, public feedback, the no-access page and the uninstall behaviour.
* Letzte Aktualisierung: 2026-08-05
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 90 Tage
