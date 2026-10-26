<?php
/**
 * The two pieces of inline CSS and JavaScript in the admin go through
 * WordPress, and only where they are needed.
 *
 * The divider in the handbook submenu was echoed as a <style> tag into the head
 * of every admin page, for every user, including those who never see the
 * handbook menu. The colour picker set-up of the settings screen was printed by
 * hand into the footer as a <script> tag. Neither was attached to a handle, so
 * WordPress could not order them, a script optimiser could not bundle them,
 * and a Content Security Policy could not tell whose they were. Both are now
 * inline data of a registered handle.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Settings;
use WP_UnitTestCase;

/**
 * Handbook::submenu_divider_style() and Settings::enqueue_assets().
 */
final class AdminInlineAssetsTest extends WP_UnitTestCase {

	/**
	 * Start without the handles from a previous test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		foreach ( array( 'living-handbook-admin-menu' ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
		wp_dequeue_script( 'living-handbook-settings-colors' );
		wp_deregister_script( 'living-handbook-settings-colors' );
	}

	/**
	 * The divider is no longer echoed on admin_head.
	 *
	 * @return void
	 */
	public function test_the_menu_divider_is_not_echoed_into_every_admin_head(): void {
		$this->assertFalse(
			has_action( 'admin_head', array( new Handbook(), 'submenu_divider_style' ) ) !== false
			|| $this->hooked_anywhere( 'admin_head', 'submenu_divider_style' ),
			'The divider is still printed from admin_head.'
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		( new Handbook() )->submenu_divider_style();
		$this->assertSame( '', (string) ob_get_clean(), 'Nothing is printed directly.' );
	}

	/**
	 * For a user who sees the handbook menu, the rule is inline data of an
	 * enqueued style handle.
	 *
	 * @return void
	 */
	public function test_the_menu_divider_rides_on_a_style_handle(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		( new Handbook() )->submenu_divider_style();

		$this->assertTrue( wp_style_is( 'living-handbook-admin-menu', 'enqueued' ) );
		$inline = implode( '', (array) wp_styles()->get_data( 'living-handbook-admin-menu', 'after' ) );
		$this->assertStringContainsString( '#menu-posts-handbook', $inline );
	}

	/**
	 * A user without the handbook menu gets no rule for it.
	 *
	 * @return void
	 */
	public function test_a_user_without_the_menu_gets_no_divider(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		( new Handbook() )->submenu_divider_style();

		$this->assertFalse( wp_style_is( 'living-handbook-admin-menu', 'enqueued' ) );
	}

	/**
	 * The colour picker set-up is inline data of a script that depends on the
	 * picker, enqueued on the settings screen and nowhere else.
	 *
	 * @return void
	 */
	public function test_the_colour_picker_rides_on_a_script_handle(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		$settings = new Settings();
		$settings->add_page();
		$hook = (string) get_plugin_page_hookname( Settings::PAGE_SLUG, 'edit.php?post_type=' . Handbook::POST_TYPE );

		$this->assertFalse( has_action( 'admin_print_footer_scripts-' . $hook ), 'The footer script hook is gone.' );

		$settings->enqueue_assets( 'index.php' );
		$this->assertFalse( wp_script_is( 'living-handbook-settings-colors', 'enqueued' ), 'Only on the settings screen.' );

		$settings->enqueue_assets( $hook );
		$this->assertTrue( wp_script_is( 'living-handbook-settings-colors', 'enqueued' ) );
		$this->assertContains( 'wp-color-picker', wp_scripts()->registered['living-handbook-settings-colors']->deps );
		$inline = implode( '', (array) wp_scripts()->get_data( 'living-handbook-settings-colors', 'after' ) );
		$this->assertStringContainsString( 'wpColorPicker', $inline );
	}

	/**
	 * Whether a method of that name is hooked to an action, from any instance.
	 *
	 * @param string $hook   Action.
	 * @param string $method Method name.
	 * @return bool
	 */
	private function hooked_anywhere( string $hook, string $method ): bool {
		global $wp_filter;
		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return false;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $method === $callback['function'][1] ) {
					return true;
				}
			}
		}
		return false;
	}
}
