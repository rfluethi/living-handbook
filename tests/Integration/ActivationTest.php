<?php
/**
 * Activation smoke test.
 *
 * This is the centrepiece of the test strategy: it proves the plugin loads
 * and activates without error. It grows as modules are added (content type,
 * taxonomies, capabilities, menu generation).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Plugin;
use WP_UnitTestCase;

/**
 * Integration activation test.
 */
final class ActivationTest extends WP_UnitTestCase {

	/**
	 * WordPress is loaded and the plugin bootstrap ran.
	 *
	 * @return void
	 */
	public function test_plugin_is_loaded(): void {
		$this->assertTrue( defined( 'LIVING_HANDBOOK_VERSION' ) );
		$this->assertNotFalse( has_action( 'plugins_loaded' ) );
	}

	/**
	 * Activation runs without throwing.
	 *
	 * @return void
	 */
	public function test_activation_does_not_error(): void {
		Plugin::activate();
		$this->assertTrue( true );
	}
}
