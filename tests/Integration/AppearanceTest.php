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
