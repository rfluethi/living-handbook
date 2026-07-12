<?php
/**
 * PHPUnit bootstrap.
 *
 * Unit tests run without WordPress. Integration tests load the wp-phpunit
 * test suite and the plugin, so activation and registration can be asserted.
 *
 * Loading the WordPress test suite is opt-in via the LH_INTEGRATION
 * environment variable. This matters because wp-phpunit sets WP_PHPUNIT__DIR
 * through its own autoloader, so that variable alone cannot tell unit runs
 * from integration runs.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( getenv( 'LH_INTEGRATION' ) ) {
	$living_handbook_wp_phpunit = getenv( 'WP_PHPUNIT__DIR' );

	if ( false === $living_handbook_wp_phpunit || '' === $living_handbook_wp_phpunit ) {
		echo 'LH_INTEGRATION is set but WP_PHPUNIT__DIR is not available.' . PHP_EOL;
		exit( 1 );
	}

	require_once $living_handbook_wp_phpunit . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function (): void {
			require dirname( __DIR__ ) . '/living-handbook.php';
		}
	);

	require $living_handbook_wp_phpunit . '/includes/bootstrap.php';
} else {
	// Unit tests run without WordPress. Define ABSPATH so the guarded source
	// files can be loaded, and autoload the plugin classes from src/ (PSR-4).
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}

	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = 'LivingHandbook\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$relative = substr( $class_name, strlen( $prefix ) );
			$path     = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}
