# Architecture

A concise, developer-facing summary of the shipped design. The detailed design rationale is maintained internally by the team.

## Building blocks

- **Content type** `handbook`: the handbook pages. Hierarchical, so parent and order drive the navigation menu.
- **Taxonomies**: page type (Diataxis plus FAQ), topic, responsible role, audience.
- **Handbook grouping**: each page belongs to exactly one handbook. A handbook carries a frontend access configuration.
- **Access**: three levels per handbook, public, all members, or restricted to roles and/or people. Enforced frontend-only through a single central check used by every read path (single view, archive, search, REST, menu, overview). Editing in wp-admin is unrestricted.
- **Metadata**: native custom fields for last update, last review, review interval, and reviewer.
- **Maintenance**: an overdue dashboard with a percentage and escalation, plus a per-page feedback counter.
- **Navigation**: one menu per handbook, generated automatically from the page hierarchy.

## Principles

- No external plugin dependencies.
- Standard WordPress interfaces (REST, hooks) behind the access check.
- Configuration follows "decisions, not options".

_This document is generated from the internal German design notes and updated in the same pull request as the code it describes._
