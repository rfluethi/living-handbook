<?php
/**
 * What a static website export costs, and what it leaves lying around.
 *
 * Three things grew with the handbook in 0.75.0. The table of page addresses
 * every link is rewritten against was rebuilt for every page, so the
 * permalinks asked for grew with the square of the page count. The search
 * text of every page and the list of every copied image were kept in the job's
 * transient, which at two thousand pages outgrew what the database stores, and
 * the next pass reported the export as expired. And an export whose download
 * never happened left its ZIP in the temp folder for good.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * StaticSite: cost per page, size of the job state, temp files.
 */
final class StaticExportCostTest extends WP_UnitTestCase {

	/**
	 * Archives the tests produced.
	 *
	 * @var array<int, string>
	 */
	private array $files = array();

	/**
	 * A REST server and somebody who may export.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * Remove the archives.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			wp_delete_file( $file );
			wp_delete_file( $file . '-index' );
		}
		parent::tear_down();
	}

	/**
	 * A public handbook with this many pages.
	 *
	 * @param int $pages Page count.
	 * @return int Term id.
	 */
	private function handbook( int $pages ): int {
		$term = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Cost ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
		for ( $i = 0; $i < $pages; $i++ ) {
			$id = (int) self::factory()->post->create(
				array(
					'post_type'    => Handbook::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => 'Page ' . $i,
					'post_content' => str_repeat( 'Some text to search. ', 40 ),
				)
			);
			wp_set_object_terms( $id, array( $term ), Handbooks::TAXONOMY );
		}
		return $term;
	}

	/**
	 * One pass of the export.
	 *
	 * @param array<string, mixed> $params Request parameters.
	 * @return array<string, mixed>
	 */
	private function pass( array $params ): array {
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/static-export' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return (array) rest_do_request( $request )->get_data();
	}

	/**
	 * Export a handbook in one pass and count the permalinks it asked for.
	 *
	 * @param int $pages Page count.
	 * @return int
	 */
	private function permalinks_for( int $pages ): int {
		$term  = $this->handbook( $pages );
		$count = 0;
		// Only what the export asks for itself. The navigation block in the
		// template renders the whole page tree into every page, so its links grow
		// with the square of the pages by nature: that is the size of the output,
		// not wasted work.
		$tally = static function ( $link ) use ( &$count ) {
			foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- telling the callers apart is the point of this test.
				if ( 'LivingHandbook\\Frontend\\Navigation' === ( $frame['class'] ?? '' ) ) {
					return $link;
				}
			}
			++$count;
			return $link;
		};
		add_filter( 'post_type_link', $tally );
		$data = $this->pass( array( 'handbook' => $term ) );
		remove_filter( 'post_type_link', $tally );

		$this->assertTrue( (bool) ( $data['done'] ?? false ) );
		$state         = get_transient( 'living_handbook_static_' . (string) $data['job'] );
		$this->files[] = (string) $state['zip'];
		return $count;
	}

	/**
	 * Twice the pages may cost about twice the permalinks, not four times.
	 *
	 * @return void
	 */
	public function test_permalinks_grow_with_the_pages_not_with_their_square(): void {
		$small = $this->permalinks_for( 10 );
		$large = $this->permalinks_for( 20 );

		$this->assertLessThan( 3 * $small, $large, sprintf( 'Ten pages asked for %d permalinks, twenty for %d.', $small, $large ) );
	}

	/**
	 * The paused job carries no search text and no list of copied files.
	 *
	 * @return void
	 */
	public function test_a_paused_job_stays_small(): void {
		$term = $this->handbook( 3 );

		add_filter( 'living_handbook_static_export_time_budget', '__return_zero' );
		$first  = $this->pass( array( 'handbook' => $term ) );
		$second = $this->pass( array( 'job' => $first['job'] ) );
		remove_filter( 'living_handbook_static_export_time_budget', '__return_zero' );

		$this->assertFalse( (bool) ( $second['done'] ?? false ) );
		$state         = get_transient( 'living_handbook_static_' . (string) $first['job'] );
		$this->files[] = (string) $state['zip'];

		$this->assertArrayNotHasKey( 'entries', $state, 'The search text of the pages does not travel in the transient.' );
		$this->assertArrayNotHasKey( 'media', $state );
		$this->assertStringNotContainsString( 'Some text to search', (string) wp_json_encode( $state ) );

		$third = $this->pass( array( 'job' => $first['job'] ) );
		$this->assertTrue( (bool) ( $third['done'] ?? false ) );

		$zip = new \ZipArchive();
		$this->assertTrue( true === $zip->open( (string) $state['zip'] ) );
		$index = (string) $zip->getFromName( 'assets/search-index.js' );
		$this->assertSame( 3, substr_count( $index, '"t":"Page ' ), 'Every page is still in the search, also those from the earlier passes.' );
		$zip->close();
	}

	/**
	 * A ZIP nobody downloaded is gone after a while, and a fresh one is not
	 * touched.
	 *
	 * @return void
	 */
	public function test_an_abandoned_export_file_is_collected(): void {
		$old = (string) tempnam( sys_get_temp_dir(), 'lh-site' );
		$new = (string) tempnam( sys_get_temp_dir(), 'lh-site' );
		$own = (string) tempnam( sys_get_temp_dir(), 'other' );
		touch( $old, time() - 3 * HOUR_IN_SECONDS );
		touch( $own, time() - 3 * HOUR_IN_SECONDS );
		$this->files[] = $new;
		$this->files[] = $own;

		$this->pass( array( 'handbook' => $this->handbook( 1 ) ) );

		$this->assertFileDoesNotExist( $old, 'An export file older than any job is collected.' );
		$this->assertFileExists( $new, 'A running export keeps its file.' );
		$this->assertFileExists( $own, 'Nobody else\'s file is touched.' );
	}
}
