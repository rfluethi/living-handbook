<?php
/**
 * Seeds the four classifying taxonomies on activation.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Setup;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Taxonomy\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inserts the default terms so every author picks from the same list.
 *
 * Term names use translatable strings, so on a localised site the seeded terms
 * are created in that language.
 */
final class Seeder {

	/**
	 * Seed all default terms.
	 *
	 * @return void
	 */
	public static function seed(): void {
		self::seed_terms(
			Taxonomies::PAGE_TYPE,
			array(
				__( 'Guide', 'living-handbook' ),
				__( 'Process description', 'living-handbook' ),
				__( 'Tool overview', 'living-handbook' ),
				__( 'Role / Organisation', 'living-handbook' ),
				__( 'Background / Concept', 'living-handbook' ),
				__( 'FAQ', 'living-handbook' ),
				__( 'Area overview', 'living-handbook' ),
			)
		);

		self::seed_terms(
			Taxonomies::AUDIENCE,
			array(
				__( 'All members', 'living-handbook' ),
				__( 'Content creators', 'living-handbook' ),
				__( 'Coordination', 'living-handbook' ),
				__( 'Tech', 'living-handbook' ),
			)
		);

		self::seed_terms(
			Taxonomies::ROLE,
			array(
				__( 'Handbook editors', 'living-handbook' ),
				__( 'GitHub specialist', 'living-handbook' ),
			)
		);

		self::seed_terms(
			Handbooks::TAXONOMY,
			array(
				__( 'General', 'living-handbook' ),
			)
		);
	}

	/**
	 * Insert a list of terms into a taxonomy if they do not exist yet.
	 *
	 * @param string   $taxonomy Taxonomy key.
	 * @param string[] $terms    Term names.
	 * @return void
	 */
	private static function seed_terms( string $taxonomy, array $terms ): void {
		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, $taxonomy ) ) {
				wp_insert_term( $term, $taxonomy );
			}
		}
	}
}
