<?php
/**
 * The colours and the text size a site can set without writing CSS.
 *
 * Two things are pinned here. The first is that an empty setting changes
 * nothing: the whole design of the plugin is that the colours follow the theme,
 * and a settings page that quietly puts its own values on top of that would
 * undo it. The second is that whatever ends up in the stylesheet is a colour
 * and nothing else, because it is printed into a style element.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Appearance;
use LivingHandbook\Setup\Settings;
use WP_UnitTestCase;

/**
 * Appearance settings.
 */
final class AppearanceTest extends WP_UnitTestCase {

	/**
	 * Start every test from the shipped state: nothing set.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( Appearance::OPTION_COLORS );
		delete_option( Appearance::OPTION_BASE_SIZE );
	}

	/**
	 * Nothing set, nothing printed. Without this the plugin would stop following
	 * the theme the moment the settings page exists.
	 *
	 * @return void
	 */
	public function test_an_untouched_installation_prints_no_css(): void {
		$this->assertSame( '', Appearance::css() );
	}

	/**
	 * An empty field is not a colour. Saving the form with the fields blank must
	 * leave the same nothing behind, not an empty declaration.
	 *
	 * @return void
	 */
	public function test_empty_fields_are_dropped_rather_than_stored(): void {
		update_option(
			Appearance::OPTION_COLORS,
			Appearance::sanitize_colors(
				array(
					'surface' => '',
					'accent'  => '   ',
				)
			)
		);

		$this->assertSame( array(), Appearance::colors() );
		$this->assertSame( '', Appearance::css() );
	}

	/**
	 * A set colour is printed once, under its user-level name, so the stylesheet
	 * picks it up as the first choice ahead of the theme preset.
	 *
	 * @return void
	 */
	public function test_a_set_colour_reaches_the_stylesheet(): void {
		update_option( Appearance::OPTION_COLORS, Appearance::sanitize_colors( array( 'surface' => '#1E1B1A' ) ) );

		$css = Appearance::css();

		$this->assertStringStartsWith( ':root{', $css );
		$this->assertStringContainsString( '--lh-user-surface:#1E1B1A', $css );
		$this->assertStringNotContainsString( '--lh-user-accent', $css, 'A field nobody filled in has no business in the output.' );
	}

	/**
	 * Anything that is not a hex colour is dropped, not repaired. The value is
	 * printed into a style element, so a half-understood value is worse than
	 * none.
	 *
	 * @param mixed $value The submitted value.
	 * @return void
	 *
	 * @dataProvider provide_values_that_are_not_colours
	 */
	public function test_values_that_are_not_colours_are_dropped( $value ): void {
		$clean = Appearance::sanitize_colors( array( 'accent' => $value ) );

		$this->assertSame( array(), $clean, 'Kept: ' . wp_json_encode( $value ) );
	}

	/**
	 * Values a colour field must refuse.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_values_that_are_not_colours(): array {
		return array(
			'a colour function'      => array( 'rgb(30, 27, 26)' ),
			'a variable'             => array( 'var(--wp--preset--color--base)' ),
			'a keyword'              => array( 'red' ),
			'a closing style tag'    => array( '#fff}</style><script>alert(1)</script>' ),
			'a declaration after it' => array( '#fff;position:fixed' ),
			'too few digits'         => array( '#ff' ),
			'not a string'           => array( array( '#fff' ) ),
		);
	}

	/**
	 * An unknown key is not carried through, so the option cannot grow custom
	 * property names that nobody declared.
	 *
	 * @return void
	 */
	public function test_unknown_keys_are_dropped(): void {
		$clean = Appearance::sanitize_colors(
			array(
				'accent'                 => '#123456',
				'surface;position:fixed' => '#123456',
			)
		);

		$this->assertSame( array( 'accent' => '#123456' ), $clean );
	}

	/**
	 * The text on an accent-filled control is chosen, not configured: black on a
	 * light accent, white on a dark one, always the higher of the two contrasts.
	 *
	 * @return void
	 */
	public function test_the_text_on_the_accent_follows_the_accent(): void {
		$this->assertSame( '#111111', Appearance::readable_on( '#ffdd00' ) );
		$this->assertSame( '#ffffff', Appearance::readable_on( '#1e1b1a' ) );
		$this->assertSame( '#111111', Appearance::readable_on( '#fff' ), 'Three-digit colours count too.' );

		update_option( Appearance::OPTION_COLORS, Appearance::sanitize_colors( array( 'accent' => '#ffdd00' ) ) );
		$this->assertStringContainsString( '--lh-user-on-accent:#111111', Appearance::css() );
	}

	/**
	 * The text size stays inside its bounds, and 100 percent prints nothing,
	 * because 100 percent is what the stylesheet already does.
	 *
	 * @return void
	 */
	public function test_the_text_size_is_bounded_and_silent_at_one_hundred(): void {
		$this->assertSame( 100, Appearance::sanitize_size( 100 ) );
		$this->assertSame( 100, Appearance::sanitize_size( 'gross' ) );
		$this->assertSame( Appearance::SIZE_MAX, Appearance::sanitize_size( 4000 ) );
		$this->assertSame( Appearance::SIZE_MIN, Appearance::sanitize_size( -10 ) );

		update_option( Appearance::OPTION_BASE_SIZE, 100 );
		$this->assertSame( '', Appearance::css() );

		update_option( Appearance::OPTION_BASE_SIZE, Appearance::sanitize_size( 125 ) );
		$this->assertStringContainsString( '--lh-base:1.25rem', Appearance::css() );
	}

	/**
	 * The stylesheet really reads every value this class can print. A renamed
	 * variable on either side would leave a settings field that does nothing,
	 * and nothing would say so.
	 *
	 * @return void
	 */
	public function test_every_field_has_a_counterpart_in_the_stylesheet(): void {
		$css = (string) file_get_contents( LIVING_HANDBOOK_DIR . 'assets/frontend.css' );

		foreach ( array_keys( Appearance::fields() ) as $key ) {
			$this->assertStringContainsString( 'var(--lh-user-' . $key . ',', $css, $key );
		}

		$this->assertStringContainsString( 'var(--lh-user-on-accent,', $css );
		$this->assertStringContainsString( 'calc(var(--lh-base, 1rem)', $css );
	}

	/**
	 * Every tab has a settings group of its own.
	 *
	 * This is the one that would cost data. options.php walks the group of the
	 * submitted form and calls update_option() for every option in it, with null
	 * for the ones the form did not send. With one group across five tabs,
	 * saving the Appearance tab would empty the sync schedule, the feedback
	 * switch, the no-access page and the uninstall choice, and nothing would say
	 * so.
	 *
	 * @return void
	 */
	public function test_each_tab_keeps_its_options_to_itself(): void {
		global $new_allowed_options;

		// Called directly rather than through admin_init: the point is what this
		// class registers, and the rest of admin_init sends headers.
		( new Settings() )->register_settings();

		$this->assertNotEmpty( $new_allowed_options, 'No settings group is registered at all.' );

		$seen = array();
		foreach ( array_keys( Settings::tabs() ) as $tab ) {
			$group = Settings::group( $tab );
			$this->assertArrayHasKey( $group, $new_allowed_options, $tab );

			foreach ( $new_allowed_options[ $group ] as $option ) {
				$this->assertArrayNotHasKey( $option, $seen, $option . ' is in two tabs.' );
				$seen[ $option ] = $tab;
			}
		}

		// And every option the plugin registers really sits in one of them, so a
		// new setting cannot quietly land in a group no form ever submits.
		foreach ( array( Appearance::OPTION_COLORS, Appearance::OPTION_BASE_SIZE, Settings::OPTION_CUSTOM_CSS, Settings::OPTION_PUBLIC_FEEDBACK, Settings::OPTION_DENIED_PAGE ) as $option ) {
			$this->assertArrayHasKey( $option, $seen, $option . ' belongs to no tab.' );
		}

		$this->assertSame( 'appearance', $seen[ Appearance::OPTION_COLORS ] );
		$this->assertSame( 'access', $seen[ Settings::OPTION_DENIED_PAGE ] );
	}

	/**
	 * An unknown tab falls back to the first one rather than rendering an empty
	 * form whose save would write nothing.
	 *
	 * @return void
	 */
	public function test_an_unknown_tab_falls_back_to_the_first(): void {
		$_GET['tab'] = 'gibt-es-nicht';
		$this->assertSame( 'sync', Settings::current_tab() );

		$_GET['tab'] = 'access';
		$this->assertSame( 'access', Settings::current_tab() );

		unset( $_GET['tab'] );
		$this->assertSame( 'sync', Settings::current_tab() );
	}

	/**
	 * The page-type badge is not a field, it takes the accent. Someone will
	 * eventually wonder why setting the topic badge colours only one of the
	 * three chips; this is the answer, in a form that fails if it changes.
	 *
	 * @return void
	 */
	public function test_the_three_badge_kinds_take_three_different_colours(): void {
		$css = (string) file_get_contents( LIVING_HANDBOOK_DIR . 'assets/frontend.css' );

		$this->assertMatchesRegularExpression( '/\.living-handbook-badge--type\s*\{[^}]*--lh-accent-soft/', $css );
		$this->assertMatchesRegularExpression( '/\.living-handbook-badge--audience\s*\{[^}]*--lh-badge-audience-bg/', $css );
		$this->assertMatchesRegularExpression( '/\.living-handbook-badge\s*\{[^}]*--lh-badge-bg/', $css );

		$fields = array_keys( Appearance::fields() );
		$this->assertContains( 'badge-bg', $fields );
		$this->assertContains( 'badge-audience-bg', $fields );
		$this->assertNotContains( 'badge-type-bg', $fields, 'The page type follows the accent on purpose.' );
	}

	/**
	 * Every font size in the stylesheet goes through the base size. One that does
	 * not would stay put while the rest moves, which is exactly the kind of
	 * mismatch nobody notices until a page looks wrong.
	 *
	 * @return void
	 */
	public function test_no_font_size_is_left_behind(): void {
		$css     = (string) file_get_contents( LIVING_HANDBOOK_DIR . 'assets/frontend.css' );
		$matches = array();
		preg_match_all( '/font-size:\s*([^;]+);/', $css, $matches );

		$stragglers = array_values(
			array_filter(
				$matches[1],
				static fn( string $value ): bool => false === strpos( $value, '--lh-base' ) && false !== strpos( $value, 'rem' )
			)
		);

		$this->assertSame( array(), $stragglers, 'Font sizes in rem that ignore the base size.' );
	}
}
