<?php
/**
 * The plugin version: for people who are signed in, in the export and in the
 * bundle report (E-0082).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Export\StaticSite;
use LivingHandbook\Frontend\Entry;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookImport;
use ReflectionProperty;
use WP_Term;
use WP_UnitTestCase;

/**
 * Where the version shows, and where it does not.
 */
final class VersionLineTest extends WP_UnitTestCase {

	/**
	 * A public handbook.
	 *
	 * @return WP_Term
	 */
	private function handbook(): WP_Term {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
		$term = get_term( $term_id, Handbooks::TAXONOMY );
		$this->assertInstanceOf( WP_Term::class, $term );
		return $term;
	}

	/**
	 * The entry page shows the version to whoever is signed in, and to nobody
	 * else.
	 *
	 * @return void
	 */
	public function test_entry_page_shows_the_version_only_when_signed_in(): void {
		$term = $this->handbook();
		$line = 'Living Handbook ' . LIVING_HANDBOOK_VERSION;

		wp_set_current_user( 0 );
		$this->assertStringNotContainsString( $line, Entry::render_entry( $term ) );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringContainsString( $line, Entry::render_entry( $term ) );
	}

	/**
	 * While the export renders a page from the template, the entry page does
	 * not add the line: the export writes it into every page itself.
	 *
	 * @return void
	 */
	public function test_entry_page_leaves_the_line_to_the_export(): void {
		$term = $this->handbook();
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$flag = new ReflectionProperty( StaticSite::class, 'rendering' );
		$flag->setAccessible( true );
		$flag->setValue( null, true );
		try {
			$this->assertStringNotContainsString( 'living-handbook-version', Entry::render_entry( $term ) );
		} finally {
			$flag->setValue( null, false );
		}
	}

	/**
	 * The bundle report names the version the bundle was made with, and
	 * ignores a value that is no version.
	 *
	 * @return void
	 */
	public function test_bundle_report_names_the_version(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$target = $this->handbook();
		$reader = static fn( string $file ) => false;

		$report = ( new HandbookImport() )->import_manifest(
			array(
				'exported'       => array( 'plugin_version' => '0.78.0' ),
				'handbook'       => array( 'name' => 'Quelle' ),
				'pages'          => array(),
			),
			$reader,
			HandbookImport::RULE_SKIP,
			$target->term_id
		);
		$this->assertContains( 'The bundle was made with Living Handbook 0.78.0.', $report['notes'] );

		$report = ( new HandbookImport() )->import_manifest(
			array(
				'exported'       => array( 'plugin_version' => '<b>1</b>' ),
				'handbook'       => array( 'name' => 'Quelle' ),
				'pages'          => array(),
			),
			$reader,
			HandbookImport::RULE_SKIP,
			$target->term_id
		);
		foreach ( (array) $report['notes'] as $note ) {
			$this->assertStringNotContainsString( 'made with', (string) $note );
		}
	}
}
