# Maintenance and freshness

Most internal documentation plugins help you publish. Living Handbook is built around the part that comes after: keeping pages correct once they exist. Documentation without maintenance goes wrong, and wrong documentation is worse than none. This page explains the freshness feature and the workflow it supports.

## The idea

Every page carries two dates and one interval:

- **Last updated** sets itself when you save, so it always reflects the real state of the content.
- **Last reviewed** is when someone last checked the page for correctness, even if nothing needed changing. You set it by hand, because only a person can say "I read this and it still holds".
- **Review interval** is how long a review stays valid for this page. Fast-moving topics (tools, external services) get a short interval; stable topics (principles, org structure) get a long one.

From the review date and the interval the plugin computes a **freshness state** and shows it as a badge in the page's metadata footer.

## The three states

| Badge | Meaning |
| --- | --- |
| **Reviewed** | The last review is within the page's review interval. |
| **Review due** | The interval has passed. The page is not wrong, but nobody has confirmed it lately. |
| **Review overdue** | Twice the interval has passed. This is the escalation state, meant to be noticed. |

The badge is not colour alone: each state has its own shape and a text label, so it is readable without colour vision and by a screen reader.

## Setting the fields

Open a page and find the **Handbook maintenance** meta box at the bottom of the editor. Set the responsible role, the last review date and the review interval there. When you review a page and it still holds, update the review date even though the content did not change: that is exactly the signal the freshness state exists to carry.

For the common case, "I reviewed this today", you do not need to open the page: use **Quick Edit** in the handbook list (hover a row, then Quick Edit). It offers the last review date, the reviewer and the review interval inline, prefilled with the current values, so you can update the review of several pages quickly.

On import, the review date and interval travel in the Markdown transport block (`Letzte Prüfung`, `Prüfintervall`) and are written into these fields. See [import and sync](import-and-sync.md).

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
