<?php
/**
 * Access control integration tests.
 *
 * These document and verify the security-critical visibility rules.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Access\AccessController;
use WP_UnitTestCase;

/**
 * Per-handbook frontend visibility.
 */
final class AccessTest extends WP_UnitTestCase {

	/**
	 * Create a handbook (grouping term) with an access configuration.
	 *
	 * @param string   $visibility Visibility value.
	 * @param string[] $roles      Allowed roles.
	 * @param int[]    $users      Allowed user IDs.
	 * @return int Term ID.
	 */
	private function make_handbook( string $visibility, array $roles = array(), array $users = array() ): int {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Handbook ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, 'living_handbook_visibility', $visibility );
		if ( array() !== $roles ) {
			update_term_meta( $term_id, 'living_handbook_roles', $roles );
		}
		if ( array() !== $users ) {
			update_term_meta( $term_id, 'living_handbook_users', $users );
		}
		return (int) $term_id;
	}

	/**
	 * Create a handbook page assigned to a handbook.
	 *
	 * @param int $term_id Handbook term ID.
	 * @return int Post ID.
	 */
	private function make_page( int $term_id ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );
		return (int) $post_id;
	}

	/**
	 * A public handbook is visible to a guest.
	 *
	 * @return void
	 */
	public function test_public_visible_to_guest(): void {
		$page = $this->make_page( $this->make_handbook( 'public' ) );
		$this->assertTrue( AccessController::can_view_post( $page, 0 ) );
	}

	/**
	 * A members handbook is hidden from a guest.
	 *
	 * @return void
	 */
	public function test_members_hidden_from_guest(): void {
		$page = $this->make_page( $this->make_handbook( 'members' ) );
		$this->assertFalse( AccessController::can_view_post( $page, 0 ) );
	}

	/**
	 * A members handbook is visible to any logged-in user.
	 *
	 * @return void
	 */
	public function test_members_visible_to_subscriber(): void {
		$page = $this->make_page( $this->make_handbook( 'members' ) );
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertTrue( AccessController::can_view_post( $page, (int) $user ) );
	}

	/**
	 * A restricted handbook is visible to a user with an allowed role.
	 *
	 * @return void
	 */
	public function test_restricted_allows_listed_role(): void {
		$page = $this->make_page( $this->make_handbook( 'restricted', array( 'subscriber' ) ) );
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertTrue( AccessController::can_view_post( $page, (int) $user ) );
	}

	/**
	 * A restricted handbook is hidden from a user without an allowed role.
	 *
	 * @return void
	 */
	public function test_restricted_denies_other_role(): void {
		$page = $this->make_page( $this->make_handbook( 'restricted', array( 'author' ) ) );
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertFalse( AccessController::can_view_post( $page, (int) $user ) );
	}

	/**
	 * A page without a handbook is fail-closed, even for a member.
	 *
	 * @return void
	 */
	public function test_no_handbook_is_fail_closed(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
			)
		);
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertFalse( AccessController::can_view_post( (int) $page, (int) $user ) );
	}

	/**
	 * A content manager (editor) sees everything.
	 *
	 * @return void
	 */
	public function test_editor_sees_restricted(): void {
		$page = $this->make_page( $this->make_handbook( 'restricted', array( 'administrator' ) ) );
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->assertTrue( AccessController::can_view_post( $page, (int) $user ) );
	}

	/**
	 * A query that sets suppress_filters (the get_posts default) still hides a
	 * members-only page from a guest: the pre_get_posts layer restricts it even
	 * though the_posts is bypassed. This is the F1 side-channel.
	 *
	 * @return void
	 */
	public function test_suppress_filters_query_hides_members_page_from_guest(): void {
		$public_page  = $this->make_page( $this->make_handbook( 'public' ) );
		$members_page = $this->make_page( $this->make_handbook( 'members' ) );

		wp_set_current_user( 0 );
		$ids = array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => 'handbook',
					'post_status'      => 'publish',
					'fields'           => 'ids',
					'suppress_filters' => true,
					'posts_per_page'   => -1,
				)
			)
		);

		$this->assertContains( $public_page, $ids );
		$this->assertNotContains( $members_page, $ids );
	}

	/**
	 * The same suppress_filters query shows the members-only page to a logged-in
	 * user, so the coarse layer restricts without over-blocking.
	 *
	 * @return void
	 */
	public function test_suppress_filters_query_shows_members_page_to_member(): void {
		$members_page = $this->make_page( $this->make_handbook( 'members' ) );
		$user         = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( (int) $user );
		$ids = array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => 'handbook',
					'post_status'      => 'publish',
					'fields'           => 'ids',
					'suppress_filters' => true,
					'posts_per_page'   => -1,
				)
			)
		);
		wp_set_current_user( 0 );

		$this->assertContains( $members_page, $ids );
	}

	/**
	 * Run the coarse layer in isolation: suppress_filters bypasses the_posts, so
	 * only restrict_query() shapes the result.
	 *
	 * @return int[]
	 */
	private function handbook_ids(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => 'handbook',
					'post_status'      => 'publish',
					'fields'           => 'ids',
					'suppress_filters' => true,
					'posts_per_page'   => -1,
				)
			)
		);
	}

	/**
	 * On the front end the coarse layer restricts an editing user just like any
	 * other: an author who is not in a restricted handbook does not see its page.
	 * This is the control for the AJAX case below.
	 *
	 * @return void
	 */
	public function test_frontend_query_restricts_editing_user(): void {
		$page   = $this->make_page( $this->make_handbook( 'restricted', array( 'administrator' ) ) );
		$author = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertFalse( AccessController::can_view_post( $page, (int) $author ) );

		wp_set_current_user( (int) $author );
		$ids = $this->handbook_ids();
		wp_set_current_user( 0 );

		$this->assertNotContains( $page, $ids );
	}

	/**
	 * A back-end AJAX read (admin-ajax.php, for example the classic editor's link
	 * search) does not apply the coarse restriction to a user who may edit posts.
	 * Their back-end view is unrestricted, so the link picker stays consistent
	 * with it. This is the N3 decision, Variante A.
	 *
	 * @return void
	 */
	public function test_backend_ajax_does_not_restrict_editing_user(): void {
		$page   = $this->make_page( $this->make_handbook( 'restricted', array( 'administrator' ) ) );
		$author = self::factory()->user->create( array( 'role' => 'author' ) );

		$this->assertFalse( AccessController::can_view_post( $page, (int) $author ) );

		wp_set_current_user( (int) $author );
		set_current_screen( 'edit.php' );
		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->assertTrue( is_admin(), 'precondition: admin context' );
		$this->assertTrue( wp_doing_ajax(), 'precondition: AJAX' );

		$ids = $this->handbook_ids();

		remove_filter( 'wp_doing_ajax', '__return_true' );
		set_current_screen( 'front' );
		wp_set_current_user( 0 );

		$this->assertContains( $page, $ids );
	}

	/**
	 * A back-end AJAX read still restricts a user who may not edit posts: a
	 * subscriber does not gain the bypass, so a restricted handbook page stays
	 * hidden from them even over admin-ajax.
	 *
	 * @return void
	 */
	public function test_backend_ajax_still_restricts_non_editing_user(): void {
		$page       = $this->make_page( $this->make_handbook( 'restricted', array( 'administrator' ) ) );
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( (int) $subscriber );
		set_current_screen( 'edit.php' );
		add_filter( 'wp_doing_ajax', '__return_true' );

		$ids = $this->handbook_ids();

		remove_filter( 'wp_doing_ajax', '__return_true' );
		set_current_screen( 'front' );
		wp_set_current_user( 0 );

		$this->assertNotContains( $page, $ids );
	}
}
