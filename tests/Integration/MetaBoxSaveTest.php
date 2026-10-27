<?php
/**
 * The meta box and Quick Edit keep the review date and the heading depth to
 * the values the field sanitizers allow.
 *
 * The three save paths used to repeat the date pattern and the depth limit on
 * their own. They now call the field sanitizers, and these tests hold the rule
 * in place for both forms.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Metadata::save and Metadata::save_quick_edit.
 */
final class MetaBoxSaveTest extends WP_UnitTestCase {

	/**
	 * Logs in an editor.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * Cleans up the faked request.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * A handbook page.
	 *
	 * @return int
	 */
	private function page(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'A page',
			)
		);
	}

	/**
	 * The meta box stores a well-formed date and a depth up to 6, and turns
	 * anything else into "no date" and "block default".
	 *
	 * @return void
	 */
	public function test_the_meta_box_keeps_date_and_depth_in_range(): void {
		$page = $this->page();
		$box  = new Metadata();

		$cases = array(
			array( '2026-05-06', '3', '2026-05-06', 3 ),
			array( '6 May 2026', '9', '', 0 ),
			array( ' 2026-05-06 ', '6', '2026-05-06', 6 ),
		);
		foreach ( $cases as $case ) {
			$_POST = wp_slash(
				array(
					'living_handbook_meta_nonce' => wp_create_nonce( 'living_handbook_meta' ),
					'living_handbook_reviewed'   => $case[0],
					'living_handbook_toc_depth'  => $case[1],
				)
			);
			$box->save( $page );

			$this->assertSame( $case[2], (string) get_post_meta( $page, Metadata::REVIEWED, true ), 'Date: ' . $case[0] );
			$this->assertSame( $case[3], (int) get_post_meta( $page, Metadata::TOC_DEPTH, true ), 'Depth: ' . $case[1] );
		}
	}

	/**
	 * Quick Edit follows the same date rule.
	 *
	 * @return void
	 */
	public function test_quick_edit_keeps_the_date_well_formed(): void {
		$page = $this->page();
		$box  = new Metadata();

		foreach ( array( '2026-05-06' => '2026-05-06', 'yesterday' => '' ) as $posted => $stored ) {
			$_POST = wp_slash(
				array(
					'living_handbook_qe_nonce' => wp_create_nonce( 'living_handbook_quick_edit' ),
					'living_handbook_reviewed' => $posted,
					'living_handbook_interval' => '30',
				)
			);
			$box->save_quick_edit( $page );

			$this->assertSame( $stored, (string) get_post_meta( $page, Metadata::REVIEWED, true ), 'Date: ' . $posted );
		}
	}
}
