# Hooks

Living Handbook exposes extension points so behaviour can be adjusted without
patching the plugin. Concrete hook names are documented here as they are added.

## Filters

### `living_handbook_can_view_post`

Filters the final decision on whether a user may view a handbook page. This is
the single access decision used by every frontend read path (single pages,
handbook entry pages, result sets, and single REST reads), so a filter here
takes effect everywhere.

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

_Planned: filters for menu markup, metadata output, and freshness evaluation._

## Actions

_None yet. Planned: after a Markdown, ZIP, or GitHub import completes, and after
a GitHub page is synced._
