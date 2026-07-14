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
 * Registers the four handbook taxonomies: page type, topic (shown as "Areas"),
 * responsible role (shown as "Responsibility"), and audience. They are not
 * public on their own; the frontend access check governs visibility.
 */
final class Taxonomies {

	public const PAGE_TYPE = 'handbook_type';
	public const TOPIC     = 'handbook_topic';
	public const ROLE      = 'handbook_role';
	public const AUDIENCE  = 'handbook_audience';

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
				true
			)
		);

		register_taxonomy(
			self::TOPIC,
			$object,
			$this->args(
				__( 'Areas', 'living-handbook' ),
				__( 'Area', 'living-handbook' ),
				'handbook-topic',
				true
			)
		);

		register_taxonomy(
			self::ROLE,
			$object,
			$this->args(
				__( 'Responsibility', 'living-handbook' ),
				__( 'Responsibility', 'living-handbook' ),
				'handbook-role',
				false
			)
		);

		register_taxonomy(
			self::AUDIENCE,
			$object,
			$this->args(
				__( 'Audiences', 'living-handbook' ),
				__( 'Audience', 'living-handbook' ),
				'handbook-audience',
				true
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
	 * @return array<string, mixed>
	 */
	private function args( string $name, string $singular, string $slug, bool $hierarchical ): array {
		return array(
			'labels'            => self::labels( $name, $singular ),
			'hierarchical'      => $hierarchical,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
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
