<?php
/**
 * PHPUnit bootstrap.
 *
 * Unit tests run without WordPress. Integration tests load the wp-phpunit
 * test suite when the WP_PHPUNIT__DIR environment variable is set, and load
 * the plugin so its activation and registration can be asserted.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$living_handbook_wp_phpunit = getenv( 'WP_PHPUNIT__DIR' );

if ( false !== $living_handbook_wp_phpunit && '' !== $living_handbook_wp_phpunit ) {
	require_once $living_handbook_wp_phpunit . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function (): void {
			require dirname( __DIR__ ) . '/living-handbook.php';
		}
	);

	require $living_handbook_wp_phpunit . '/includes/bootstrap.php';
}
