<?php
/**
 * Find the images a Markdown draft references, so the import can bring them along.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts the image references from a Markdown draft: both Markdown image
 * syntax and raw HTML <img> tags. Only relative references are returned, the
 * ones that point at a file next to the page in the same source (a repository
 * folder or a bundled folder); an absolute http(s) or data URL is left as it is,
 * because it is already reachable and is not ours to copy.
 */
final class ImageRefs {

	/**
	 * The distinct relative image references in a Markdown draft, in the order
	 * they first appear.
	 *
	 * @param string $markdown Markdown source.
	 * @return array<int, string> Relative references, e.g. "../assets/x.svg".
	 */
	public static function extract( string $markdown ): array {
		$refs = array();

		// Markdown images: ![alt](url "title"). The URL may be wrapped in <>.
		if ( preg_match_all( '/!\[[^\]]*\]\(\s*<?([^)\s>]+)>?[^)]*\)/', $markdown, $matches ) ) {
			foreach ( $matches[1] as $ref ) {
				self::add( $refs, (string) $ref );
			}
		}

		// Raw HTML images that passed through as-is.
		if ( preg_match_all( '/<img\b[^>]*\bsrc\s*=\s*("|\')(.*?)\1/i', $markdown, $html ) ) {
			foreach ( $html[2] as $ref ) {
				self::add( $refs, (string) $ref );
			}
		}

		return array_values( $refs );
	}

	/**
	 * Add a reference to the set if it is relative and not already present.
	 *
	 * @param array<string, string> $refs Reference set, keyed by itself.
	 * @param string                $ref  Raw reference from the source.
	 * @return void
	 */
	private static function add( array &$refs, string $ref ): void {
		$ref = trim( $ref );
		if ( '' === $ref || self::is_absolute( $ref ) ) {
			return;
		}
		$refs[ $ref ] = $ref;
	}

	/**
	 * Whether a reference is absolute, so it is left untouched.
	 *
	 * @param string $ref Reference.
	 * @return bool
	 */
	private static function is_absolute( string $ref ): bool {
		return 1 === preg_match( '#^(?:[a-z][a-z0-9+.-]*:|//)#i', $ref );
	}
}
