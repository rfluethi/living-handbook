<?php
/**
 * Where a bundled library's classes actually live.
 *
 * The plugin ships three Composer libraries in vendor/. Shipped as they come,
 * they occupy the global names League\CommonMark, Symfony\Component\Yaml and
 * enshrined\svgSanitize, and WordPress loads every plugin into one PHP process:
 * whichever plugin loads first wins the name, and the other one gets a class
 * that looks right and behaves differently. That is not theoretical, CommonMark
 * 1.x and 2.x differ in exactly the method the import calls.
 *
 * The release build therefore runs PHP-Scoper over vendor/ and moves those
 * libraries into LivingHandbook\Vendor\, where nothing else can reach them.
 * A development checkout has no prefix, because it installs vendor/ with plain
 * Composer. The plugin has to work in both, so it never writes a library class
 * name into a `new`: it asks here, and gets whichever of the two is present.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the name of a bundled library class in this installation.
 */
final class Vendored {

	/**
	 * Namespace the release build moves the bundled libraries into.
	 *
	 * Note for the plugin's own autoloader: it is registered for LivingHandbook\
	 * and will be asked for these names first. It looks for a file under src/,
	 * does not find one and returns, after which Composer's autoloader answers.
	 */
	public const PREFIX = 'LivingHandbook\\Vendor\\';

	/**
	 * The name a bundled class or interface goes by here.
	 *
	 * Prefers the prefixed name, so a scoped build never falls back to a copy
	 * another plugin loaded under the plain name. Returns the plain name when
	 * there is no prefixed one, which is the development checkout, and also when
	 * neither exists: the caller checks with exists() before using it.
	 *
	 * @param string $symbol Fully qualified class or interface name as written
	 *                       by the library itself, without a leading backslash.
	 * @return string
	 */
	public static function name( string $symbol ): string {
		$symbol   = ltrim( $symbol, '\\' );
		$prefixed = self::PREFIX . $symbol;

		if ( class_exists( $prefixed ) || interface_exists( $prefixed ) ) {
			return $prefixed;
		}

		return $symbol;
	}

	/**
	 * Whether a bundled class or interface is available at all.
	 *
	 * @param string $symbol Fully qualified class or interface name.
	 * @return bool
	 */
	public static function exists( string $symbol ): bool {
		$name = self::name( $symbol );
		return class_exists( $name ) || interface_exists( $name );
	}

	/**
	 * Load the bundled autoloader, if it is there.
	 *
	 * The libraries are only needed for the import and the sync, so they are
	 * loaded on demand rather than on every request.
	 *
	 * @return void
	 */
	public static function load(): void {
		$autoload = LIVING_HANDBOOK_DIR . 'vendor/autoload.php';
		if ( is_readable( $autoload ) ) {
			require_once $autoload;
		}
	}
}
