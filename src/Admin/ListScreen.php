<?php
/**
 * What the page list is currently showing, asked by the controls above it.
 *
 * WordPress lets a person switch columns off: "Screen Options" at the top right
 * of the list, stored per user, plugin columns included. It has nothing of the
 * kind for the filter dropdowns, so a list stripped down to title and handbook
 * still carried five filters, one of them for a column that is not on screen.
 *
 * Rather than add a second place to configure the same thing, the filters read
 * the choice that already exists. One control, two effects: hide the "Topics"
 * column and its dropdown goes with it.
 *
 * One case must not follow the rule. A dropdown that is currently narrowing the
 * list stays visible even when its column is hidden, because the query var lives
 * in the URL and does not care what is on screen. Dropping the control would
 * leave a list filtered by something with no way to see it or undo it, which is
 * worse than one dropdown too many.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the current admin list screen: which columns a person left visible.
 */
final class ListScreen {

	/**
	 * Whether the current screen shows a column.
	 *
	 * Answers true when it cannot tell. The question is asked while rendering a
	 * control, and a missing screen is a reason to show one control too many, not
	 * to silently drop it.
	 *
	 * @param string $column Column key, as used in the `manage_..._posts_columns` array.
	 * @return bool
	 */
	public static function shows_column( string $column ): bool {
		if ( ! function_exists( 'get_current_screen' ) || ! function_exists( 'get_hidden_columns' ) ) {
			return true;
		}

		$screen = get_current_screen();
		if ( null === $screen ) {
			return true;
		}

		return ! in_array( $column, get_hidden_columns( $screen ), true );
	}

	/**
	 * Whether a taxonomy has any term at all.
	 *
	 * A vocabulary nobody has filled cannot narrow anything: its dropdown offers
	 * exactly one entry, "All topics", and selecting it changes nothing. Counted
	 * including empty terms, because a term without pages is still a term someone
	 * created and will use.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	public static function taxonomy_has_terms( string $taxonomy ): bool {
		$count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $count ) ) {
			return true;
		}

		return (int) $count > 0;
	}
}
