<?php
/**
 * The repository archive as an import source.
 *
 * Covers the two things that matter about it: that it turns one download into
 * a readable set of repository files, and that it refuses to follow a redirect
 * to a host that is not meant to serve archives.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\ArchiveSource;
use WP_Error;
use WP_UnitTestCase;
use ZipArchive;

/**
 * Archive download and reading.
 */
final class ArchiveSourceTest extends WP_UnitTestCase {

	/**
	 * URLs requested during a test.
	 *
	 * @var array<int, string>
	 */
	private array $requested = array();

	/**
	 * Path of the archive built for a test.
	 *
	 * @var string
	 */
	private string $archive = '';

	/**
	 * Reset the request log.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->requested = array();
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
	 * Build a small repository archive, wrapped in the folder GitHub adds.
	 *
	 * @param array<string, string> $files Path inside the repository to contents.
	 * @return string Path of the archive.
	 */
	private function make_archive( array $files ): string {
		$path = (string) tempnam( sys_get_temp_dir(), 'lh-test-archive' );
		$zip  = new ZipArchive();
		$this->assertTrue( true === $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );
		$zip->addEmptyDir( 'example-repo-abc123' );
		foreach ( $files as $relative => $contents ) {
			$zip->addFromString( 'example-repo-abc123/' . $relative, $contents );
		}
		$zip->close();

		$this->archive = $path;
		return $path;
	}

	/**
	 * Answer the API with a redirect and the archive host with the file.
	 *
	 * @param string $archive_path Archive to serve.
	 * @param string $location     Redirect target the API returns.
	 * @return void
	 */
	private function serve_archive( string $archive_path, string $location = 'https://codeload.github.com/example/repo/zip/abc123' ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $archive_path, $location ) {
				unset( $preempt );
				$this->requested[] = (string) $url;

				if ( false !== strpos( (string) $url, 'api.github.com' ) ) {
					return array(
						'headers'  => array( 'location' => $location ),
						'body'     => '',
						'response' => array(
							'code'    => 302,
							'message' => 'Found',
						),
						'cookies'  => array(),
						'filename' => null,
					);
				}

				// The archive host: WordPress streams to a file, so write it there.
				if ( isset( $args['filename'] ) && is_string( $args['filename'] ) && '' !== $args['filename'] ) {
					copy( $archive_path, $args['filename'] );
				}

				return array(
					'headers'  => array(),
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => $args['filename'] ?? null,
				);
			},
			10,
			3
		);
	}

	/**
	 * One download makes every file of the repository readable.
	 *
	 * @return void
	 */
	public function test_one_download_serves_the_whole_folder(): void {
		$this->serve_archive(
			$this->make_archive(
				array(
					'handbuch/de/README.md'      => "# Overview\n",
					'handbuch/de/erste-seite.md' => "# First page\n\nText.\n",
					'handbuch/de/assets/plan.png' => 'binary-ish',
					'handbuch/en/README.md'      => "# Overview EN\n",
					'README.md'                  => "# Repository\n",
				)
			)
		);

		$source = new ArchiveSource();
		$result = $source->open( 'example', 'repo', 'main' );

		$this->assertTrue( true === $result, 'The archive should open.' );
		$this->assertCount( 2, $this->requested, 'One request to the API, one to the archive host.' );

		$markdown = $source->files_under( 'handbuch/de', '.md' );
		$this->assertSame( array( 'handbuch/de/README.md', 'handbuch/de/erste-seite.md' ), $markdown );

		$this->assertSame( "# First page\n\nText.\n", $source->contents( 'handbuch/de/erste-seite.md' ) );
		$this->assertTrue( $source->has( 'handbuch/de/assets/plan.png' ) );
		$this->assertNull( $source->contents( 'handbuch/de/missing.md' ) );

		$source->close();
	}

	/**
	 * The whole repository is readable when no folder is given.
	 *
	 * @return void
	 */
	public function test_files_under_root_returns_everything(): void {
		$this->serve_archive(
			$this->make_archive(
				array(
					'docs/a.md' => 'a',
					'b.md'      => 'b',
				)
			)
		);

		$source = new ArchiveSource();
		$this->assertTrue( true === $source->open( 'example', 'repo', 'main' ) );
		$this->assertSame( array( 'b.md', 'docs/a.md' ), $source->files_under( '', '.md' ) );
		$source->close();
	}

	/**
	 * A redirect to a host that is not meant to serve archives is refused.
	 *
	 * This is the SSRF guard: the redirect is followed by hand precisely so the
	 * target can be checked instead of trusted.
	 *
	 * @return void
	 */
	public function test_redirect_to_a_foreign_host_is_refused(): void {
		$this->serve_archive(
			$this->make_archive( array( 'a.md' => 'a' ) ),
			'https://evil.example.com/repo.zip'
		);

		$source = new ArchiveSource();
		$result = $source->open( 'example', 'repo', 'main' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertCount( 1, $this->requested, 'The archive host must not be contacted at all.' );
		$source->close();
	}

	/**
	 * A host added through the filter is accepted, the ordinary source list is
	 * not touched by it.
	 *
	 * @return void
	 */
	public function test_allowed_archive_hosts_are_filterable(): void {
		$this->assertTrue( ArchiveSource::is_allowed_archive_url( 'https://codeload.github.com/x/y/zip/main' ) );
		$this->assertFalse( ArchiveSource::is_allowed_archive_url( 'https://example.com/x.zip' ) );
		$this->assertFalse( ArchiveSource::is_allowed_archive_url( 'http://codeload.github.com/x/y/zip/main' ), 'Plain HTTP must be refused.' );

		add_filter(
			'living_handbook_archive_allowed_hosts',
			static function ( array $hosts ): array {
				$hosts[] = 'git.example.org';
				return $hosts;
			}
		);

		$this->assertTrue( ArchiveSource::is_allowed_archive_url( 'https://git.example.org/x.zip' ) );
	}
}
