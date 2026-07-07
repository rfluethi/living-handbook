=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.5.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An internal team handbook for WordPress: structured page types, clear ownership, and freshness tracking so docs don't rot.

== Description ==

Living Handbook turns WordPress into an internal team handbook that is built to stay current. Unlike customer-facing knowledge base plugins, it focuses on the thing that makes internal documentation fail over time: maintenance.

Planned core features:

* A dedicated handbook content type with structured page types (Diataxis plus FAQ).
* Ownership per page: a responsible role mapped to a current person.
* Freshness tracking: per-page review dates and intervals, an overdue dashboard, and escalation for pages that go unchecked.
* Frontend access per handbook: public, all members, or restricted to specific roles and/or people.
* Automatic navigation generated from the page hierarchy.
* No external plugin dependencies, no cost.

== Installation ==

1. Upload the plugin to `wp-content/plugins/living-handbook`.
2. Activate it through the Plugins screen in WordPress.
3. Visit Settings then Permalinks once so the handbook URLs work.

== Changelog ==

= 0.5.0 =
* Frontend design following the prototype: the overview shows page cards with a freshness dot, the navigation is a bordered tree, and the metadata footer, badges and feedback are styled to match.
* Colours are exposed as CSS custom properties (`--lh-accent`, `--lh-ok`, `--lh-due`, `--lh-overdue`, `--lh-border`, `--lh-muted`) so you can adapt them to your theme; see docs/customization.md.
* Fixed the navigation block icon.

= 0.4.0 =
* Overview block: place it on any page to build the handbook home.
* Navigation block: the current handbook's page tree, for a single-page block template.
* Own block category "Living Handbook" in the inserter.

= 0.3.0 =
* Maintenance dashboard widget with the overdue share and a list of pages due or unchecked.
* Handbook list columns for the last review (with status) and the feedback counts.

= 0.2.0 =
* Access configuration UI per handbook.
* Maintenance metadata, freshness status, and a "was this helpful?" feedback counter.
* Default frontend rendering and a navigation shortcode.
* German translation.

= 0.1.0 =
* Initial scaffold, data model, frontend access control, and internationalisation.
