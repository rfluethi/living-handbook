# Maintenance and freshness

Most internal documentation plugins help you publish. Living Handbook is built around the part that comes after: keeping pages correct once they exist. Documentation without maintenance goes wrong, and wrong documentation is worse than none. This page explains the freshness feature and the workflow it supports.

## The idea

Every page carries two dates and one interval:

- **Last updated** sets itself when you save, so it always reflects the real state of the content.
- **Last reviewed** is when someone last checked the page for correctness, even if nothing needed changing. You set it by hand, because only a person can say "I read this and it still holds".
- **Review interval** is how long a review stays valid for this page. Fast-moving topics (tools, external services) get a short interval; stable topics (principles, org structure) get a long one.

From the review date and the interval the plugin computes a **freshness state** and shows it as a badge in the page's metadata footer.

## The four states

| Badge | Meaning |
| --- | --- |
| **Review overdue** | Twice the interval has passed. This is the escalation state, meant to be noticed. |
| **Review due** | The interval has passed. The page is not wrong, but nobody has confirmed it lately. |
| **Reviewed** | The last review is within the page's review interval. |
| **Not reviewed** | No review date, or no interval. Nobody has set the page up for review yet. |

**Not reviewed** is deliberately neutral in colour rather than a warning. A page nobody has looked at is not a page that went stale, and every page arrives in this state: a fresh import would otherwise look like a failing handbook on its first day. What it asks for is not a review but a decision, a review date and an interval, and after that the page joins the other three states.

The badge is not colour alone: each state has its own shape and a text label, so it is readable without colour vision and by a screen reader. The dot of **Not reviewed** is an outlined circle rather than a filled one, an empty shape for a field nobody has filled in.

## Setting the fields

Open a page and find the **Handbook maintenance** meta box at the bottom of the editor. Set the responsible role, the last review date and the review interval there. When you review a page and it still holds, update the review date even though the content did not change: that is exactly the signal the freshness state exists to carry.

For the common case, "I reviewed this today", you do not need to open the page: use **Quick Edit** in the handbook list (hover a row, then Quick Edit). It offers the last review date, the reviewer and the review interval inline, prefilled with the current values, so you can update the review of several pages quickly.

On import, the review date and interval travel in the Markdown transport block (`Letzte Prüfung`, `Prüfintervall`) and are written into these fields. See [import and sync](import-and-sync.md).

## Finding pages in the list

The handbook list carries a few columns and filters to help you work through it. The **Last reviewed** column sorts by review date (oldest first is the useful direction for triage), and the **Feedback** column sorts by net feedback, the yes votes minus the no votes, so the best and worst received pages are one click away. Above the list, a dropdown for each taxonomy filters it: handbook, page type, topic, responsibility and audience, plus a source filter (GitHub or WordPress), the same way the category filter works for posts. The taxonomy columns themselves do not sort, on purpose, because a page can belong to several terms and so has no single order; the dropdowns are the reliable way to narrow the list.

A **review-status filter** (reviewed, due, overdue, never reviewed) sits alongside them. The status is not a stored field: it is computed from each page's review date and its review interval, so it lives as its own filter rather than as a sortable column. Sort the Last reviewed column by date to see the oldest reviews, or filter by status to pull out everything overdue at once.

Two warnings can appear above the list: pages that belong to no handbook (and so stay invisible on the front end), and GitHub pages whose last sync failed. Both list the affected pages as direct links, so you reach each one in a click.

## The overdue dashboard

The plugin adds a dashboard widget that lists the pages whose review is due or overdue, so you do not have to open pages one by one to find them. It reads the same review dates and intervals. The widget is the triage surface: work down it, review each page, and reset its review date.

## Who does the reviewing

Responsibility is deliberately **distributed, not central**. There is no single role that owns the whole handbook. Every page names a **responsible role** in its metadata, and that role maintains the page. A staffing change touches the one place that maps roles to people, not the pages.

The cross-cutting work that belongs to no single page (navigation structure, reading search logs and feedback, triaging the dashboard, spot checks) sits with an editorial role. Page ownership stays with the responsible role either way.

## Two ways a page gets maintained

- **On a schedule.** Each page is reviewed regularly by its responsible role. "Regularly" is the review interval you set per page. When a review comes due, the dashboard surfaces it; when it stays undone past twice the interval, the badge escalates to *Review overdue* so it does not age quietly.
- **On an occasion.** A page is updated immediately when a process changes, a tool is swapped or retired, an error is found, or several people ask the same question the handbook fails to answer. The healthiest maintenance is this one: fix the page in the same work step as the change, not later as a separate chore.

## Versioning

Because pages are maintained in WordPress, WordPress revisions are the version history: who changed what, when, with the option to restore an earlier version. There is no separate per-page changelog to keep.

## Related feedback signal

The **Was this helpful?** prompt on each page (see [blocks.md](blocks.md)) counts one vote per allowed reader and reports the totals on the dashboard. A page that keeps drawing "no" is a maintenance signal: it is not answering the question people arrive with.

By default only logged-in readers who may view the page vote, one vote each. Turn on **Public feedback** under Handbook → Settings to let logged-out visitors vote on public pages too; to stay privacy-friendly those votes store nothing personal (no cookie, no IP, no identifier), so they have no per-person limit. After reworking a page you can reset its counters: the page list shows a **Reset feedback** action on any page that has votes, which clears its yes and no counts.
