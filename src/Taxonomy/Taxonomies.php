<?php
/**
 * Handbook taxonomies.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Taxonomy;

use LivingHandbook\Handbook\Handbooks;
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
				self::PAGE_TYPE,
				true,
				self::is_enabled( self::PAGE_TYPE )
			)
		);

		register_taxonomy(
			self::TOPIC,
			$object,
			$this->args(
				self::TOPIC,
				true,
				self::is_enabled( self::TOPIC )
			)
		);

		register_taxonomy(
			self::ROLE,
			$object,
			$this->args(
				self::ROLE,
				false,
				self::is_enabled( self::ROLE )
			)
		);

		register_taxonomy(
			self::AUDIENCE,
			$object,
			$this->args(
				self::AUDIENCE,
				true,
				self::is_enabled( self::AUDIENCE )
			)
		);
	}

	/**
	 * Build a common taxonomy argument array with a full label set, so the term
	 * screens do not fall back to the generic category or tag labels.
	 *
	 * @param string $taxonomy     Taxonomy name.
	 * @param bool   $hierarchical Whether the taxonomy is hierarchical.
	 * @param bool   $enabled      Whether this site uses this taxonomy at all.
	 * @return array<string, mixed>
	 */
	private function args( string $taxonomy, bool $hierarchical, bool $enabled = true ): array {
		return array(
			'labels'            => self::taxonomy_labels( $taxonomy ),
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
			// No addresses of their own. These taxonomies are not public, there
			// is no archive of a topic, and the front end filters by its own
			// parameters (lh_type and the others, see Frontend\Filters). A rewrite
			// slug still made core write a permastruct for each, so every flush
			// stored rules for /handbook-topic/... and the rest that led nowhere.
			//
			// The query var is left at its default on purpose. Core keeps it only
			// in the admin for a taxonomy that is not publicly queryable, and the
			// admin needs it: the filter dropdowns above the handbook list and the
			// term links in its columns send ?handbook_topic=<slug>. Switching it
			// off turned those filters into no-ops (TaxonomyRewriteTest).
			'rewrite'           => false,
		);
	}

	/**
	 * The full label set of one of the plugin's taxonomies.
	 *
	 * Every label is a whole string of its own. They used to be assembled from
	 * fragments, sprintf( __( 'Add new %s' ), $singular ), which English gets
	 * away with and German does not: article and adjective ending follow the
	 * gender of the noun, so "Add new %s" is "Neues Thema hinzufügen", "Neuen
	 * Seitentyp hinzufügen" and "Neue Zielgruppe hinzufügen", and no single
	 * translation of the fragment can be right for all of them. The catalogue
	 * had settled on "%s hinzufügen" and dropped the "new". Spelled out, each
	 * string can be translated as what it is.
	 *
	 * Hierarchical taxonomies get their parent labels, the one flat one
	 * (responsibility) the labels of the tag-style box, so neither shows core's
	 * "Parent Category" or "Popular Tags".
	 *
	 * Named taxonomy_labels() and not labels(): up to 0.75.x labels() took a
	 * plural and a singular name, and a caller of that signature would have
	 * received the labels of no taxonomy without an error. Under a new name it
	 * fails loudly instead.
	 *
	 * @internal Not part of the plugin's public API.
	 *
	 * @param string $taxonomy One of the plugin's taxonomies, the handbook grouping included.
	 * @return array<string, string>
	 */
	public static function taxonomy_labels( string $taxonomy ): array {
		switch ( $taxonomy ) {
			case Handbooks::TAXONOMY:
				return array(
					'name'              => __( 'Handbooks', 'living-handbook' ),
					'singular_name'     => __( 'Handbook', 'living-handbook' ),
					'menu_name'         => __( 'Handbooks', 'living-handbook' ),
					'all_items'         => __( 'Handbooks', 'living-handbook' ),
					'edit_item'         => __( 'Edit handbook', 'living-handbook' ),
					'view_item'         => __( 'View handbook', 'living-handbook' ),
					'update_item'       => __( 'Update handbook', 'living-handbook' ),
					'add_new_item'      => __( 'Add new handbook', 'living-handbook' ),
					'new_item_name'     => __( 'New handbook name', 'living-handbook' ),
					'search_items'      => __( 'Search handbooks', 'living-handbook' ),
					'not_found'         => __( 'No handbooks found.', 'living-handbook' ),
					'back_to_items'     => __( 'Back to handbooks', 'living-handbook' ),
					'parent_item'       => __( 'Parent handbook', 'living-handbook' ),
					'parent_item_colon' => __( 'Parent handbook:', 'living-handbook' ),
				);

			case self::PAGE_TYPE:
				return array(
					'name'              => __( 'Page types', 'living-handbook' ),
					'singular_name'     => __( 'Page type', 'living-handbook' ),
					'menu_name'         => __( 'Page types', 'living-handbook' ),
					'all_items'         => __( 'Page types', 'living-handbook' ),
					'edit_item'         => __( 'Edit page type', 'living-handbook' ),
					'view_item'         => __( 'View page type', 'living-handbook' ),
					'update_item'       => __( 'Update page type', 'living-handbook' ),
					'add_new_item'      => __( 'Add new page type', 'living-handbook' ),
					'new_item_name'     => __( 'New page type name', 'living-handbook' ),
					'search_items'      => __( 'Search page types', 'living-handbook' ),
					'not_found'         => __( 'No page types found.', 'living-handbook' ),
					'back_to_items'     => __( 'Back to page types', 'living-handbook' ),
					'parent_item'       => __( 'Parent page type', 'living-handbook' ),
					'parent_item_colon' => __( 'Parent page type:', 'living-handbook' ),
				);

			case self::TOPIC:
				return array(
					'name'              => __( 'Topics', 'living-handbook' ),
					'singular_name'     => __( 'Topic', 'living-handbook' ),
					'menu_name'         => __( 'Topics', 'living-handbook' ),
					'all_items'         => __( 'Topics', 'living-handbook' ),
					'edit_item'         => __( 'Edit topic', 'living-handbook' ),
					'view_item'         => __( 'View topic', 'living-handbook' ),
					'update_item'       => __( 'Update topic', 'living-handbook' ),
					'add_new_item'      => __( 'Add new topic', 'living-handbook' ),
					'new_item_name'     => __( 'New topic name', 'living-handbook' ),
					'search_items'      => __( 'Search topics', 'living-handbook' ),
					'not_found'         => __( 'No topics found.', 'living-handbook' ),
					'back_to_items'     => __( 'Back to topics', 'living-handbook' ),
					'parent_item'       => __( 'Parent topic', 'living-handbook' ),
					'parent_item_colon' => __( 'Parent topic:', 'living-handbook' ),
				);

			case self::ROLE:
				return array(
					'name'                       => __( 'Responsibility', 'living-handbook' ),
					'singular_name'              => __( 'Responsibility', 'living-handbook' ),
					'menu_name'                  => __( 'Responsibility', 'living-handbook' ),
					'all_items'                  => __( 'Responsibility', 'living-handbook' ),
					'edit_item'                  => __( 'Edit responsibility', 'living-handbook' ),
					'view_item'                  => __( 'View responsibility', 'living-handbook' ),
					'update_item'                => __( 'Update responsibility', 'living-handbook' ),
					'add_new_item'               => __( 'Add new responsibility', 'living-handbook' ),
					'new_item_name'              => __( 'New responsibility name', 'living-handbook' ),
					'search_items'               => __( 'Search responsibilities', 'living-handbook' ),
					'not_found'                  => __( 'No responsibilities found.', 'living-handbook' ),
					'back_to_items'              => __( 'Back to responsibilities', 'living-handbook' ),
					'popular_items'              => __( 'Frequently used responsibilities', 'living-handbook' ),
					'separate_items_with_commas' => __( 'Separate responsibilities with commas', 'living-handbook' ),
					'add_or_remove_items'        => __( 'Add or remove responsibilities', 'living-handbook' ),
					'choose_from_most_used'      => __( 'Choose from the most used responsibilities', 'living-handbook' ),
				);

			case self::AUDIENCE:
			default:
				return array(
					'name'              => __( 'Audiences', 'living-handbook' ),
					'singular_name'     => __( 'Audience', 'living-handbook' ),
					'menu_name'         => __( 'Audiences', 'living-handbook' ),
					'all_items'         => __( 'Audiences', 'living-handbook' ),
					'edit_item'         => __( 'Edit audience', 'living-handbook' ),
					'view_item'         => __( 'View audience', 'living-handbook' ),
					'update_item'       => __( 'Update audience', 'living-handbook' ),
					'add_new_item'      => __( 'Add new audience', 'living-handbook' ),
					'new_item_name'     => __( 'New audience name', 'living-handbook' ),
					'search_items'      => __( 'Search audiences', 'living-handbook' ),
					'not_found'         => __( 'No audiences found.', 'living-handbook' ),
					'back_to_items'     => __( 'Back to audiences', 'living-handbook' ),
					'parent_item'       => __( 'Parent audience', 'living-handbook' ),
					'parent_item_colon' => __( 'Parent audience:', 'living-handbook' ),
				);
		}
	}
}
