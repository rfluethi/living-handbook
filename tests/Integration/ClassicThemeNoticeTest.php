<?php
/**
 * A classic theme is pointed out on the handbook screens.
 *
 * The handbook templates are block templates, and only a block theme uses
 * them. Up to 0.75.1 a site on a classic theme got handbook pages in the
 * theme's own layout, without navigation, table of contents or search bar,
 * and nothing in the admin said why.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Setup\Onboarding;
use WP_UnitTestCase;

/**
 * Onboarding::classic_theme_notice().
 */
final class ClassicThemeNoticeTest extends WP_UnitTestCase {

	/**
	 * The theme active before the test.
	 *
	 * @var string
	 */
	private string $theme = '';

	/**
	 * An administrator on the handbook list screen.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->theme = get_stylesheet();
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'edit-handbook' );
	}

	/**
	 * Back to the theme and screen the suite expects.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		switch_theme( $this->theme );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * The notice as printed.
	 *
	 * @return string
	 */
	private function notice(): string {
		ob_start();
		( new Onboarding() )->classic_theme_notice();
		return (string) ob_get_clean();
	}

	/**
	 * A classic theme gets the notice, a block theme does not.
	 *
	 * @return void
	 */
	public function test_a_classic_theme_is_pointed_out(): void {
		if ( ! wp_get_theme( 'twentytwentyone' )->exists() || ! wp_get_theme( 'twentytwentyfive' )->exists() ) {
			$this->markTestSkipped( 'The default themes are not installed in this test site.' );
		}

		switch_theme( 'twentytwentyone' );
		$this->assertStringContainsString( 'classic theme', $this->notice() );

		switch_theme( 'twentytwentyfive' );
		$this->assertSame( '', $this->notice() );
	}

	/**
	 * Outside the handbook screens, and for people who cannot change the theme,
	 * nothing is shown.
	 *
	 * @return void
	 */
	public function test_only_on_the_handbook_screens_and_for_theme_admins(): void {
		if ( ! wp_get_theme( 'twentytwentyone' )->exists() ) {
			$this->markTestSkipped( 'The default themes are not installed in this test site.' );
		}
		switch_theme( 'twentytwentyone' );

		set_current_screen( 'dashboard' );
		$this->assertSame( '', $this->notice() );

		set_current_screen( 'edit-handbook' );
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( '', $this->notice() );
	}
}
