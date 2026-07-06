<?php
/**
 * Data model integration test.
 *
 * Asserts that the content type and taxonomies are registered once WordPress
 * and the plugin are loaded.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use WP_UnitTestCase;

/**
 * Registration of the data model.
 */
final class DataModelTest extends WP_UnitTestCase {

	/**
	 * The handbook content type is registered.
	 *
	 * @return void
	 */
	public function test_post_type_registered(): void {
		$this->assertTrue( post_type_exists( 'handbook' ) );
	}

	/**
	 * All taxonomies and the handbook grouping are registered.
	 *
	 * @return void
	 */
	public function test_taxonomies_registered(): void {
		$this->assertTrue( taxonomy_exists( 'handbook_type' ) );
		$this->assertTrue( taxonomy_exists( 'handbook_topic' ) );
		$this->assertTrue( taxonomy_exists( 'handbook_role' ) );
		$this->assertTrue( taxonomy_exists( 'handbook_audience' ) );
		$this->assertTrue( taxonomy_exists( 'handbook_set' ) );
	}
}
