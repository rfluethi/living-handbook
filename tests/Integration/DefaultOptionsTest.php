<?php
/**
 * The switches a site never saved are stored with the value they already mean.
 *
 * Learning paths on or off, the classifying taxonomies in use, and guest
 * voting are read on every request. Without a row and without a persistent
 * object cache each of them cost a database query per page view. Activation
 * and the upgrade now store them, autoloaded, without changing what they mean.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Plugin;
use LivingHandbook\Setup\Settings;
use LivingHandbook\Taxonomy\Taxonomies;
use LivingHandbook\Training\Training;
use WP_UnitTestCase;

/**
 * Plugin::store_default_options() through activation.
 */
final class DefaultOptionsTest extends WP_UnitTestCase {

	/**
	 * The three options.
	 *
	 * @return array<int, string>
	 */
	private function names(): array {
		return array( Taxonomies::OPTION_ENABLED, Training::OPTION_ENABLED, Settings::OPTION_PUBLIC_FEEDBACK );
	}

	/**
	 * After activation all three are autoloaded rows, and they mean what no row
	 * meant: all four taxonomies, no learning paths, no guest votes.
	 *
	 * @return void
	 */
	public function test_the_switches_are_stored_and_autoloaded(): void {
		foreach ( $this->names() as $name ) {
			delete_option( $name );
		}

		Plugin::activate();
		wp_cache_delete( 'alloptions', 'options' );
		$autoloaded = wp_load_alloptions();

		foreach ( $this->names() as $name ) {
			$this->assertArrayHasKey( $name, $autoloaded, $name . ' is loaded with the other options.' );
		}
		$this->assertCount( 4, Taxonomies::enabled() );
		$this->assertFalse( Training::is_enabled() );
		$this->assertFalse( Settings::public_feedback_enabled() );
	}

	/**
	 * A choice the site saved is kept.
	 *
	 * @return void
	 */
	public function test_a_saved_choice_is_kept(): void {
		update_option( Training::OPTION_ENABLED, '1' );

		Plugin::activate();

		$this->assertSame( '1', get_option( Training::OPTION_ENABLED ) );
		delete_option( Training::OPTION_ENABLED );
	}
}
