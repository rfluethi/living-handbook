# Classification and roles

Every handbook page is classified by four groups: page type, topic, audience and responsible role. Together they are the **classification**. This guide says what each group is for, and shows where you view, extend and rename its terms.

<details>
<summary>Concept: What the classification is for</summary>

The classification makes pages findable. It produces the badges on every page and the filters on the entry page. For that to work, all authors must use the same terms. That is why the terms are maintained centrally, and while writing you only pick from them. The plugin ships starter terms for page type, audience and role. Topics you create yourself, matching your content.

The classification is not navigation. Where a page sits is decided by its parent page; the classification says what kind of page it is and who it concerns.

</details>

## The four groups, one by one

**Page type** says what kind of page this is, and therefore how to read it: guide, process description, background, FAQ, area overview. A page has exactly one type. Starter terms ship with the plugin, so you rarely need your own. Use this group when your pages really do come in different shapes; if the handbook is nothing but guides, the type says the same thing on every page and can go.

**Topic** says what the page is about, in your own words: onboarding, invoicing, security. This is the group you fill yourself, and the one people filter by most. Keep it short, see the pitfalls below.

**Responsibility** says which role currently holds the page: handbook editors, GitHub specialist. A function, not a person, so the entry survives someone leaving. Who holds the role right now is deliberately not in the plugin, see below. Without this group the review list still shows what is due, but no longer who it concerns.

**Audience** says who the page is written for: all members, content team, coordination, tech. It says who should read the page, not who may: that is the handbook's access rule, and it sits on the handbook, not on the page. If your handbook is the same for everyone anyway, this is the first group you can switch off.

Switching off happens under **Handbook → Settings → Classification**, one checkbox per group. Nothing is deleted: the terms stay, the pages keep their assignments, and switching a group back on brings all of it back unchanged. See [The settings](../the-settings.md).

## Viewing and adding terms

1. Open the **Handbook** menu. Below the divider sit the groups that are switched on: **Page types**, **Topics**, **Audiences** and **Responsibility**.
2. Open the group you want. You see the list of existing terms.
3. Add a new term on the left and save. It is immediately available in the editor sidebar.
4. To rename or delete, hover over a term and choose **Edit** or **Delete**. On deletion, pages only lose that one term; the pages themselves remain.

## Roles: the special case

The **Responsibility** group holds the roles, for example "Handbook editors". A role is a job label, not a person's name. Every page names the role that currently holds it.

Important to know: **which person currently holds a role is deliberately not managed by the plugin.** You maintain this mapping in a single place of your choosing. A handbook page of its own has proven itself, for example "Roles in the team", with a table of role to person. When the staffing changes, you adjust exactly that one page. All other pages keep naming just the role and stay unchanged.

## Result

Every group that is switched on holds the terms your handbook needs. When writing a page, they are available in the sidebar. The filters on the entry page only show terms that at least one page uses.

<details>
<summary>Pitfalls: Less is more</summary>

* **Keep the lists short.** Ten topics everyone understands help more than forty nobody can tell apart.
* **Rename instead of adding.** A second, nearly identical term ("Tech" and "Technical") spreads pages across two filters and makes both useless.
* **An import can create terms.** If a file's transport metadata carries an unknown term, it is created automatically. After an import, check the lists for duplicates.
* **A group that is switched off is no longer read by an import either.** Its line in the transport metadata is skipped, instead of creating terms for something nobody sees.

</details>

## Related pages

* [The settings](../the-settings.md)
* [Create your first page](../getting-started/create-your-first-page.md)
* [The review cycle](the-review-cycle.md)

## Transport-Metadaten
* Seitentyp: Guide
* Verantwortliche Rolle: Handbook editors
* Thema: Upkeep
* Zielgruppe: All members
* Eltern-Seite: Upkeep
* Reihenfolge: 4
* Textauszug: What the four groups of the classification are for, where you maintain their terms, and why the mapping of people to roles deliberately lives outside the plugin.
* Letzte Aktualisierung: 2026-08-09
* Letzte Prüfung: 2026-07-27
* Prüfintervall: 180 Tage
