<?php
/**
 * The personal data the plugin keeps is reachable through WordPress's privacy
 * tools, and goes with a deleted account.
 *
 * Two places hold a user id: the voter list of a page (so a signed-in reader's
 * "Was this helpful?" counts once) and the reviewer of a page. Neither showed
 * up in Tools > Export Personal Data or Erase Personal Data, the privacy policy
 * guide had no text for the plugin, and a deleted account stayed in every voter
 * list.
 *
 * The eraser treats the two differently, deliberately: the voter id goes and
 * the totals stay; the reviewer stays and is reported as retained, because who
 * reviewed a page is the page's editorial record. See Privacy for the reasoning.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Privacy\Privacy.
 */
final class PrivacyTest extends WP_UnitTestCase {

	/**
	 * A handbook page with votes from the given users.
	 *
	 * @param array<int, int> $voters User ids.
	 * @param int             $yes    Yes count.
	 * @return int
	 */
	private function page_with_voters( array $voters, int $yes = 0 ): int {
		$page = (int) self::factory()->post->create(
			array(
				'post_type'  => Handbook::POST_TYPE,
				'post_title' => 'Onboarding',
			)
		);
		update_post_meta( $page, Feedback::VOTERS, $voters );
		update_post_meta( $page, Feedback::YES, $yes );

		return $page;
	}

	/**
	 * The callback WordPress would call for a registered exporter or eraser.
	 *
	 * @param string $filter wp_privacy_personal_data_exporters or ..._erasers.
	 * @param string $key    The plugin's key.
	 * @return callable
	 */
	private function registered( string $filter, string $key ): callable {
		$registered = apply_filters( $filter, array() );
		$this->assertArrayHasKey( $key, $registered, $key . ' is not registered with ' . $filter );

		return $registered[ $key ]['callback'];
	}

	/**
	 * The exporters list the pages someone voted on and the pages they review,
	 * and nothing about anybody else.
	 *
	 * @return void
	 */
	public function test_the_exporters_list_votes_and_reviews(): void {
		$person = (int) self::factory()->user->create( array( 'user_email' => 'reader@example.org' ) );
		$other  = (int) self::factory()->user->create();

		$voted    = $this->page_with_voters( array( $other, $person ) );
		$not      = $this->page_with_voters( array( $other ) );
		$reviewed = $this->page_with_voters( array() );
		update_post_meta( $reviewed, Metadata::REVIEWER, $person );

		$feedback = call_user_func( $this->registered( 'wp_privacy_personal_data_exporters', 'living-handbook-feedback' ), 'reader@example.org', 1 );
		$items    = wp_list_pluck( $feedback['data'], 'item_id' );
		$this->assertContains( 'living-handbook-feedback-' . $voted, $items );
		$this->assertNotContains( 'living-handbook-feedback-' . $not, $items );
		$this->assertTrue( $feedback['done'] );

		$reviews = call_user_func( $this->registered( 'wp_privacy_personal_data_exporters', 'living-handbook-reviewer' ), 'reader@example.org', 1 );
		$this->assertSame( array( 'living-handbook-reviewer-' . $reviewed ), wp_list_pluck( $reviews['data'], 'item_id' ) );

		$nobody = call_user_func( $this->registered( 'wp_privacy_personal_data_exporters', 'living-handbook-feedback' ), 'nobody@example.org', 1 );
		$this->assertSame( array(), $nobody['data'] );
	}

	/**
	 * The eraser takes the id out of the voter lists and keeps the totals;
	 * the reviewer is kept and reported.
	 *
	 * @return void
	 */
	public function test_the_eraser_removes_votes_and_retains_reviews(): void {
		$person = (int) self::factory()->user->create( array( 'user_email' => 'reader@example.org' ) );
		$other  = (int) self::factory()->user->create();

		$voted = $this->page_with_voters( array( $other, $person ), 2 );
		update_post_meta( $voted, Metadata::REVIEWER, $person );

		$result = call_user_func( $this->registered( 'wp_privacy_personal_data_erasers', 'living-handbook' ), 'reader@example.org', 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertNotEmpty( $result['messages'] );
		$this->assertTrue( $result['done'] );

		$this->assertSame( array( $other ), array_map( 'intval', (array) get_post_meta( $voted, Feedback::VOTERS, true ) ) );
		$this->assertSame( 2, (int) get_post_meta( $voted, Feedback::YES, true ), 'The totals stay.' );
		$this->assertSame( $person, (int) get_post_meta( $voted, Metadata::REVIEWER, true ), 'The reviewer is the page\'s record.' );
	}

	/**
	 * A deleted account leaves the voter lists, the trash included.
	 *
	 * @return void
	 */
	public function test_a_deleted_account_leaves_the_voter_lists(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$person  = (int) self::factory()->user->create();
		$other   = (int) self::factory()->user->create();
		$page    = $this->page_with_voters( array( $person, $other ), 2 );
		$trashed = $this->page_with_voters( array( $person ) );
		wp_trash_post( $trashed );

		wp_delete_user( $person );

		$this->assertSame( array( $other ), array_map( 'intval', (array) get_post_meta( $page, Feedback::VOTERS, true ) ) );
		$this->assertSame( array(), (array) get_post_meta( $trashed, Feedback::VOTERS, true ) );
		$this->assertSame( 2, (int) get_post_meta( $page, Feedback::YES, true ) );
	}

	/**
	 * The privacy policy guide gets a text that names what the plugin does.
	 *
	 * @return void
	 */
	public function test_the_policy_guide_gets_a_text(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
		set_current_screen( 'dashboard' );

		// Only the plugin's own callback, inside admin_init as core requires: the
		// full action would run every other admin_init handler of the test site.
		global $wp_actions;
		$wp_actions['admin_init'] = 1; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Marks admin_init as done for wp_add_privacy_policy_content().
		( new \LivingHandbook\Privacy\Privacy() )->add_policy_content();
		$hooked = false;
		foreach ( $GLOBALS['wp_filter']['admin_init']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$hooked = $hooked || ( is_array( $callback['function'] ) && $callback['function'][0] instanceof \LivingHandbook\Privacy\Privacy );
			}
		}
		$this->assertTrue( $hooked, 'The policy text is added on admin_init.' );

		$texts = array();
		foreach ( \WP_Privacy_Policy_Content::get_suggested_policy_text() as $entry ) {
			if ( 'Living Handbook' === $entry['plugin_name'] ) {
				$texts[] = $entry['policy_text'];
			}
		}
		$this->assertCount( 1, $texts );
		foreach ( array( 'Gravatar', 'GitHub', 'local storage', 'counted only once' ) as $needle ) {
			$this->assertStringContainsString( $needle, $texts[0] );
		}
	}
}
