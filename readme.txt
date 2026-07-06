=== Living Handbook ===
Contributors: rfluethi
Tags: handbook, documentation, knowledge base, internal, maintenance
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
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
* Automatic navigation menus generated from the page hierarchy.
* Markdown and ZIP import.
* No external plugin dependencies, no cost.

== Installation ==

1. Upload the plugin to `wp-content/plugins/living-handbook`.
2. Activate it through the Plugins screen in WordPress.
3. Visit Settings then Permalinks once so the handbook URLs work.

== Changelog ==

= 0.1.0 =
* Initial scaffold: plugin bootstrap, PSR-4 autoloader, developer tooling (PHPCS with WordPress standards, PHPStan, PHPUnit), and continuous integration.
* Data model: the `handbook` content type; taxonomies for page type, topic, responsible role, and audience; and the handbook grouping with per-handbook frontend access configuration (visibility plus optional roles and users, default members only).
* Controlled vocabulary seeded on activation, using translatable term names.
* Frontend access control: a single check enforces per-handbook visibility on single pages, result sets (archives, search, REST collections), and single REST reads. Editing in wp-admin stays unrestricted; pages without a handbook are fail-closed.
* Internationalisation with a German translation (`languages/`).
