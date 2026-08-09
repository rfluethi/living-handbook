<?php
/**
 * Handbook taxonomies.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Taxonomy;

use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the four handbook taxonomies: page type, topic (shown as "Topics"),
 * responsible role (shown as "Responsibility"), and audience. They are not
 * public on their own; the frontend access check governs visibility.
 */
final class Taxonomies {

	public const PAGE_TYPE = 'handbook_type';
	public const TOPIC     = 'handbook_topic';
	public const ROLE      = 'handbook_role';
	public const AUDIENCE  = 'handbook_audience';

	/**
	 * Which of the four classifying taxonomies this site uses. An array keyed by taxonomy,
	 * '1' for on. Absent means all four, which is how every existing site
	 * behaves after an update.
	 */
	public const OPTION_ENABLED = 'living_handbook_taxonomies';

	/**
	 * The four classifying taxonomies, taxonomy to label, in the order they
	 * are offered.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			self::PAGE_TYPE => __( 'Page type', 'living-handbook' ),
			self::TOPIC     => __( 'Topics', 'living-handbook' ),
			self::ROLE      => __( 'Responsibility', 'living-handbook' ),
			self::AUDIENCE  => __( 'Audiences', 'living-handbook' ),
		);
	}

	/**
	 * Whether this site uses one of the four classifying taxonomies.
	 *
	 * Switching one off hides it: the column and the filter in the backend, the
	 * facet on the entry page, the badge on a page and on a card, the field in
	 * the editor sidebar, and the line in an import's transport block. What it
	 * does not do is delete anything. The terms stay, the pages keep them, and
	 * switching it back on brings every assignment back exactly as
	 * it was. That was decided rather than assumed: a switch that quietly threw
	 * away work would be a switch nobody dares to touch.
	 *
	 * The bundle export and import are deliberately not filtered by this. A
	 * bundle carries a handbook to another site, and dropping data on the way
	 * because this site happens to hide it would lose it for good.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	public static function is_enabled( string $taxonomy ): bool {
		return in_array( $taxonomy, self::enabled(), true );
	}

	/**
	 * The classifying taxonomies this site uses, in the order of all().
	 *
	 * @return array<int, string>
	 */
	public static function enabled(): array {
		$stored = get_option( self::OPTION_ENABLED, null );
		$out    = array();

		foreach ( array_keys( self::all() ) as $taxonomy ) {
			// No option yet means all four: that is what every site had before
			// the switches existed, and an update must not change a site.
			if ( ! is_array( $stored ) || ! empty( $stored[ $taxonomy ] ) ) {
				$out[] = $taxonomy;
			}
		}

		/**
		 * Filter which classifying taxonomies this site uses.
		 *
		 * The handbook grouping itself is not among them and cannot be switched
		 * off: access hangs on it, and a page without a handbook is invisible.
		 *
		 * @param array<int, string> $enabled Taxonomy names that are in use.
		 */
		$filtered = (array) apply_filters( 'living_handbook_enabled_taxonomies', $out );

		// Intersected with the four this plugin has, so a filter cannot invent a
		// fifth taxonomy that nothing else in the plugin knows about, and the
		// order stays the order of all().
		return array_values( array_intersect( array_keys( self::all() ), $filtered ) );
	}

	/**
	 * Sanitize the switches from the settings form.
	 *
	 * Written as all four keys, present or absent, rather than as a list of the
	 * ones that are on: an empty list and "nothing saved yet" would otherwise be
	 * the same value, and they mean opposite things.
	 *
	 * @param mixed $value Raw value from the form.
	 * @return array<string, string>
	 */
	public static function sanitize_enabled( $value ): array {
		$raw = is_array( $value ) ? $value : array();
		$out = array();

		foreach ( array_keys( self::all() ) as $taxonomy ) {
			$out[ $taxonomy ] = empty( $raw[ $taxonomy ] ) ? '0' : '1';
		}

		return $out;
	}

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register the taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		$object = array( Handbook::POST_TYPE );

		register_taxonomy(
			self::PAGE_TYPE,
			$object,
			$this->args(
				__( 'Page types', 'living-handbook' ),
				__( 'Page type', 'living-handbook' ),
				'handbook-type',
				true,
				self::is_enabled( self::PAGE_TYPE )
			)
		);

		register_taxonomy(
			self::TOPIC,
			$object,
			$this->args(
				__( 'Topics', 'living-handbook' ),
				__( 'Topic', 'living-handbook' ),
				'handbook-topic',
				true,
				self::is_enabled( self::TOPIC )
			)
		);

		register_taxonomy(
			self::ROLE,
			$object,
			$this->args(
				__( 'Responsibility', 'living-handbook' ),
				__( 'Responsibility', 'living-handbook' ),
				'handbook-role',
				false,
				self::is_enabled( self::ROLE )
			)
		);

		register_taxonomy(
			self::AUDIENCE,
			$object,
			$this->args(
				__( 'Audiences', 'living-handbook' ),
				__( 'Audience', 'living-handbook' ),
				'handbook-audience',
				true,
				self::is_enabled( self::AUDIENCE )
			)
		);
	}

	/**
	 * Build a common taxonomy argument array with a full label set, so the term
	 * screens do not fall back to the generic category or tag labels.
	 *
	 * @param string $name         Plural label.
	 * @param string $singular     Singular label.
	 * @param string $slug         Rewrite slug.
	 * @param bool   $hierarchical Whether the taxonomy is hierarchical.
	 * @param bool   $enabled      Whether this site uses this taxonomy at all.
	 * @return array<string, mixed>
	 */
	private function args( string $name, string $singular, string $slug, bool $hierarchical, bool $enabled = true ): array {
		return array(
			'labels'            => self::labels( $name, $singular ),
			'hierarchical'      => $hierarchical,
			'public'            => false,
			// A taxonomy this site does not use stays registered, and only
			// stops being shown. Unregistering it would orphan the terms and the
			// assignments, which is exactly what the switch promises not to do;
			// this way the editor sidebar, the term screen and the admin column
			// are gone while the data sits untouched, ready for the day somebody
			// switches it back on.
			'show_ui'           => $enabled,
			'show_admin_column' => $enabled,
			'show_in_rest'      => $enabled,
			'rewrite'           => array( 'slug' => $slug ),
		);
	}

	/**
	 * A full taxonomy label set derived from the plural and singular names.
	 *
	 * @param string $name     Plural label.
	 * @param string $singular Singular label.
	 * @return array<string, string>
	 */
	public static function labels( string $name, string $singular ): array {
		return array(
			'name'          => $name,
			'singular_name' => $singular,
			'menu_name'     => $name,
			'all_items'     => $name,
			/* translators: %s: singular taxonomy name. */
			'edit_item'     => sprintf( __( 'Edit %s', 'living-handbook' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'view_item'     => sprintf( __( 'View %s', 'living-handbook' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'update_item'   => sprintf( __( 'Update %s', 'living-handbook' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'add_new_item'  => sprintf( __( 'Add new %s', 'living-handbook' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'new_item_name' => sprintf( __( 'New %s name', 'living-handbook' ), $singular ),
			/* translators: %s: plural taxonomy name. */
			'search_items'  => sprintf( __( 'Search %s', 'living-handbook' ), $name ),
			'not_found'     => __( 'None found.', 'living-handbook' ),
			/* translators: %s: plural taxonomy name. */
			'back_to_items' => sprintf( __( 'Back to %s', 'living-handbook' ), $name ),
		);
	}
}
