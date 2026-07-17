# Hooks

Living Handbook exposes extension points so you can adjust behaviour without patching the plugin. Hook names are documented here as they are added.

## Filters

### `living_handbook_can_view_post`

Filters the final decision on whether a user may view a handbook page. This is the single access decision used by every front-end read path (single pages, handbook entry pages, result sets, the facet filter endpoint, the feedback endpoint and single REST reads), so a filter here takes effect everywhere.

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

Only add hosts you control or trust. Every host you add is a host your server can be told to fetch from.

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

_Planned: filters for the navigation markup, the metadata output and the freshness evaluation._

## Actions

_None yet. Planned: after a Markdown, ZIP or GitHub import completes, and after a GitHub page is synced._
