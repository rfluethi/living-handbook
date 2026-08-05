<?php
/**
 * Bulk Edit for the freshness fields, and the Handbook column.
 *
 * Quick Edit answers "I reviewed this page today". A handbook of two hundred
 * pages raises a different question, "these forty are reviewed yearly by the
 * same person", and answering it page by page is what makes a large handbook
 * feel unmaintainable. The rule that matters here is the one about fields left
 * empty: a bulk edit must never clear what it was not asked about.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Admin\Maintenance;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Metadata::save_bulk_edit and Maintenance::columns.
 */
final class BulkEditTest extends WP_UnitTestCase {

	/**
	 * A handbook page with review meta already set.
	 *
	 * @param string $reviewed Review date.
	 * @param int    $interval Interval in days.
	 * @param int    $reviewer Reviewer user id.
	 * @return int Post id.
	 */
	private function page( string $reviewed = '2026-01-01', int $interval = 90, int $reviewer = 0 ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'A page',
			)
		);
		update_post_meta( $id, Metadata::REVIEWED, $reviewed );
		update_post_meta( $id, Metadata::INTERVAL, $interval );
		update_post_meta( $id, Metadata::REVIEWER, $reviewer );

		return $id;
	}

	/**
	 * Put a bulk edit request in place.
	 *
	 * @param array<string, mixed> $fields Submitted fields.
	 * @return void
	 */
	private function bulk_request( array $fields ): void {
		$_REQUEST = array_merge(
			array(
				'bulk_edit'                => 'Update',
				'living_handbook_be_nonce' => wp_create_nonce( 'living_handbook_bulk_edit' ),
			),
			$fields
		);
	}

	/**
	 * Clean up the faked request.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * A submitted field is written to every selected page.
	 *
	 * @return void
	 */
	public function test_a_submitted_field_is_applied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$one = $this->page();
		$two = $this->page();

		$this->bulk_request( array( 'living_handbook_interval' => '365' ) );
		( new Metadata() )->save_bulk_edit( $one );
		( new Metadata() )->save_bulk_edit( $two );

		$this->assertSame( '365', (string) get_post_meta( $one, Metadata::INTERVAL, true ) );
		$this->assertSame( '365', (string) get_post_meta( $two, Metadata::INTERVAL, true ) );
	}

	/**
	 * And a field left empty is left alone. This is the one that matters: the
	 * bulk edit form submits every field, so "not filled in" and "cleared" look
	 * identical on the wire and have to be told apart here.
	 *
	 * @return void
	 */
	public function test_an_empty_field_changes_nothing(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->page( '2026-03-04', 90, 7 );

		$this->bulk_request(
			array(
				'living_handbook_interval' => '365',
				'living_handbook_reviewed' => '',
				'living_handbook_reviewer' => '-1',
			)
		);
		( new Metadata() )->save_bulk_edit( $id );

		$this->assertSame( '365', (string) get_post_meta( $id, Metadata::INTERVAL, true ), 'The interval was the point.' );
		$this->assertSame( '2026-03-04', (string) get_post_meta( $id, Metadata::REVIEWED, true ), 'The date must survive.' );
		$this->assertSame( '7', (string) get_post_meta( $id, Metadata::REVIEWER, true ), 'The reviewer must survive.' );
	}

	/**
	 * Without the marker WordPress sets on a bulk edit, nothing runs. An
	 * ordinary save carries the same field names from the meta box.
	 *
	 * @return void
	 */
	public function test_nothing_happens_outside_a_bulk_edit(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->page( '2026-03-04', 90 );

		$_REQUEST = array(
			'living_handbook_be_nonce' => wp_create_nonce( 'living_handbook_bulk_edit' ),
			'living_handbook_interval' => '365',
		);
		( new Metadata() )->save_bulk_edit( $id );

		$this->assertSame( '90', (string) get_post_meta( $id, Metadata::INTERVAL, true ) );
	}

	/**
	 * A wrong nonce writes nothing, and neither does a user who may not edit
	 * this page.
	 *
	 * @return void
	 */
	public function test_a_bad_nonce_or_a_wrong_user_writes_nothing(): void {
		$id = $this->page( '2026-03-04', 90 );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST = array(
			'bulk_edit'                => 'Update',
			'living_handbook_be_nonce' => 'not-a-nonce',
			'living_handbook_interval' => '365',
		);
		( new Metadata() )->save_bulk_edit( $id );
		$this->assertSame( '90', (string) get_post_meta( $id, Metadata::INTERVAL, true ), 'A bad nonce must write nothing.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->bulk_request( array( 'living_handbook_interval' => '365' ) );
		( new Metadata() )->save_bulk_edit( $id );
		$this->assertSame( '90', (string) get_post_meta( $id, Metadata::INTERVAL, true ), 'A subscriber must write nothing.' );
	}

	/**
	 * The list gains a Handbook column, directly after the title. Until now the
	 * list mixed every handbook and no row said which one it belonged to.
	 *
	 * @return void
	 */
	public function test_the_list_has_a_handbook_column_after_the_title(): void {
		$columns = ( new Maintenance() )->columns(
			array(
				'cb'    => '',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		$keys = array_keys( $columns );
		$this->assertContains( 'living_handbook_set', $keys );
		$this->assertSame( 'living_handbook_set', $keys[ array_search( 'title', $keys, true ) + 1 ] );
	}

	/**
	 * The column shows the handbook of the page, and says so when there is
	 * none, because a page without a handbook is invisible on the front end and
	 * this list is where that is noticed.
	 *
	 * @return void
	 */
	public function test_the_handbook_column_names_the_handbook_or_its_absence(): void {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Operations',
			)
		);
		$with = $this->page();
		wp_set_object_terms( $with, array( (int) $term->term_id ), Handbooks::TAXONOMY );
		$without = $this->page();

		$maintenance = new Maintenance();

		ob_start();
		$maintenance->render_column( 'living_handbook_set', $with );
		$this->assertStringContainsString( 'Operations', (string) ob_get_clean() );

		ob_start();
		$maintenance->render_column( 'living_handbook_set', $without );
		$this->assertStringContainsString( 'invisible', (string) ob_get_clean() );
	}
}
