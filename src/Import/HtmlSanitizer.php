<?php
/**
 * The single HTML allowlist for content that comes from outside WordPress.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters HTML that entered the plugin from a Markdown source (a pasted draft, a
 * ZIP, or a GitHub pull) before it is stored. The Markdown converter lets raw
 * HTML pass through, so a hostile source could carry a script; this is the one
 * place that decides what markup is allowed to survive. Both the import and the
 * GitHub sync run their HTML through here, so the rule cannot drift apart.
 *
 * The allowlist is the standard post allowlist plus the markup the plugin's own
 * features rely on: the Mermaid code fence (pre and code keep their class so the
 * diagram is still recognised), the details/summary disclosure markup, and the
 * disabled checkbox of a GitHub task list.
 */
final class HtmlSanitizer {

	/**
	 * The allowed-HTML map.
	 *
	 * @return array<string, mixed>
	 */
	public static function allowed(): array {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed['details'] = array(
			'open'  => true,
			'class' => true,
			'id'    => true,
		);
		$allowed['summary'] = array(
			'class' => true,
			'id'    => true,
		);
		if ( ! isset( $allowed['pre'] ) || ! is_array( $allowed['pre'] ) ) {
			$allowed['pre'] = array();
		}
		$allowed['pre']['class'] = true;
		if ( ! isset( $allowed['code'] ) || ! is_array( $allowed['code'] ) ) {
			$allowed['code'] = array();
		}
		$allowed['code']['class'] = true;

		// Task-list checkboxes from GitHub Flavored Markdown ("- [ ]"). Only the
		// read-only checkbox is allowed: type is pinned by kses to a safe value,
		// and the box is always disabled, so this adds a display element, not an
		// input the visitor can interact with.
		$allowed['input'] = array(
			'type'     => true,
			'checked'  => true,
			'disabled' => true,
			'class'    => true,
		);

		return $allowed;
	}

	/**
	 * Strip anything not on the allowlist from a block of HTML.
	 *
	 * @param string $html HTML from an external source.
	 * @return string
	 */
	public static function clean( string $html ): string {
		return wp_kses( $html, self::allowed() );
	}
}
