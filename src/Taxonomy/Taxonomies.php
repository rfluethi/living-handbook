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
 * Registers the four handbook taxonomies: page type, topic, responsible role,
 * and audience. They are not public on their own; the frontend access check
 * governs visibility.
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
				__( 'Topics', 'living-handbook' ),
				__( 'Topic', 'living-handbook' ),
				'handbook-topic',
				true
			)
		);

		register_taxonomy(
			self::ROLE,
			$object,
			$this->args(
				__( 'Responsible roles', 'living-handbook' ),
				__( 'Responsible role', 'living-handbook' ),
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
	 * Build a common taxonomy argument array.
	 *
	 * @param string $name         Plural label.
	 * @param string $singular     Singular label.
	 * @param string $slug         Rewrite slug.
	 * @param bool   $hierarchical Whether the taxonomy is hierarchical.
	 * @return array<string, mixed>
	 */
	private function args( string $name, string $singular, string $slug, bool $hierarchical ): array {
		return array(
			'labels'            => array(
				'name'          => $name,
				'singular_name' => $singular,
			),
			'hierarchical'      => $hierarchical,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => $slug ),
		);
	}
}
