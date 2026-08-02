<?php
/**
 * A folder import large enough to use the repository archive.
 *
 * The point of the archive is the request count: below the threshold the import
 * fetches each file, above it one download serves everything. Both paths have to
 * produce the same pages, and the switch must not change what is imported, only
 * how it is fetched.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;
use ZipArchive;

/**
 * Folder import with and without the archive.
 */
final class FolderImportArchiveTest extends WP_UnitTestCase {

	/**
	 * URLs requested during the test.
	 *
	 * @var array<int, string>
	 */
	private array $requested = array();

	/**
	 * Path of the archive built for the test.
	 *
	 * @var string
	 */
	private string $archive = '';

	/**
	 * Files the fake repository holds, path to contents.
	 *
	 * @var array<string, string>
	 */
	private array $files = array();

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
	 * Remove the built archive.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( '' !== $this->archive && file_exists( $this->archive ) ) {
			wp_delete_file( $this->archive );
		}
		$this->archive = '';
		parent::tear_down();
	}

	/**
	 * Build a fake repository with the given number of Markdown pages.
	 *
	 * @param int $count How many pages under handbuch/de.
	 * @return void
	 */
	private function make_repository( int $count ): void {
		for ( $i = 1; $i <= $count; $i++ ) {
			$this->files[ sprintf( 'handbuch/de/page-%02d.md', $i ) ] = sprintf( "# Page %02d\n\nBody of page %02d.\n", $i, $i );
		}

		$this->rebuild_archive();
	}

	/**
	 * Write the current file list into the archive the fake host serves.
	 *
	 * @return void
	 */
	private function rebuild_archive(): void {
		if ( '' === $this->archive ) {
			$this->archive = (string) tempnam( sys_get_temp_dir(), 'lh-repo-archive' );
		}

		$zip = new ZipArchive();
		$zip->open( $this->archive, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $this->files as $relative => $contents ) {
			$zip->addFromString( 'example-repo-abc123/' . $relative, $contents );
		}
		$zip->close();
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

				// A raw file request: serve it, so the fallback path works too.
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
	 * How many of the recorded requests went to raw.githubusercontent.com.
	 *
	 * @return int
	 */
	private function raw_requests(): int {
		$count = 0;
		foreach ( $this->requested as $url ) {
			if ( false !== strpos( $url, 'raw.githubusercontent.com' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * A large folder is imported from one archive download, not file by file.
	 *
	 * @return void
	 */
	public function test_a_large_folder_uses_the_archive(): void {
		$this->make_repository( 25 );
		$this->serve_repository();

		$term = wp_insert_term( 'Archive handbook', Handbooks::TAXONOMY );
		$this->assertIsArray( $term );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = ( new GitSync() )->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', (int) $term['term_id'], false );

		$this->assertIsArray( $result );
		$this->assertCount( 25, $result['pages'], 'Every page of the folder should be imported.' );
		$this->assertSame( 0, $this->raw_requests(), 'With the archive open, no single file may be fetched.' );

		// One tree call, one archive lookup, one archive download.
		$this->assertCount( 3, $this->requested );

		$titles = wp_list_pluck( $result['pages'], 'title' );
		$this->assertContains( 'Page 01', $titles );
		$this->assertContains( 'Page 25', $titles );
	}

	/**
	 * A small folder stays on the per-file path: downloading the whole
	 * repository for a handful of pages would be the more expensive way.
	 *
	 * @return void
	 */
	public function test_a_small_folder_fetches_each_file(): void {
		$this->make_repository( 3 );
		$this->serve_repository();

		$term = wp_insert_term( 'Small handbook', Handbooks::TAXONOMY );
		$this->assertIsArray( $term );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = ( new GitSync() )->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', (int) $term['term_id'], false );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result['pages'] );
		$this->assertSame( 3, $this->raw_requests(), 'Below the threshold each file is fetched on its own.' );

		foreach ( $this->requested as $url ) {
			$this->assertStringNotContainsString( '/zipball/', $url, 'A small import must not download the repository archive.' );
		}
	}

	/**
	 * A failing archive download does not fail the import: it falls back to
	 * fetching each file and says so.
	 *
	 * @return void
	 */
	public function test_a_failing_archive_falls_back_to_single_files(): void {
		$this->make_repository( 25 );

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				unset( $preempt, $args );
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
					return $this->response( '', null, 500 );
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

		$term = wp_insert_term( 'Fallback handbook', Handbooks::TAXONOMY );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = ( new GitSync() )->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', (int) $term['term_id'], false );

		$this->assertIsArray( $result );
		$this->assertCount( 25, $result['pages'], 'The import has to finish without the archive.' );
		$this->assertSame( 25, $this->raw_requests() );
		$this->assertNotEmpty( $result['notes'] ?? array(), 'The report should say why the import was slow.' );
	}

	/**
	 * Images come out of the archive too, not over HTTP.
	 *
	 * Images are the larger half of an import's request count, so this is where
	 * the archive pays off most. The page still has to end up pointing at a
	 * sideloaded copy, exactly as it does on the per-file path.
	 *
	 * @return void
	 */
	public function test_images_are_read_from_the_archive(): void {
		$this->make_repository( 25 );

		// One page references an image that lives beside it in the repository.
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A one pixel PNG for the test archive, not obfuscation.
		$this->files['handbuch/de/page-01.md']      = "# Page 01\n\n![Plan](assets/plan.png)\n";
		$this->files['handbuch/de/assets/plan.png'] = (string) $png;
		$this->rebuild_archive();
		$this->serve_repository();

		$result = ( new GitSync() )->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', $this->handbook( 'Image handbook' ), false );

		$this->assertIsArray( $result );
		$this->assertSame( 0, $this->raw_requests(), 'The image must not be fetched over HTTP either.' );

		$first   = (int) $result['pages'][0]['id'];
		$content = (string) get_post_field( 'post_content', $first );
		$this->assertStringNotContainsString( 'raw.githubusercontent.com', $content, 'The page must not keep pointing at GitHub.' );
		$this->assertMatchesRegularExpression( '#<img[^>]+src="[^"]*plan[^"]*\.png"#', $content, 'The page should show the sideloaded image.' );

		$attachments = get_children(
			array(
				'post_parent' => $first,
				'post_type'   => 'attachment',
			)
		);
		$this->assertCount( 1, $attachments, 'The image should be in the media library, attached to the page.' );
	}

	/**
	 * The imported pages carry their repository path, whichever way they were
	 * fetched, so internal links keep resolving.
	 *
	 * @return void
	 */
	public function test_imported_pages_keep_their_source_path(): void {
		$this->make_repository( 25 );
		$this->serve_repository();

		$term = wp_insert_term( 'Path handbook', Handbooks::TAXONOMY );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = ( new GitSync() )->import_folder( 'https://github.com/example/repo/tree/main/handbuch/de', (int) $term['term_id'], false );

		$first = (int) $result['pages'][0]['id'];
		$this->assertSame( Handbook::POST_TYPE, get_post_type( $first ) );
		$this->assertStringStartsWith( 'handbuch/de/', (string) get_post_meta( $first, '_lh_source_path', true ) );
	}
}
