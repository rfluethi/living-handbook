<?php
/**
 * The colours and the base size a site can set without writing CSS.
 *
 * The plugin's colours already follow the active theme through its theme.json
 * presets, and that stays the rule: an empty field means "the theme decides".
 * This is the escape hatch for the case the theme gets it wrong, which is a
 * real case: a theme whose presets do not match what it actually paints, or one
 * whose contrast is too low to read. Rather than send people to the Custom CSS
 * field with a list of variable names, the ten values that matter are fields.
 *
 * Not every colour of the plugin is one: the page-type badge takes the accent
 * on purpose, so the three badge kinds stay told apart, and the three freshness
 * chips take their backgrounds from their own colours. A field for every value
 * would be a list of variable names again, only slower.
 *
 * How it fits together: the stylesheet declares its defaults as
 * var(--lh-user-x, <theme preset>, <fallback>), and what is set here is printed
 * as --lh-user-x on :root. That way a set field beats the default without a
 * specificity fight, and CSS written by hand still beats both, because it names
 * --lh-x directly and is printed after this. Nothing is printed for a field
 * that is empty.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, validates and renders the appearance settings.
 */
final class Appearance {

	/**
	 * Option holding the colour overrides, as key => hex colour. A key that is
	 * absent or empty means the theme decides.
	 */
	public const OPTION_COLORS = 'living_handbook_colors';

	/**
	 * Option holding the base size in percent. 100 is the plugin default, one rem.
	 */
	public const OPTION_BASE_SIZE = 'living_handbook_base_size';

	/**
	 * Smallest and largest base size in percent. Below 75 the badges and the
	 * metadata footer stop being readable, above 150 the navigation no longer
	 * fits its column.
	 */
	public const SIZE_MIN = 75;
	public const SIZE_MAX = 150;

	/**
	 * The colour fields, in the order they are shown.
	 *
	 * Each key maps to the custom property --lh-user-<key with dashes>, which the
	 * stylesheet reads as the first choice for the matching --lh- variable.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function fields(): array {
		return array(
			'surface'             => array(
				'label'       => __( 'Surface', 'living-handbook' ),
				'description' => __( 'Background of cards, navigation, table of contents, filter bar and search field.', 'living-handbook' ),
			),
			'surface-text'        => array(
				'label'       => __( 'Text on the surface', 'living-handbook' ),
				'description' => __( 'The text on those surfaces. Lines and secondary text are mixed from this colour, so they follow it.', 'living-handbook' ),
			),
			'accent'              => array(
				'label'       => __( 'Accent', 'living-handbook' ),
				'description' => __( 'Links, the current page in the navigation, filled controls, and the page-type badge. The text on a filled control is chosen automatically, black or white, whichever reads better.', 'living-handbook' ),
			),
			'badge-bg'            => array(
				'label'       => __( 'Topic badge background', 'living-handbook' ),
				'description' => __( 'A page carries up to three of these small labels, told apart by colour on purpose: the page type takes the accent above, the topic this pair, the audience the pair below.', 'living-handbook' ),
			),
			'badge-text'          => array(
				'label'       => __( 'Topic badge text', 'living-handbook' ),
				'description' => __( 'The text of the topic label.', 'living-handbook' ),
			),
			'badge-audience-bg'   => array(
				'label'       => __( 'Audience badge background', 'living-handbook' ),
				'description' => __( 'The label that reads "Audience: …".', 'living-handbook' ),
			),
			'badge-audience-text' => array(
				'label'       => __( 'Audience badge text', 'living-handbook' ),
				'description' => __( 'The text of the audience label.', 'living-handbook' ),
			),
			'ok'                  => array(
				'label'       => __( 'Reviewed', 'living-handbook' ),
				'description' => __( 'The first of the three review states.', 'living-handbook' ),
			),
			'due'                 => array(
				'label'       => __( 'Review due', 'living-handbook' ),
				'description' => __( 'The second of the three review states.', 'living-handbook' ),
			),
			'overdue'             => array(
				'label'       => __( 'Review overdue', 'living-handbook' ),
				'description' => __( 'The third of the three review states. Keep the three clearly different from each other, and not by hue alone.', 'living-handbook' ),
			),
		);
	}

	/**
	 * The stored colours, validated, with empty entries dropped.
	 *
	 * @return array<string, string>
	 */
	public static function colors(): array {
		$stored = get_option( self::OPTION_COLORS, array() );

		return is_array( $stored ) ? self::sanitize_colors( $stored ) : array();
	}

	/**
	 * The stored base size in percent, inside its bounds.
	 *
	 * @return int
	 */
	public static function base_size(): int {
		return self::sanitize_size( get_option( self::OPTION_BASE_SIZE, 100 ) );
	}

	/**
	 * Keep only known keys holding a valid hex colour.
	 *
	 * WordPress's sanitize_hex_color() returns null for anything else, so a
	 * pasted colour function, a variable name or an attempt to close the style
	 * block is dropped rather than repaired: a colour the plugin cannot read is
	 * not a colour, and printing it would put unchecked text into a style
	 * element.
	 *
	 * @param mixed $value Submitted value.
	 * @return array<string, string>
	 */
	public static function sanitize_colors( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			$raw = isset( $value[ $key ] ) && is_string( $value[ $key ] ) ? trim( $value[ $key ] ) : '';
			if ( '' === $raw ) {
				continue;
			}
			$hex = sanitize_hex_color( $raw );
			if ( is_string( $hex ) && '' !== $hex ) {
				$clean[ $key ] = $hex;
			}
		}

		return $clean;
	}

	/**
	 * Clamp the base size to its bounds, falling back to 100 for anything that is
	 * not a number.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public static function sanitize_size( $value ): int {
		if ( ! is_numeric( $value ) ) {
			return 100;
		}

		return (int) max( self::SIZE_MIN, min( self::SIZE_MAX, (int) round( (float) $value ) ) );
	}

	/**
	 * The CSS for the current settings, or an empty string when nothing is set.
	 *
	 * @return string
	 */
	public static function css(): string {
		$declarations = array();

		foreach ( self::colors() as $key => $hex ) {
			$declarations[] = '--lh-user-' . $key . ':' . $hex;

			if ( 'accent' === $key ) {
				$declarations[] = '--lh-user-on-accent:' . self::readable_on( $hex );
			}
		}

		// --lh-base is printed under its own name, not as --lh-user-base: the
		// stylesheet never declares it, every font size reads it as
		// var(--lh-base, 1rem). That way it also reaches what is rendered outside
		// a handbook container, the enlarged-image overlay for one, which is
		// appended to the document body.
		$size = self::base_size();
		if ( 100 !== $size ) {
			$declarations[] = '--lh-base:' . round( $size / 100, 4 ) . 'rem';
		}

		if ( ! $declarations ) {
			return '';
		}

		return ':root{' . implode( ';', $declarations ) . '}';
	}

	/**
	 * Black or white, whichever reads better on the given colour.
	 *
	 * The threshold is not a guess: it is the point where the contrast ratio
	 * against white and against black are equal, so the choice is always the
	 * higher of the two.
	 *
	 * @param string $hex A validated hex colour, three or six digits.
	 * @return string
	 */
	public static function readable_on( string $hex ): string {
		$luminance = self::luminance( $hex );

		$on_white = 1.05 / ( $luminance + 0.05 );
		$on_black = ( $luminance + 0.05 ) / 0.05;

		return $on_black >= $on_white ? '#111111' : '#ffffff';
	}

	/**
	 * Relative luminance of a hex colour, per WCAG 2.
	 *
	 * @param string $hex A validated hex colour, three or six digits.
	 * @return float
	 */
	private static function luminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return 0.0;
		}

		$channels = array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);

		foreach ( $channels as $i => $channel ) {
			$channels[ $i ] = $channel <= 0.03928
				? $channel / 12.92
				: pow( ( $channel + 0.055 ) / 1.055, 2.4 );
		}

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * The colours the active theme declares in its theme.json, as value => name.
	 *
	 * Offered next to each field as one-click presets. This is the theme's own
	 * palette, the same list the block editor shows, so a site picks a colour the
	 * theme already uses instead of inventing one beside it. A theme without a
	 * palette simply gets no presets.
	 *
	 * @return array<string, string>
	 */
	public static function theme_palette(): array {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return array();
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$entries = array();

		foreach ( array( 'theme', 'custom' ) as $origin ) {
			if ( empty( $palette[ $origin ] ) || ! is_array( $palette[ $origin ] ) ) {
				continue;
			}
			foreach ( $palette[ $origin ] as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['color'] ) || ! is_string( $entry['color'] ) ) {
					continue;
				}
				$hex = sanitize_hex_color( trim( $entry['color'] ) );
				if ( ! is_string( $hex ) || '' === $hex ) {
					continue;
				}
				$name = isset( $entry['name'] ) && is_string( $entry['name'] ) ? $entry['name'] : $hex;

				$entries[ $hex ] = $name;
			}
		}

		return $entries;
	}
}
