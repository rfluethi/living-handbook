<?php
/**
 * Which handbook the import loads, where it lands, and whether it stays tied.
 *
 * The import used to decide for you: it read the admin language and loaded the
 * user handbook in it. That is right often enough to be annoying when it is
 * wrong, a German admin could not ask for the English pages, and the technical
 * documentation could not be loaded at all because it did not ship. These tests
 * hold the three things that replaced it: a catalogue of what is actually in
 * this build, a choice that is honoured, and a way to load pages that are not
 * tied to the shipped copy.
 *
 * The last one is the one with a price attached: untied pages can be edited and
 * are never refreshed again. Both halves are asserted, so neither can quietly
 * turn into the other.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\AppHandbook;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Import\AppHandbook.
 */
final class AppHandbookChoiceTest extends WP_UnitTestCase {

	/**
	 * The user handbook ships in both languages, so both are on offer.
	 *
	 * @return void
	 */
	public function test_the_catalogue_lists_what_is_in_this_build(): void {
		$entries = AppHandbook::entries();

		$this->assertArrayHasKey( 'user-de', $entries );
		$this->assertArrayHasKey( 'user-en', $entries );

		foreach ( $entries as $key => $entry ) {
			$this->assertNotSame( '', $entry['label'], $key . ' needs a label' );
			$this->assertDirectoryExists( $entry['dir'], $key . ' is offered, so its folder must be there' );
		}
	}

	/**
	 * A folder that is not in this build is left out rather than offered and then
	 * failing. Asserted through the loader, which is where it would hurt.
	 *
	 * @return void
	 */
	public function test_an_entry_that_is_not_bundled_is_refused(): void {
		$result = AppHandbook::load( 0, 'not-a-handbook' );

		$this->assertWPError( $result );
		$this->assertSame( 'living_handbook_import', $result->get_error_code() );
	}

	/**
	 * The default follows the admin language, and stays inside what is bundled.
	 *
	 * @return void
	 */
	public function test_the_default_is_the_user_handbook_in_the_admin_language(): void {
		$default = AppHandbook::default_key();

		$this->assertArrayHasKey( $default, AppHandbook::entries() );
		$this->assertStringStartsWith( 'user-', $default );
	}

	/**
	 * The choice decides which folder is read: the two user handbooks are
	 * different files, so the pages differ.
	 *
	 * @return void
	 */
	public function test_the_choice_decides_which_folder_is_read(): void {
		$this->assertNotSame( AppHandbook::local_dir( 'user-de' ), AppHandbook::local_dir( 'user-en' ) );
		$this->assertSame( AppHandbook::local_dir( AppHandbook::default_key() ), AppHandbook::local_dir() );
	}

	/**
	 * Loaded tied, a page is locked in the editor, which is what makes a later
	 * load able to refresh it.
	 *
	 * @return void
	 */
	public function test_pages_are_tied_to_the_shipped_copy_by_default(): void {
		$ids = $this->load_into_a_handbook( 'user-en', true );

		$this->assertNotEmpty( $ids );
		foreach ( $ids as $id ) {
			$this->assertSame( '1', (string) get_post_meta( $id, '_lh_app_handbook', true ) );
		}
	}

	/**
	 * Loaded untied, the same pages carry no source marker, so they are ordinary
	 * handbook pages: editable, and never refreshed again.
	 *
	 * @return void
	 */
	public function test_pages_can_be_loaded_without_the_connection(): void {
		$ids = $this->load_into_a_handbook( 'user-en', false );

		$this->assertNotEmpty( $ids );
		foreach ( $ids as $id ) {
			$this->assertSame( '', (string) get_post_meta( $id, '_lh_app_handbook', true ) );
		}
	}

	/**
	 * Load one entry into a fresh handbook and return the page ids.
	 *
	 * @param string $key     Entry key.
	 * @param bool   $managed Whether the pages stay tied to the shipped copy.
	 * @return array<int, int>
	 */
	private function load_into_a_handbook( string $key, bool $managed ): array {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$term   = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		$result = AppHandbook::load( $term, $key, $managed );

		$this->assertIsArray( $result );

		$ids = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term,
					),
				),
			)
		);

		return array_map( 'intval', $ids );
	}
}
