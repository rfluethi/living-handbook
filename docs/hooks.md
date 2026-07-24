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

Filters the GitHub folder URL the **App handbook** tab loads from. The default points at this plugin's own documentation repository, chosen by the admin language. A fork with its own documentation uses this filter to point the tab at its repository, without editing the plugin. Return an empty string to hide the tab and the setup hint entirely.

Parameters:

- `string $default` The default tree URL for the current admin language.
- `string $locale` The current admin locale.

Return a `github.com/.../tree/<branch>/<path>` URL, or `''`. Example, point it at your own handbook:

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

_Planned: filters for the navigation markup, the metadata output and the freshness evaluation._

## Actions

_None yet. Planned: after a Markdown, ZIP or GitHub import completes, and after a GitHub page is synced._
