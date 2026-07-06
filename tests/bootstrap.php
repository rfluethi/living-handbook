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
}
