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
 * diagram is still recognised) and the details/summary disclosure markup. GitHub
 * task-list checkboxes are converted to characters before sanitizing, so no
 * <input> is on the list.
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

		// No <input> is allowed. GitHub task-list checkboxes ("- [ ]") are turned
		// into ballot characters by MarkdownConverter before this runs, so no
		// input needs to survive. kses could not secure one anyway: it only checks
		// that an attribute is allowed, it does not pin the type value or force
		// disabled, so an allowed <input> could carry type="text" or "submit".

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
