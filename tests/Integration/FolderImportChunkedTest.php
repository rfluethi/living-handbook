<?php
/**
 * A folder import that does not fit into one request.
 *
 * PHP stops a request after a while, so a few hundred pages cannot be imported
 * in one go. The import therefore stops on its own, hands back a job id and is
 * asked again for the rest. What matters here: the same pages come out either
 * way, nothing is imported twice, and the job belongs to whoever started it.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\ArchiveSource;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use WP_Error;
use WP_UnitTestCase;
use ZipArchive;

/**
 * Chunked folder import.
 */
final class FolderImportChunkedTest extends WP_UnitTestCase {

	/**
	 * URLs requested during the test.
	 *
	 * @var array<int, string>
	 */
	private array $requested = array();

	/**
	 * Files the fake repository holds, path to contents.
	 *
	 * @var array<string, string>
	 */
	private array $files = array();

	/**
	 * Path of the archive built for the test.
	 *
	 * @var string
	 */
	private string $archive = '';

	/**
	 * Reset state.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->requested = array();
		$this->files     = array();
	}

	/**
	 * Remove the built archive and anything the import kept.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( '' !== $this->archive && file_exists( $this->archive ) ) {
			wp_delete_file( $this->archive );
		}
		$this->archive = '';
		ArchiveSource::cleanup_stale( 0 );
		parent::tear_down();
	}

	/**
	 * Make every pass of an import stop after one page.
	 *
	 * @return void
	 */
	private function stop_after_every_page(): void {
		add_filter(
			'living_handbook_import_time_budget',
			static function (): float {
				return -1.0;
			}
		);
	}

	/**
	 * Build a fake repository with the given number of Markdown pages.
	 *
	 * @param int  $count  How many pages under handbuch/de.
	 * @param bool $linked Whether the pages link to each other.
	 * @return void
	 */
	private function make_repository( int $count, bool $linked = false ): void {
		for ( $i = 1; $i <= $count; $i++ ) {
			$body = sprintf( "# Page %02d\n\nBody of page %02d.\n", $i, $i );
			if ( $linked ) {
				// Every page links to the next one, the last one back to the first,
				// so no page can resolve its link at the moment it is created.
				$next  = $i < $count ? $i + 1 : 1;
				$body .= sprintf( "\nSee [page-%02d.md](page-%02d.md).\n", $next, $next );
			}
			$this->files[ sprintf( 'handbuch/de/page-%02d.md', $i ) ] = $body;
		}

		$path = (string) tempnam( sys_get_temp_dir(), 'lh-repo-chunked' );
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $this->files as $relative => $contents ) {
			$zip->addFromString( 'example-repo-abc123/' . $relative, $contents );
		}
		$zip->close();
		$this->archive = $path;
	}

	/**
	 * Answer the tree API, the archive endpoints and the raw files.
	 *
	 * @return void
	 */
	private function serve_repository(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				unset( $preempt );
				$url               = (string) $url;
				$this->requested[] = $url;

				if ( false !== strpos( $url, '/git/trees/' ) ) {
					$tree = array();
					foreach ( array_keys( $this->files ) as $path ) {
						$tree[] = array(
							'path' => $path,
							'type' => 'blob',
						);
					}
					return $this->response( (string) wp_json_encode( array( 'tree' => $tree ) ) );
				}

				if ( false !== strpos( $url, '/zipball/' ) ) {
					return array(
						'headers'  => array( 'location' => 'https://codeload.github.com/example/repo/zip/abc123' ),
						'body'     => '',
						'response' => array(
							'code'    => 302,
							'message' => 'Found',
						),
						'cookies'  => array(),
						'filename' => null,
					);
				}

				if ( false !== strpos( $url, 'codeload.github.com' ) ) {
					if ( isset( $args['filename'] ) && is_string( $args['filename'] ) ) {
						copy( $this->archive, $args['filename'] );
					}
					return $this->response( '', $args['filename'] ?? null );
				}

				$path = (string) wp_parse_url( $url, PHP_URL_PATH );
				foreach ( $this->files as $relative => $contents ) {
					if ( false !== strpos( $path, $relative ) ) {
						return $this->response( $contents );
					}
				}

				return $this->response( '', null, 404 );
			},
			10,
			3
		);
	}

	/**
	 * Build an HTTP response array.
	 *
	 * @param string      $body     Response body.
	 * @param string|null $filename Streamed file, if any.
	 * @param int         $code     Status code.
	 * @return array<string, mixed>
	 */
	private function response( string $body, ?string $filename = null, int $code = 200 ): array {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => 200 === $code ? 'OK' : 'Error',
			),
			'cookies'  => array(),
			'filename' => $filename,
		);
	}

	/**
	 * How many of the recorded requests match a fragment.
	 *
	 * @param string $fragment Part of a URL.
	 * @return int
	 */
	private function requests_matching( string $fragment ): int {
		$count = 0;
		foreach ( $this->requested as $url ) {
			if ( false !== strpos( $url, $fragment ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Create a handbook and sign in as someone who may import.
	 *
	 * @param string $name Handbook name.
	 * @return int Term id.
	 */
	private function handbook( string $name ): int {
		$term = wp_insert_term( $name, Handbooks::TAXONOMY );
		$this->assertIsArray( $term );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		return (int) $term['term_id'];
	}

	/**
	 * An import out of time hands back a job, and the job finishes the work.
	 *
	 * @return void
	 */
	public function test_an_import_continues_where_it_stopped(): void {
		$this->make_repository( 6 );
		$this->serve_repository();
		$this->stop_after_every_page();

		$git    = new GitSync();
		$result = $git->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', $this->handbook( 'Chunked handbook' ), false );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'job', $result, 'An import that ran out of time has to say how to continue it.' );
		$this->assertCount( 1, $result['pages'], 'The first pass got exactly one page done.' );
		$this->assertSame( 6, $result['total'] );
		$this->assertSame( 5, $result['remaining'] );

		$titles = wp_list_pluck( $result['pages'], 'title' );
		$job    = (string) $result['job'];
		$passes = 1;

		while ( '' !== $job ) {
			$next = $git->import_folder( '', 0, false, $job );
			$this->assertIsArray( $next, 'Continuing an import must not fail.' );
			$titles = array_merge( $titles, wp_list_pluck( $next['pages'], 'title' ) );
			$job    = isset( $next['job'] ) ? (string) $next['job'] : '';
			++$passes;
			$this->assertLessThan( 20, $passes, 'The import has to end.' );
		}

		$this->assertCount( 6, $titles, 'Every page is imported exactly once, across all passes.' );
		$this->assertSame( $titles, array_unique( $titles ) );
		$this->assertContains( 'Page 01', $titles );
		$this->assertContains( 'Page 06', $titles );
	}

	/**
	 * Resolving the links is the second phase of the same job, and it pauses too.
	 *
	 * It used to run in one go at the end of the last pass, in the request that
	 * had just spent the whole import budget. On a large handbook that is the
	 * request that runs into the server's time limit, and it is the one that must
	 * not: without it the pages keep links that lead nowhere.
	 *
	 * The second half of the test is the reason the first half matters: a page
	 * created before its link target existed must end up with a working link.
	 *
	 * @return void
	 */
	public function test_the_links_are_resolved_across_passes(): void {
		$this->make_repository( 4, true );
		$this->serve_repository();
		$this->stop_after_every_page();

		$git    = new GitSync();
		$result = $git->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', $this->handbook( 'Linked handbook' ), false );
		$this->assertIsArray( $result );

		$phases = array( (string) ( $result['phase'] ?? '' ) );
		$job    = isset( $result['job'] ) ? (string) $result['job'] : '';
		$passes = 1;

		while ( '' !== $job ) {
			$next = $git->import_folder( '', 0, false, $job );
			$this->assertIsArray( $next, 'Continuing an import must not fail.' );
			$phases[] = (string) ( $next['phase'] ?? '' );
			$job      = isset( $next['job'] ) ? (string) $next['job'] : '';
			++$passes;
			$this->assertLessThan( 30, $passes, 'The import has to end.' );
		}

		$this->assertContains( 'links', $phases, 'The link phase has to report itself, so the screen can say what it is doing.' );
		$this->assertGreaterThan(
			1,
			count( array_keys( $phases, 'links', true ) ),
			'It has to pause between pages like the import does.'
		);

		$pages = get_posts(
			array(
				'post_type'      => 'handbook',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'title'          => 'Page 01',
			)
		);
		$this->assertCount( 1, $pages, 'Page 01 exists exactly once.' );

		$content = (string) $pages[0]->post_content;
		$this->assertStringNotContainsString( 'page-02.md', $content, 'No raw .md link may be left behind.' );
		$this->assertStringContainsString( '<a href="http', $content, 'The link has to point at the imported page.' );
	}

	/**
	 * When GitHub stops answering, the import stops too.
	 *
	 * Sixty requests an hour is what an unauthenticated import gets. Running into
	 * that used to mean the import carried on and wrote an error onto every
	 * remaining page, which leaves a handbook that looks imported and is not. It
	 * now stops on a whole page, says what it managed and when the limit resets,
	 * and does not ask again.
	 *
	 * @return void
	 */
	public function test_an_import_stops_when_the_request_limit_is_reached(): void {
		$this->make_repository( 5 );

		// From the second file on, GitHub answers the way it does when the hour's
		// quota is gone. This runs after the filter that serves the files and
		// replaces its answer, which is why it has the higher priority.
		$served = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$served ) {
				unset( $args );
				// Anything this does not answer keeps the answer it already has:
				// returning false here would throw the served file away.
				if ( false === strpos( (string) $url, 'raw.githubusercontent.com' ) ) {
					return $preempt;
				}
				++$served;
				if ( $served < 2 ) {
					return $preempt;
				}
				return array(
					'headers'  => array(
						'x-ratelimit-remaining' => '0',
						'x-ratelimit-reset'     => (string) ( time() + 900 ),
					),
					'body'     => '',
					'response' => array(
						'code'    => 403,
						'message' => 'Forbidden',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			11,
			3
		);
		$this->serve_repository();

		$git    = new GitSync();
		$result = $git->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', $this->handbook( 'Limited handbook' ), false );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'retry_after', $result, 'The import has to say that asking again now is pointless.' );
		$this->assertGreaterThan( 0, $result['retry_after'] );
		$this->assertLessThanOrEqual( 900, $result['retry_after'] );
		$this->assertArrayHasKey( 'notes', $result, 'And it has to say what happened.' );
		$this->assertStringContainsString( 'GitHub', implode( ' ', $result['notes'] ) );

		$imported = get_posts(
			array(
				'post_type'      => 'handbook',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$this->assertLessThan( 5, count( $imported ), 'It must not carry on creating the rest.' );
	}

	/**
	 * The pages are the same whether the import is cut into passes or not.
	 *
	 * @return void
	 */
	public function test_chunked_and_whole_imports_agree(): void {
		$this->make_repository( 5 );
		$this->serve_repository();

		$git   = new GitSync();
		$whole = $git->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', $this->handbook( 'Whole handbook' ), false );
		$this->assertIsArray( $whole );
		$this->assertArrayNotHasKey( 'job', $whole, 'A small import finishes in one pass.' );

		$this->stop_after_every_page();
		$chunked = $git->import_folder_complete( 'https://github.com/example/repo/tree/main/handbuch/de', $this->handbook( 'Piecewise handbook' ), false );

		$this->assertIsArray( $chunked );
		$this->assertSame(
			wp_list_pluck( $whole['pages'], 'title' ),
			wp_list_pluck( $chunked['pages'], 'title' ),
			'Cutting an import into passes must not change what comes out, nor in which order.'
		);
	}

	/**
	 * The repository is downloaded once for the whole import, not once per pass.
	 *
	 * @return void
	 */
	public function test_the_archive_is_downloaded_once_for_all_passes(): void {
		$this->make_repository( 25 );
		$this->serve_repository();
		$this->stop_after_every_page();

		$git = new GitSync();
		$git->import_folder_complete( 'https://github.com/example/repo/tree/main/handbuch/de', $this->handbook( 'Archive handbook' ), false );

		$this->assertSame( 1, $this->requests_matching( '/zipball/' ), 'Every pass reopens the file the first one downloaded.' );
		$this->assertSame( 0, $this->requests_matching( 'raw.githubusercontent.com' ), 'No pass may fall back to single files.' );
	}

	/**
	 * A job cannot be continued by someone else.
	 *
	 * @return void
	 */
	public function test_a_job_belongs_to_the_user_who_started_it(): void {
		$this->make_repository( 4 );
		$this->serve_repository();
		$this->stop_after_every_page();

		$git    = new GitSync();
		$result = $git->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', $this->handbook( 'Owned handbook' ), false );
		$this->assertArrayHasKey( 'job', $result );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$stolen = $git->import_folder( '', 0, false, (string) $result['job'] );

		$this->assertInstanceOf( WP_Error::class, $stolen );
		$this->assertSame( 410, $stolen->get_error_data()['status'] ?? 0 );
	}

	/**
	 * A job that is no longer there says so instead of starting over.
	 *
	 * @return void
	 */
	public function test_a_forgotten_job_is_reported(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = ( new GitSync() )->import_folder( '', 0, false, 'nosuchjobatall' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 410, $result->get_error_data()['status'] ?? 0 );
	}

	/**
	 * An abandoned import does not leave its download behind for good.
	 *
	 * @return void
	 */
	public function test_stale_archives_are_collected(): void {
		$old = (string) tempnam( sys_get_temp_dir(), 'lh-archive' );
		$new = (string) tempnam( sys_get_temp_dir(), 'lh-archive' );
		touch( $old, time() - 10000 );

		$deleted = ArchiveSource::cleanup_stale( 7200 );

		$this->assertGreaterThanOrEqual( 1, $deleted );
		$this->assertFileDoesNotExist( $old, 'An archive nobody came back for is deleted.' );
		$this->assertFileExists( $new, 'A download that may still be in use is left alone.' );

		wp_delete_file( $new );
	}
}
