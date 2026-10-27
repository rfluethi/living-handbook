<?php
/**
 * The cached area tiles and overview previews follow the pages they list.
 *
 * Both are kept for a day under a shared version number. Every change that
 * removes a page has to bump it, or a page that is gone stays on the entry
 * page as a link to nothing.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Cards;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Navigation::invalidate_for_post, as hooked in Blocks::register().
 */
final class CardsCacheTest extends WP_UnitTestCase {

	/**
	 * A public handbook with two top-level pages, its area tiles rendered once
	 * so they are cached.
	 *
	 * @return array{int, int} Term id and the id of the page that will go.
	 */
	private function cached_handbook(): array {
		$term = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Cached',
			)
		);
		update_term_meta( $term, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );

		$ids = array();
		foreach ( array( 'Staying page', 'Leaving page' ) as $title ) {
			$id = (int) self::factory()->post->create(
				array(
					'post_type'   => Handbook::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $title,
				)
			);
			wp_set_object_terms( $id, array( $term ), Handbooks::TAXONOMY );
			$ids[] = $id;
		}

		$this->assertStringContainsString( 'Leaving page', Cards::areas( $term ) );

		return array( $term, $ids[1] );
	}

	/**
	 * A page moved to the trash leaves the tiles.
	 *
	 * @return void
	 */
	public function test_a_trashed_page_leaves_the_tiles(): void {
		list( $term, $leaving ) = $this->cached_handbook();

		wp_trash_post( $leaving );

		$this->assertStringNotContainsString( 'Leaving page', Cards::areas( $term ) );
	}

	/**
	 * A page deleted for good, without the trash, leaves them too. Up to
	 * 0.75.1 only the trash retired the cache, and a page deleted directly
	 * stayed on the entry page for up to a day.
	 *
	 * @return void
	 */
	public function test_a_page_deleted_for_good_leaves_the_tiles(): void {
		list( $term, $leaving ) = $this->cached_handbook();

		wp_delete_post( $leaving, true );

		$areas = Cards::areas( $term );
		$this->assertStringContainsString( 'Staying page', $areas );
		$this->assertStringNotContainsString( 'Leaving page', $areas );
	}
}
