<?php
/**
 * Paths in a GitHub import: the branch in a folder URL, and an image from the
 * repository root.
 *
 * A tree URL was split at fixed slashes, so a branch named "feature/docs" was
 * read as branch "feature" and its folder never found, and a folder with a
 * space stayed percent-encoded and matched nothing in the tree. And an image
 * written from the repository root, "/assets/x.png", was looked for beside the
 * page instead, so it was never fetched and the page pointed at a path that
 * does not exist on the site.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use WP_UnitTestCase;

/**
 * GitSync: branch with a slash, root-relative image.
 */
final class GitHubPathsTest extends WP_UnitTestCase {

	/**
	 * Files of the fake repository, by branch and path.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $repo = array();

	/**
	 * URLs requested.
	 *
	 * @var array<int, string>
	 */
	private array $requested = array();

	/**
	 * Answer the tree API for the branches the repository has, and raw files.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->requested = array();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				unset( $preempt, $args );
				$url               = (string) $url;
				$this->requested[] = $url;

				if ( 1 === preg_match( '#/git/trees/([^?]+)#', $url, $found ) ) {
					$branch = rawurldecode( $found[1] );
					if ( ! isset( $this->repo[ $branch ] ) ) {
						return $this->answer( '{"message":"Not Found"}', 404 );
					}
					$tree = array();
					foreach ( array_keys( $this->repo[ $branch ] ) as $path ) {
						$tree[] = array(
							'path' => $path,
							'type' => 'blob',
						);
					}
					return $this->answer( (string) wp_json_encode( array( 'tree' => $tree ) ) );
				}

				if ( 1 === preg_match( '#^https://raw\.githubusercontent\.com/example/repo/([^/]+)/(.+)$#', $url, $found ) ) {
					$branch = rawurldecode( $found[1] );
					$path   = rawurldecode( $found[2] );
					if ( isset( $this->repo[ $branch ][ $path ] ) ) {
						return $this->answer( $this->repo[ $branch ][ $path ] );
					}
				}
				return $this->answer( '', 404 );
			},
			10,
			3
		);
	}

	/**
	 * A canned response.
	 *
	 * @param string $body Body.
	 * @param int    $code Status.
	 * @return array<string, mixed>
	 */
	private function answer( string $body, int $code = 200 ): array {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => 200 === $code ? 'OK' : 'Not Found',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * A folder on a branch with a slash in its name is imported.
	 *
	 * @return void
	 */
	public function test_a_folder_on_a_branch_with_a_slash_is_imported(): void {
		$this->repo['feature/docs'] = array(
			'handbuch/de/first.md'  => "# First\n\nText.\n",
			'handbuch/de/second.md' => "# Second\n\nText.\n",
		);

		$result = ( new GitSync() )->import_folder_complete( 'https://github.com/example/repo/tree/feature/docs/handbuch/de' );

		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertSame( array( 'First', 'Second' ), wp_list_pluck( $result['pages'], 'title' ) );
	}

	/**
	 * A folder with a space, copied from the address bar, is found.
	 *
	 * @return void
	 */
	public function test_an_encoded_folder_name_is_found(): void {
		$this->repo['main'] = array(
			'Benutzer Handbuch/start.md' => "# Start\n\nText.\n",
		);

		$result = ( new GitSync() )->import_folder_complete( 'https://github.com/example/repo/tree/main/Benutzer%20Handbuch' );

		$this->assertIsArray( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertSame( array( 'Start' ), wp_list_pluck( $result['pages'], 'title' ) );
	}

	/**
	 * An image from the repository root is fetched from there.
	 *
	 * @return void
	 */
	public function test_a_root_relative_image_is_taken_from_the_repository_root(): void {
		$png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A one pixel PNG for the test.

		$this->repo['main'] = array(
			'docs/guide.md'     => "# Guide\n\n![Plan](/assets/plan.png)\n",
			'assets/plan.png'   => $png,
		);

		$id = ( new GitSync() )->create_github_page( 'https://raw.githubusercontent.com/example/repo/main/docs/guide.md' );

		$this->assertGreaterThan( 0, $id );
		$this->assertContains( 'https://raw.githubusercontent.com/example/repo/main/assets/plan.png', $this->requested, 'The image is fetched from the repository root.' );
		$this->assertMatchesRegularExpression( '#<img[^>]+src="[^"]*/uploads/[^"]*plan[^"]*\.png"#', (string) get_post_field( 'post_content', $id ) );
	}
}
