# Hooks

Living Handbook exposes extension points so you can adjust behaviour without patching the plugin. Hook names are documented here as they are added.

## Filters

### `living_handbook_can_view_post`

Filters the final decision on whether a user may view a handbook page. This is the single access decision used by every front-end read path (single pages, handbook entry pages, result sets, the facet filter endpoint, the feedback endpoint, single REST reads, and the comment channels), so a filter here takes effect everywhere.

Parameters:

- `bool $allowed` Whether access is granted by the built-in per-handbook rules.
- `int $post_id` The handbook page ID.
- `int $user_id` The user ID (0 for a guest).

Return a boolean. Example, grant a service account read access to everything:

```php
add_filter(
	'living_handbook_can_view_post',
	function ( bool $allowed, int $post_id, int $user_id ): bool {
		if ( user_can( $user_id, 'read_all_handbooks' ) ) {
			return true;
		}
		return $allowed;
	},
	10,
	3
);
```

If you write your own read path, call `AccessController::can_view_post()` rather than reading the term meta yourself. It is the only supported way to ask the question, and it is memoized per request.

### `living_handbook_sync_allowed_hosts`

Filters the hosts a Markdown source URL may point at. The GitHub sync fetches a URL that an editor typed in, so the host is restricted to an allowlist; without it, someone with edit rights could aim the server at an internal address (server-side request forgery).

Parameters:

- `string[] $hosts` Allowed host names. Defaults to `array( 'raw.githubusercontent.com' )`.

Return an array of host names. Example, also allow a self-hosted Git service:

```php
add_filter(
	'living_handbook_sync_allowed_hosts',
	function ( array $hosts ): array {
		$hosts[] = 'git.example.com';
		return $hosts;
	}
);
```

Only add hosts you control or trust. Every host you add is a host your server can be told to fetch from, and it counts as a trusted fetch source from then on: the plugin will pull Markdown from it and store the result as page content. The second safety net stays in place either way, because `wp_safe_remote_get` still refuses internal and private addresses, but a public host under someone else's control is reachable once you list it.

### `living_handbook_nav_label`

Filters the label of the handbook submenu that is injected into a core Navigation block carrying the `has-handbook-menu` class. It only applies to that one case: when you put the class on a navigation link or on a submenu, the item keeps its own label and this filter is not involved. See [blocks.md](blocks.md).

Parameters:

- `string $label` The menu label. Defaults to the translated "Handbooks".

Return a string. Example:

```php
add_filter(
	'living_handbook_nav_label',
	function ( string $label ): string {
		return 'Knowledge base';
	}
);
```

### `living_handbook_uninstall_remove_content`

Filters whether deleting the plugin also removes all handbook content. By default the uninstall keeps your content and only removes the plugin's own options and caches; the same choice is offered as a checkbox on the settings page. This filter is OR-combined with that checkbox, so returning `true` forces the full removal even when the option is off: the handbook pages, the handbooks and their metadata, the four seeded vocabularies, the overview page created on activation, and any templates you edited in the Site Editor.

Parameters:

- `bool $remove` Whether to remove all content. Defaults to `false`.

Return a boolean. Because it runs during uninstall, put it in a must-use plugin (`wp-content/mu-plugins/`) so it is loaded when the plugin is deleted. Example, always wipe content on uninstall:

```php
add_filter( 'living_handbook_uninstall_remove_content', '__return_true' );
```

### `living_handbook_zip_max_bytes`

Filters the maximum uncompressed total of a ZIP import, in bytes (default 100 MB). This is a safety limit against memory exhaustion, not a size the plugin can guarantee: the real ceiling for a large import is the server's PHP configuration (`upload_max_filesize`, `post_max_size`, `memory_limit`, and the execution time limit), which this filter does not change. Raise it only if the server has the memory to read that much at once, and remember that the uploaded ZIP file itself is still bounded by `upload_max_filesize` and `post_max_size`.

Parameters:

- `int $bytes` The default limit in bytes.

Return an integer. Example, raise the uncompressed limit to 250 MB:

```php
add_filter(
	'living_handbook_zip_max_bytes',
	function ( int $bytes ): int {
		return 250 * MB_IN_BYTES;
	}
);
```

### `living_handbook_app_handbook_url`

Filters where the **App handbook** tab loads from. The default is an empty string, which means "use the copy bundled with the plugin". Return a GitHub tree URL to load from a repository instead: a fork with its own documentation points the tab at its repository this way, without editing the plugin, and any install that would rather pull the latest state from GitHub than the bundled copy can too.

Parameters:

- `string $default` The default, an empty string (use the bundled copy).
- `string $locale` The current admin locale.

Return a `github.com/.../tree/<branch>/<path>` URL, or `''` to keep the bundled copy. Example, pull from your own repository instead of the bundle:

```php
add_filter(
	'living_handbook_app_handbook_url',
	function ( string $default, string $locale ): string {
		return 0 === strpos( $locale, 'fr' )
			? 'https://github.com/me/my-docs/tree/main/handbook/fr'
			: 'https://github.com/me/my-docs/tree/main/handbook/en';
	},
	10,
	2
);
```

### `living_handbook_post_type_slug`

Filters the URL base of a handbook page. The default is `handbook`, so a page lives at `/handbook/<slug>`. It is English and fixed by default, so permalinks stay stable and do not collide. Change it only with care: on a live site a new base rewrites every page URL, so flush the permalinks afterwards (Settings, Permalinks) and redirect the old links, or existing bookmarks and inbound links break.

Parameters:

- `string $slug` The rewrite base. Default `'handbook'`.

```php
add_filter(
	'living_handbook_post_type_slug',
	function (): string {
		return 'handbuch';
	}
);
```

### `living_handbook_taxonomy_slug`

Filters the URL base of a handbook grouping term (`handbook_set`), at `/handbook-set/<slug>` by default. Same reason and same caveat as `living_handbook_post_type_slug`.

Parameters:

- `string $slug` The rewrite base. Default `'handbook-set'`.

```php
add_filter(
	'living_handbook_taxonomy_slug',
	function (): string {
		return 'handbuecher';
	}
);
```

### `living_handbook_access_denied_title`

Filters the title of the page a signed-in visitor lands on when they open a handbook they may not read. The built-in message is used unless the settings name a page of your own; this filter changes the built-in one without needing a page.

Parameters:

- `string $title` The built-in title.

Return a string.

### `living_handbook_access_denied_message`

The same for the body of that page. Use it to say who grants access on your site, which the plugin cannot know.

Parameters:

- `string $message` The built-in message, already escaped for output.
- `WP_User $user` The signed-in user who was refused.

Return a string. Anything you return is printed as it is, so escape it yourself.

### `living_handbook_anonymous_feedback_limit`

Filters how many anonymous votes one page accepts per hour (default 200). Anonymous feedback is deliberately not tied to a person, so it cannot be deduplicated; the ceiling exists so an unattended script cannot fill the counter, and it answers with HTTP 429 once reached. It is not a one-vote-per-person rule and cannot be made into one. Return `0` to switch the ceiling off.

Parameters:

- `int $limit` The default ceiling.
- `int $post_id` The page being voted on.

Return an integer. Example, a stricter ceiling on a busy public handbook:

```php
add_filter(
	'living_handbook_anonymous_feedback_limit',
	function ( int $limit, int $post_id ): int {
		return 50;
	},
	10,
	2
);
```

### `living_handbook_archive_allowed_hosts`

Filters the hosts the repository archive may be downloaded from. A GitHub folder import above about twenty files fetches the repository once as a zipball instead of one request per file, and GitHub answers that with a redirect to `codeload.github.com`. This list is deliberately separate from `living_handbook_sync_allowed_hosts`: it applies to the archive path only, so widening it does not widen the general source check. Both are part of the SSRF protection; add a host only if you host your own Git service and know what you are adding.

Parameters:

- `string[] $hosts` The allowed hosts.

Return an array of host names.

### `living_handbook_archive_max_bytes`

Filters the maximum size of that downloaded archive, in bytes. The same reasoning as `living_handbook_zip_max_bytes`: a guard against memory exhaustion, not a promise about what the server can carry.

Parameters:

- `int $bytes` The default limit in bytes.

Return an integer.

### `living_handbook_import_time_budget`

Filters how long one import batch may run, in seconds, before it pauses and continues in the next request. The default is 60 percent of `max_execution_time`, which leaves room for the response itself. Lower it on a host that cuts requests short, raise it on a machine you control. A large import is not lost when a batch ends: it resumes at the stored offset.

Parameters:

- `float $budget` The default budget in seconds.

Return a float.

### `living_handbook_export_user_identifier`

Filters how a reviewer is named in an export bundle. The default is the user's login, which is less personal than the e-mail address in a file that leaves the site. The importer reads either, so bundles written before this still resolve. The trade-off, stated rather than hidden: matching by login fails where the same person has a different login on the target site, and returning the e-mail address puts the old behaviour back.

Parameters:

- `string $identifier` The default, the user's login.
- `int $user_id` The reviewer.

Return a string.

### `living_handbook_heading_anchors`

Filters whether the h2, h3 and h4 of a handbook page get an id built from the heading text and a small link to that section. Default true.

Switching it off leaves the headings exactly as the editor wrote them, which also means links into a section stop working: the table of contents then falls back to ids made in the browser from the heading's position, which are not addresses to pass on. An id set by hand in the editor always wins over the generated one, so a single collision is better solved there than with this filter.

Parameters:

- `bool $enabled` Whether to add ids and anchor links.

Return a bool.

### `living_handbook_enabled_taxonomies`

Filters which of the four classifying vocabularies this site uses: page type, topic, responsibility, audience. The default is whatever the checkboxes under **Handbook → Settings → Vocabularies** say, and all four when nothing has been saved.

A vocabulary that is not in the list disappears from the column and the filter in the page list, the facet on a handbook's entry page, the badge on a page and on a card, the field in the editor sidebar, and the line an import reads from a transport block. It is still registered, and nothing is deleted: the terms stay, the pages keep them, and putting it back brings every assignment back as it was.

The handbook grouping (`handbook_set`) is deliberately not among them and cannot be switched off. Access hangs on it, and a page that belongs to no handbook is invisible on the front end.

Parameters:

- `array<int, string> $enabled` Taxonomy names that are in use.

Return an array of taxonomy names; anything not one of the four is ignored.

## Actions

_None. The three that were listed here as planned, after an import, on the metadata and on the freshness evaluation, were removed in 0.60.0 rather than left standing as a promise: a hook is a commitment whose signature cannot be changed later without breaking whoever uses it, and these three came from a list rather than from a use. They are kept as a note in the project's own workspace, with signature and purpose, and will be built when something actually needs them._
