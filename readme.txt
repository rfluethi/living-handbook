=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.4.0
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

= 0.4.0 =
* Overview block: place it on any page to build the handbook home, listing every handbook you may view and its pages.
* Navigation block: the current handbook's page tree, for use in a single-page block template.
* Both blocks render server-side; navigation logic is shared with the `[living_handbook_nav]` shortcode.

= 0.3.0 =
* Maintenance dashboard widget showing the share of overdue pages and a list of pages due or unchecked.
* Handbook list columns for the last review (with status) and the feedback counts.
* Frontend order fixed: the feedback prompt now sits above the metadata footer.

= 0.2.0 =
* Access configuration UI per handbook (visibility public, members, or restricted to roles and/or users).
* Maintenance metadata: last updated (automatic), last reviewed, review interval, reviewer, with an editor meta box.
* Freshness status: reviewed, review due, or unchecked (escalated past twice the interval).
* "Was this helpful?" feedback counter with a REST endpoint.
* Default frontend rendering: a feedback prompt and a metadata footer with a freshness badge, plus a navigation shortcode.
* German translation.

= 0.1.0 =
* Initial scaffold: plugin bootstrap, PSR-4 autoloader, developer tooling (PHPCS, PHPStan, PHPUnit), and continuous integration.
* Data model: the `handbook` content type; taxonomies; and the handbook grouping.
* Frontend access control enforced per handbook on every read path.
* Internationalisation with a German translation.
