<?php
/**
 * Activation test.
 *
 * Proves that the plugin loads, that activation runs, and that it leaves behind
 * what getting-started promises: the seeded terms, the overview page and a
 * stored database version. It used to assert true after calling activate(),
 * which only caught a fatal error.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Plugin;
use LivingHandbook\Setup\Onboarding;
use LivingHandbook\Taxonomy\Taxonomies;
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
	 * Activation stores the plugin version, so the upgrade routing has something
	 * to compare against.
	 *
	 * @return void
	 */
	public function test_activation_stores_the_database_version(): void {
		delete_option( Plugin::DB_VERSION_OPTION );

		Plugin::activate();

		$this->assertSame( LIVING_HANDBOOK_VERSION, get_option( Plugin::DB_VERSION_OPTION ) );
	}

	/**
	 * Activation seeds the taxonomies, so a fresh site can classify pages right
	 * away instead of starting with empty taxonomies.
	 *
	 * Topics are deliberately not seeded: they are the one taxonomy that is
	 * specific to each team's handbook, so a generic default would be in the way.
	 *
	 * @return void
	 */
	public function test_activation_seeds_the_taxonomies(): void {
		Plugin::activate();

		foreach ( array( Taxonomies::PAGE_TYPE, Taxonomies::ROLE, Taxonomies::AUDIENCE, Handbooks::TAXONOMY ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			$this->assertIsArray( $terms );
			$this->assertNotEmpty( $terms, $taxonomy . ' should be seeded on activation.' );
		}
	}

	/**
	 * Activation creates the overview page and remembers it, so the setup notice
	 * can link to it and the uninstall can clean it up.
	 *
	 * @return void
	 */
	public function test_activation_creates_the_overview_page(): void {
		delete_option( Onboarding::OPTION_OVERVIEW_PAGE );

		Plugin::activate();

		$page_id = (int) get_option( Onboarding::OPTION_OVERVIEW_PAGE, 0 );
		$this->assertGreaterThan( 0, $page_id, 'Activation should create the overview page.' );
		$this->assertSame( 'page', get_post_type( $page_id ) );
		$this->assertStringContainsString(
			'living-handbook/overview',
			(string) get_post_field( 'post_content', $page_id ),
			'The overview page should hold the overview block.'
		);
	}
}
