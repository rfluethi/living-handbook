# The settings

Every option of the plugin at a glance. You find them under **Handbook → Settings**; the screen there has seven tabs. Saving always writes the tab you are looking at; the others are left alone.

## GitHub sync

**Automatic sync:** How often WordPress refreshes the [pages connected to GitHub](content/github-sync.md) in the background. The choices: off, hourly, twice daily, daily or weekly (the default). Independently of this, a page always syncs when saved and through the **Sync now** button. The screen also shows when the next automatic sync is scheduled.

## Classification

**What pages are classified by:** Four checkboxes, one per group. Beside each one, how many terms the group holds, and under it a sentence saying what it is for:

* **Page type:** What kind of page this is, and therefore how to read it: guide, process description, background, FAQ, area overview. One per page.
* **Topics:** What the page is about, in your own words: onboarding, invoicing, security. The group you fill yourself, and the one people filter by most.
* **Responsibility:** Which role currently holds the page: handbook editors, GitHub specialist. A function, not a person, so the entry survives someone leaving.
* **Audiences:** Who the page is written for: all members, content team, coordination, tech. Who should read it, not who may; that is the handbook's access rule.

All four are on by default. If your team only ever uses topics, switch the other three off and they disappear everywhere: the column and the filter in the page list, the facet on a handbook's entry page, the badge on a page and on a card, the field in the editor sidebar and, for responsibility, the "Responsible role" line in the page footer. An import stops reading the matching line as well.

**Nothing is deleted.** The terms stay, the pages keep their assignments, and the moment you switch a group back on, everything is there unchanged. A bundle export carries all four either way, so moving a handbook to another site never loses what this one only hides.

The handbook itself is not in that list and cannot be switched off. Access hangs on it, and a page without a handbook stays invisible on the front end.

In more detail, with examples and pitfalls: [Classification and roles](upkeep/tags-and-roles.md).

## Learning paths

**Learning paths:** One checkbox, **Use learning paths in this handbook installation**. Off by default. A learning path is an ordered selection of pages that already exist in the handbook, for onboarding or for a piece of required reading. With the box ticked, the handbook menu gains a **Learning paths** entry. In this first stage a reader's progress is remembered in their browser only, so nothing personal is stored. Switching it off hides everything again and deletes nothing. How to create a path is under [Create learning paths](content/learning-paths.md).

## Appearance

**Text size:** A percentage for the text the plugin sets itself: navigation, table of contents, badges, cards and the page-details footer. The text of a page is untouched, that one belongs to your theme. 100 percent is 16 pixels, the size the plugin is designed at. If your theme sets larger text, the handbook looks small beside it; a value around 120 to 130 percent helps then. All sizes move together, so their proportions stay.

**Eleven colour fields:** surface, text on the surface, accent, a background and a text colour each for the topic and the audience badge, plus the four review-status colours (Reviewed, Review due, Review overdue, Not reviewed). Empty means the theme decides. That is how the plugin ships and how it is meant to work. Fill a field in only where your theme gets it wrong, because its colour values do not match what it actually paints, or because the contrast is too low. The colour picker offers your theme's own palette, and **Clear** takes you back to the theme. You do not choose the text colour on filled buttons: the plugin takes black or white, whichever reads better on your accent colour.

A page carries up to three of these small badges, and they are told apart by colour on purpose: the **page type** takes the accent, the **topic** and the **audience** each take their own pair. So if you change only the topic background, exactly one badge changes colour; that is not a bug. The page type follows the accent.

**Custom CSS:** Styling rules that load on the handbook pages only. They are stored with the plugin and removed when you delete the plugin. Custom CSS wins over the colour fields above, so you can mix the two. How to change the colours with them is shown in [Customize the design](interface/customize-the-design.md). Examples sit right on the settings screen, in the **Help** tab at the top right.

## Feedback

**Public feedback:** Off by default. When on, logged-out visitors also see the question "Was this helpful?" on public pages and can vote. To protect privacy, such a vote is linked to no person: no cookie, no IP address, nothing personal. In return there is no one-vote limit there; the same person can vote again after reloading. A ceiling of 200 anonymous votes per page and hour only keeps scripts from filling the counter. On internal pages, independently of this, only logged-in people vote, one vote each. How you read and reset the votes is under [Reading feedback](upkeep/reading-feedback.md).

**Failed searches:** On by default. When a search in a handbook finds no page, the plugin counts the search term, so the editors see what readers look for in vain. Only the term in lower case, the handbook, the calendar week and whether the search came from the website or from an AI tool are stored: no account, no IP address, no time of day, no cookie. Searches with a filter selected are not counted. The counts are deleted after 90 days and when the plugin is deleted. They are read through an [AI tool](access/connect-an-ai-tool.md).

## Access

**No-access page:** Where a signed-in person lands who opens a handbook they may not read. The default is the built-in message (**Use the built-in message**): "No access to this handbook", with the name of the signed-in person. Choose a page of your own here if you want to explain in your own words who grants access, with a contact form for instance. Use an ordinary WordPress page for it. If you choose a handbook page or a learning path, saving shows an error message and the setting stays empty. Logged-out visitors still go to the login screen and on to the address they asked for.

**Protected files:** Shows whether the protection of the files of internal handbooks works on your server. **Test the protection** requests a test file without signing in for that. When files wait to be moved, their number and the **Move now** button appear here. On nginx the rule for the server configuration is shown here too. Details: [Protect files](access/protect-files.md).

## Uninstall

**When the plugin is deleted:** By default, deleting the plugin keeps all handbooks and pages. Only the plugin's settings and caches are removed. An accidental delete therefore never costs you the handbook. Only when you tick **"Also delete all handbook pages, handbooks and their data"** does deleting clear away everything the plugin created, including templates edited in the Site Editor. The files in the media library stay, though, including the images and files uploaded to handbook pages. Protected files move back to the normal uploads folder first. If you no longer need them, delete them in the media library. Without the box, protected files stay locked, as when the plugin is deactivated.

**In a network of several sites** the plugin is deleted in the network admin. Every site that ran it is then cleaned by its own setting: one site keeps its handbooks, another removes everything.

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
* Textauszug: Every option of the plugin at a glance: automatic GitHub sync, classification, learning paths, text size, colours and custom CSS, public feedback, failed searches, the no-access page, protected files and the uninstall behaviour.
* Letzte Aktualisierung: 2026-11-10
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 90 Tage
