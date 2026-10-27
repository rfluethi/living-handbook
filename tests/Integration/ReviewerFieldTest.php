<?php
/**
 * The reviewer field of the page details box saves "none" as nobody.
 *
 * The box lists the users with an empty choice, "none", first. Up to 0.75.1
 * that choice posted -1, and the save turned it with absint() into 1: whoever
 * saved a page in the editor without touching the field made user 1 the
 * reviewer, and the public footer named that person.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\PageMeta;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Metadata::render and Metadata::save, through the form and save_post.
 */
final class ReviewerFieldTest extends WP_UnitTestCase {

	/**
	 * The editor who saves.
	 *
	 * @var int
	 */
	private $editor = 0;

	/**
	 * Log in an editor, who may edit every page.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->editor = (int) self::factory()->user->create(
			array(
				'role'         => 'editor',
				'display_name' => 'Edna Editor',
			)
		);
		wp_set_current_user( $this->editor );
	}

	/**
	 * Clean up the faked request.
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
	 * The form fields of the details box as the browser would send them: every
	 * field with the value it shows, the selected option of each dropdown.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string>
	 */
	private function submitted_form( int $post_id ): array {
		$post = get_post( $post_id );
		$this->assertNotNull( $post );
		ob_start();
		( new Metadata() )->render( $post );
		$html = (string) ob_get_clean();

		$fields = array();
		$this->assertSame( 1, preg_match( '/name="living_handbook_meta_nonce" value="([^"]+)"/', $html, $nonce ) );
		$fields['living_handbook_meta_nonce'] = $nonce[1];

		// The reviewer dropdown: the selected option, or the first one, as a
		// browser submits a select without a selected option.
		$this->assertSame( 1, preg_match( '/<select[^>]*name=[\'"]living_handbook_reviewer[\'"][^>]*>(.*?)<\/select>/s', $html, $select ), $html );
		$this->assertGreaterThan( 0, preg_match_all( '/<option[^>]*value=[\'"]([^\'"]*)[\'"]([^>]*)>/', $select[1], $options, PREG_SET_ORDER ) );
		$value = $options[0][1];
		foreach ( $options as $option ) {
			if ( false !== strpos( $option[2], 'selected' ) ) {
				$value = $option[1];
			}
		}
		$fields['living_handbook_reviewer']  = $value;
		$fields['living_handbook_reviewed']  = '';
		$fields['living_handbook_interval']  = '0';
		$fields['living_handbook_toc_depth'] = '0';

		return $fields;
	}

	/**
	 * Save a page with the given form, through save_post as the editor does.
	 *
	 * @param int                   $post_id Post ID.
	 * @param array<string, string> $fields  Posted fields.
	 * @return void
	 */
	private function save_with( int $post_id, array $fields ): void {
		$_POST = wp_slash( $fields );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'A page, saved',
			)
		);
	}

	/**
	 * Saving without touching the field leaves the page without a reviewer,
	 * and the footer names nobody.
	 *
	 * @return void
	 */
	public function test_saving_with_none_selected_stores_no_reviewer(): void {
		// User 1 exists in the test install: the person the old code chose.
		$this->assertNotFalse( get_userdata( 1 ) );
		$page = $this->page();

		$fields = $this->submitted_form( $page );
		$this->save_with( $page, $fields );

		$this->assertSame( 0, (int) get_post_meta( $page, Metadata::REVIEWER, true ), 'Posted value: ' . $fields['living_handbook_reviewer'] );
		$admin = get_userdata( 1 );
		$this->assertNotFalse( $admin );
		$this->assertStringNotContainsString( $admin->display_name, PageMeta::render_meta( $page ) );
	}

	/**
	 * A chosen reviewer is stored and stays when the page is saved again.
	 *
	 * @return void
	 */
	public function test_a_chosen_reviewer_is_kept_on_the_next_save(): void {
		$page = $this->page();

		$fields                             = $this->submitted_form( $page );
		$fields['living_handbook_reviewer'] = (string) $this->editor;
		$this->save_with( $page, $fields );
		$this->assertSame( $this->editor, (int) get_post_meta( $page, Metadata::REVIEWER, true ) );

		$this->save_with( $page, $this->submitted_form( $page ) );
		$this->assertSame( $this->editor, (int) get_post_meta( $page, Metadata::REVIEWER, true ) );
		$this->assertStringContainsString( 'Edna Editor', PageMeta::render_meta( $page ) );
	}

	/**
	 * A negative value, a value that is no number and the ID of a user who
	 * does not exist are all stored as nobody.
	 *
	 * @return void
	 */
	public function test_an_invalid_reviewer_is_stored_as_nobody(): void {
		$page = $this->page();

		foreach ( array( '-1', '-7', 'abc', '999999' ) as $value ) {
			update_post_meta( $page, Metadata::REVIEWER, $this->editor );
			$fields                             = $this->submitted_form( $page );
			$fields['living_handbook_reviewer'] = $value;
			$this->save_with( $page, $fields );
			$this->assertSame( 0, (int) get_post_meta( $page, Metadata::REVIEWER, true ), 'Posted value: ' . $value );
		}
	}
}
