<?php
/**
 * The four classifying taxonomies have no addresses of their own.
 *
 * Page type, topic, responsibility and audience are registered with
 * public => false: there is no archive of a topic, and the front end filters
 * by its own parameters (lh_type, lh_topic, lh_role, lh_audience). They were
 * still registered with a rewrite slug, and core adds the permastruct of a
 * taxonomy whenever rewrite is set, public or not. So every flush wrote rules
 * for /handbook-type/..., /handbook-topic/..., /handbook-role/... and
 * /handbook-audience/... that led nowhere: on the front end the taxonomy is not
 * queryable, and the request ended on whatever WordPress makes of a query it
 * has to ignore.
 *
 * The query var stays, deliberately. Core only keeps it in the admin for a
 * taxonomy that is not publicly queryable, and the admin is where it is used:
 * the filter dropdowns above the handbook list send ?handbook_topic=<slug>, and
 * the term links in the list columns do the same. Switching it off would
 * quietly turn those filters into no-ops.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_UnitTestCase;

/**
 * Rewrite rules and query vars of the classifying taxonomies.
 */
final class TaxonomyRewriteTest extends WP_UnitTestCase {

	/**
	 * Restore the request globals.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_GET = array();
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * No stored rule starts with one of the four slugs.
	 *
	 * @return void
	 */
	public function test_the_classifying_taxonomies_write_no_rewrite_rules(): void {
		$this->set_permalink_structure( '/%postname%/' );
		( new Taxonomies() )->register_taxonomies();
		( new Handbooks() )->register_taxonomy();
		flush_rewrite_rules( false );

		$patterns = array_keys( (array) get_option( 'rewrite_rules' ) );

		foreach ( array( 'handbook-type/', 'handbook-topic/', 'handbook-role/', 'handbook-audience/' ) as $slug ) {
			$found = array_filter( $patterns, static fn( $p ): bool => str_starts_with( (string) $p, $slug ) );
			$this->assertSame( array(), array_values( $found ), $slug . ' still has rewrite rules.' );
		}

		// The handbook grouping is public and keeps its address.
		$this->assertNotEmpty( array_filter( $patterns, static fn( $p ): bool => str_starts_with( (string) $p, 'handbook-set/' ) ) );

		foreach ( array_keys( Taxonomies::all() ) as $taxonomy ) {
			$this->assertFalse( get_taxonomy( $taxonomy )->rewrite, $taxonomy );
		}
	}

	/**
	 * The filter dropdown above the handbook list still narrows the list. This
	 * is what the query var is kept for.
	 *
	 * @return void
	 */
	public function test_the_admin_list_still_filters_by_a_taxonomy(): void {
		$tagged = self::factory()->post->create( array( 'post_type' => Handbook::POST_TYPE ) );
		$other  = self::factory()->post->create( array( 'post_type' => Handbook::POST_TYPE ) );
		wp_set_object_terms( $tagged, array( 'onboarding' ), Taxonomies::TOPIC );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'edit-' . Handbook::POST_TYPE );
		( new Taxonomies() )->register_taxonomies();

		$_GET = array(
			'post_type'       => Handbook::POST_TYPE,
			Taxonomies::TOPIC => 'onboarding',
			'orderby'         => 'title',
		);
		wp_edit_posts_query( $_GET );

		$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertContains( $tagged, $ids );
		$this->assertNotContains( $other, $ids, 'The topic filter of the list did nothing.' );
	}
}
