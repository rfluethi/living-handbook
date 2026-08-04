# Getting started

From a fresh install to your first page that visitors can actually see. This walks the whole path once; the deeper references (blocks, templates, import, maintenance) are linked at the end.

## Before you start

- **WordPress 6.8 or newer, with a block theme.** The entry page, the single page and the navigation are built from block templates, so a classic theme will not render them.
- **PHP 8.1 or newer.**
- **A single-site install.** Living Handbook is built for one site; network activation on multisite is not supported. On a network, activate it per site.

## 1. Install and activate

Upload the plugin zip under **Plugins → Add New → Upload Plugin**, or drop the folder into `wp-content/plugins/`, then activate it. (If you build from source, run `composer install` first so `vendor/` is present; the release zip already bundles it.)

Activation does three things for you:

- It registers the handbook content type and the taxonomies, and seeds the four vocabularies (page type, topic, responsible role, audience) with starter terms.
- It creates a normal WordPress page called **Handbook** with the **Handbook overview** block on it, so a fresh install shows something instead of an empty archive. You can move, restyle or replace this page later.
- It flushes rewrite rules, so the handbook URLs work right away.

## Shortcut: load the app handbook first

The plugin comes with a handbook of its own: the documentation of the app, written as a Living Handbook so it doubles as a first example of one. Go to **Handbook → Import**, open the tab **App handbook** and press **Load app handbook**. It ships inside the plugin, as Markdown under `handbuch/`, and is imported from there, so you read the documentation that matches your version and see a real handbook at the same time.

Some details worth knowing:

- It is never loaded on activation, only when you ask for it. It follows the admin language.
- It is a local folder import, so nothing is fetched over the network: the pages always match the installed version, and loading again after a plugin update refreshes them. A fork can point the tab at a GitHub repository instead, through the `living_handbook_app_handbook_url` filter.
- Pick the handbook it goes into under **Load into**; create one first (for example "App handbook") and set who may read it there.

The rest of this guide builds a handbook from scratch, which is what you will do for real content.

## 2. Create your first handbook

A handbook is the container your pages live in. Go to **Handbook → Handbooks → Add New**, give it a name (for example "General") and a short description, and save.

Set its **visibility** on the same screen. A new handbook defaults to **All members (logged in)**, so a logged-out visitor sees nothing until you either set it to **Public** or grant specific roles and people. This is deliberate: the plugin is fail-closed, it would rather hide a page than leak it.

## 3. Add your first page

Go to **Handbook → Add New** and write the page like any block-editor page: a clear title, a short lead, then the content.

Two settings in the editor sidebar matter before you publish:

- **Assign the page to a handbook.** This is the one step people miss. A handbook page that is not assigned to a handbook is invisible on the front end, because access is fail-closed and a page with no handbook has no visibility rule to satisfy. Pick the handbook you just created.
- **Classify the page.** Set the page type (Guide, Process, Reference, Role, Background, FAQ), one or more topics, the audience, and the responsible role. These drive the badges and the filters on the entry page.

> **The most common "nothing shows up".** If a page you published does not appear, it almost always has no handbook assigned, or its handbook is not visible to the current visitor. Assign the handbook, and check the handbook's visibility.

New handbook pages default to comments closed, so a handbook is not a comment thread unless you want one. This is only the default: turn comments on for a page in its **Discussion** panel if you want them. Imported pages, the app handbook included, are created with comments off the same way.

## 4. Set ownership and freshness

At the bottom of the editor, the **Handbook maintenance** meta box carries the review fields: the responsible role, when the page was last reviewed, and the review interval. Fill these in now, even roughly. They power the freshness badge on the page and the overdue dashboard, which is the whole point of a living handbook. The details are in [maintenance.md](maintenance.md).

Ownership is assigned by **role**, not by person, so a staffing change does not mean editing every page. Which person currently holds a role is maintained in one place.

## 5. Give the page a place in the navigation

The per-handbook navigation is built from the page hierarchy, not from a menu you edit by hand. In the editor sidebar, under **Page Attributes**, set a **parent page** and an **order**. Top-level pages become the areas shown on the entry page; their children become the nested navigation. A page with no parent is a top-level area.

## 6. Look at it

Your handbook now has three surfaces:

- The **overview** page ("Handbook") lists every handbook the visitor may read.
- The **entry page** at `/handbook-set/<handbook-slug>/` is the start page of one handbook: search, filters, area tiles and recently updated pages.
- Each **single page** at `/handbook/<page-slug>/` shows the navigation, the content, the table of contents, the badges, the feedback prompt and the metadata footer.

The entry page and the single page come with block templates that already place the right blocks, so you rarely need to build them by hand. What each block does and where it renders is in [blocks.md](blocks.md).

## Where to go next

- **Fill it with content fast:** paste Markdown, upload a ZIP, or sync from GitHub. See [import and sync](import-and-sync.md).
- **Move a handbook to another site:** export it as a bundle under **Handbook → Export** and import that file there. See [import and sync](import-and-sync.md).
- **Keep it from going stale:** review dates, intervals and the overdue dashboard. See [maintenance](maintenance.md).
- **Change how it looks:** the blocks and their CSS variables. See [blocks](blocks.md) and [customization](customization.md).
- **Understand the code:** the plain-language [code overview](code-overview.md).
