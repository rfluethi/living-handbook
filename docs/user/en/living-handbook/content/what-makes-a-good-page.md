# What makes a good handbook page

This page answers the question: **what makes a handbook page good, regardless of the tool?** It is for everyone who writes or reviews pages. How to create a page technically is on [Writing content](writing-content.md).

## The underlying problem: two audiences, one document

A handbook serves two groups with opposing needs. Newcomers want to learn, understand and build context. Experienced people want to find fast and get on with it.

Write separately for both and you end up with two handbooks that drift apart and have to be maintained twice. The answer is **one** document that keeps the required path short and visible and hides the depth in collapsible sections. People looking something up never see them. People who want to learn open them.

## Six rules that carry

**One page, one purpose.** A page is either a how-to, an explanation or a reference, never all at once. The question "why do we do it this way?" does not belong next to the steps but in a collapsible section or on a page of its own. The pattern comes from the [Diátaxis framework](https://diataxis.fr/).

**Action before theory.** A how-to starts with the first step, not with a paragraph of background. The why follows below, where it is needed.

**Depth goes into collapsible sections.** Required information sits at the top and visible, reasoning and detail sit below it, collapsed. Give every collapsible section a telling title following the pattern "Category: what it is about", for example "Background: what review intervals are for". A title like "More information" tells nobody whether opening it is worth the click. The principle is called [progressive disclosure](https://www.nngroup.com/articles/progressive-disclosure/).

**One source per piece of information.** Every fact lives in exactly one place, everywhere else links to it. Otherwise a change means finding two places, and one of them gets forgotten.

**Link, do not duplicate.** Link other people's documentation instead of copying it. Describe only what is different in your case.

**A role is responsible, not a person.** Write "the handbook editors approve it", not "Anna approves it". Otherwise a change of staff means searching the whole handbook. Living Handbook has the field "responsible role" on every page for exactly this.

<details>
<summary>Example: how the rules work together on one page</summary>

Say you are writing "How to create a task". The page is a pure how-to. It starts with the first click instead of theory. The reasoning "why we track tasks this way" sits collapsed below it and links to the concept page instead of repeating it. How the tool works in general is linked, not copied. A role is responsible. The review date and the responsibility sit at the bottom. If the process changes, you update this page in the same work session.

</details>

## How a page is built

Keep the same order on every page and readers find their way without thinking about it:

1. **A title and one opening sentence.** The sentence says what the page delivers and for whom. It also works as the excerpt on overview cards and in search results.
2. **The main part,** structured with headings from level 2 downwards. The "on this page" list builds itself from them.
3. **Collapsible sections** with background, prerequisites and pitfalls.
4. **Related pages** as a list at the end.
5. Where useful, a **page glossary** explaining the terms of that page. A central glossary for the whole handbook goes stale faster than anyone maintains it.

## Make currency visible

A handbook page without a date is worthless, because nobody can tell whether it still holds or was merely forgotten. Living Handbook therefore shows on every page when it was last changed, when it was last reviewed and who is responsible.

Set the review date even when you changed nothing. "I read this and it still holds" is a statement, and it is the most valuable one a handbook page can carry. How the review cycle works is on [The review cycle](../maintenance/the-review-cycle.md).

And the rule behind it: when a process or a tool changes, you update the affected page in the same work session. A handbook that trails reality loses its readers' trust faster than it builds content.

## Related pages

* [Writing content](writing-content.md)
* [Create your first page](../getting-started/create-your-first-page.md)
* [The review cycle](../maintenance/the-review-cycle.md)

## Page glossary

| Term | Definition |
|---|---|
| Collapsible section | A section that starts closed and opens on click. In Markdown a `<details>` element. |
| Diátaxis | A structural approach to documentation that treats how-to, explanation, reference and tutorial as separate page types. |
| Progressive disclosure | A design principle that shows only what is needed and keeps the depth available on demand. |
| Role | A function in the team that responsibilities are assigned to, instead of a named person. |

## Transport-Metadaten
* Seitentyp: Background / Concept
* Slug: what-makes-a-good-page
* Verantwortliche Rolle: Handbook editors
* Thema: Content
* Zielgruppe: Content creators
* Eltern-Seite: Content
* Reihenfolge: 0
* Textauszug: What makes a handbook page good, regardless of the tool: one purpose per page, action before theory, depth in collapsible sections, one source per fact.
* Letzte Aktualisierung: 2026-08-30
* Letzte Prüfung: 2026-08-30
* Prüfintervall: 365 Tage
