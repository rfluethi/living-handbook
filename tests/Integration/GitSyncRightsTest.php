<?php
/**
 * Who may have a page pulled from GitHub.
 *
 * A pull stores the images its Markdown references in the media library. Up
 * to 0.76.0 saving GitHub as the source and "Sync now" asked only for
 * edit_post, so a contributor, who may not upload a single file, could have
 * the sync upload for them from any repository, now and on every scheduled
 * run after.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use LivingHandbook\PostType\Handbook;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use WPDieException;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * GitSync::save_meta() and GitSync::sync_now() against upload_files.
 */
final class GitSyncRightsTest extends WP_UnitTestCase {

	private const URL = 'https://github.com/example/repo/blob/main/page.md';

	/**
	 * URLs the sync asked for.
	 *
	 * @var array<int, string>
	 */
	private array $requested = array();

	/**
	 * Answer every request with a small Markdown page and record it.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->requested = array();
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				unset( $preempt, $args );
				$this->requested[] = (string) $url;
				return array(
					'headers'  => array(),
					'body'     => "# Pulled\n\nBody.\n",
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Clear the request superglobals.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * A draft of the given user, and that user signed in.
	 *
	 * @param string $role Role.
	 * @return int Post ID.
	 */
	private function own_draft( string $role ): int {
		$user = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user );
		return (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'draft',
				'post_author' => $user,
			)
		);
	}

	/**
	 * Save the source box as the browser would.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_github_source( int $post_id ): void {
		$_POST = array(
			'living_handbook_git_nonce'       => wp_create_nonce( 'living_handbook_git' ),
			'living_handbook_source'          => GitSync::SOURCE_GITHUB,
			'living_handbook_markdown_source' => self::URL,
		);
		( new GitSync() )->save_meta( $post_id );
	}

	/**
	 * A contributor cannot set GitHub as the source of their own draft.
	 *
	 * @return void
	 */
	public function test_a_contributor_cannot_set_github_as_the_source(): void {
		$post_id = $this->own_draft( 'contributor' );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ), 'The test needs a user who may edit the page.' );

		$this->save_github_source( $post_id );

		$this->assertNotSame( GitSync::SOURCE_GITHUB, get_post_meta( $post_id, GitSync::META_SOURCE, true ), 'The source stays as it was.' );
		$this->assertSame( '', get_post_meta( $post_id, GitSync::META_URL, true ), 'So does the address.' );
		$this->assertSame( array(), $this->requested, 'Nothing is pulled.' );
	}

	/**
	 * An author, who may upload files, still can.
	 *
	 * @return void
	 */
	public function test_an_author_sets_github_as_the_source(): void {
		$post_id = $this->own_draft( 'author' );

		$this->save_github_source( $post_id );

		$this->assertSame( GitSync::SOURCE_GITHUB, get_post_meta( $post_id, GitSync::META_SOURCE, true ) );
		$this->assertNotSame( array(), $this->requested, 'The page is pulled on save.' );
	}

	/**
	 * A contributor cannot have a GitHub page synced now.
	 *
	 * @return void
	 */
	public function test_a_contributor_cannot_sync_now(): void {
		$post_id = $this->own_draft( 'contributor' );
		update_post_meta( $post_id, GitSync::META_SOURCE, GitSync::SOURCE_GITHUB );
		update_post_meta( $post_id, GitSync::META_URL, GitSync::normalize_url( self::URL ) );

		$_GET['post']         = (string) $post_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'living_handbook_git_sync_now_' . $post_id );
		// The allowed path ends in a redirect and exit; stop it before the exit.
		$redirect = static function () {
			throw new RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $redirect );

		$refused = false;
		try {
			( new GitSync() )->sync_now();
		} catch ( WPDieException $e ) {
			$refused = true;
		} catch ( RuntimeException $e ) {
			$refused = false;
		}
		remove_filter( 'wp_redirect', $redirect );

		$this->assertTrue( $refused, 'The request is refused.' );
		$this->assertSame( array(), $this->requested, 'Nothing is pulled.' );
	}

	/**
	 * Nor can a contributor set it over the REST API, which the source box does
	 * not go through. Up to 0.75.1 both keys were writable there for anybody
	 * with edit_post, and the scheduled sync then pulled the page and stored its
	 * images: the upload_files rule of the source box, bypassed.
	 *
	 * @return void
	 */
	public function test_a_contributor_cannot_set_the_source_over_rest(): void {
		$post_id = $this->own_draft( 'contributor' );
		// The test case resets the registered meta between tests.
		( new GitSync() )->register_meta();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/' . Handbook::POST_TYPE . '/' . $post_id );
		$request->set_body_params(
			array(
				'meta' => array(
					GitSync::META_SOURCE => GitSync::SOURCE_GITHUB,
					GitSync::META_URL    => GitSync::normalize_url( self::URL ),
				),
			)
		);
		rest_do_request( $request );

		$this->assertNotSame( GitSync::SOURCE_GITHUB, get_post_meta( $post_id, GitSync::META_SOURCE, true ), 'The source stays as it was.' );
		$this->assertSame( '', get_post_meta( $post_id, GitSync::META_URL, true ), 'So does the address.' );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post_id, GitSync::META_SOURCE ), 'No other write path lets it through either.' );
		$this->assertSame( array(), $this->requested, 'Nothing is pulled.' );
	}

	/**
	 * A write that throws does not leave the sync believing it is still busy.
	 *
	 * Up to 0.75.1 the guard against re-entering the save handler was set
	 * around the write and cleared only when the write returned. Once a write
	 * threw, every later save and sync in the request returned at once.
	 *
	 * @return void
	 */
	public function test_a_write_that_throws_clears_the_sync_guard(): void {
		$post_id = $this->own_draft( 'administrator' );
		$throw   = static function () {
			throw new RuntimeException( 'write failed' );
		};
		add_filter( 'wp_insert_post_data', $throw );

		$render = new ReflectionMethod( GitSync::class, 'render_markdown_into_post' );
		$render->setAccessible( true );
		$thrown = false;
		try {
			$render->invoke( new GitSync(), $post_id, "# Title\n\nBody.\n" );
		} catch ( RuntimeException $e ) {
			$thrown = true;
		}
		remove_filter( 'wp_insert_post_data', $throw );

		$guard = new ReflectionProperty( GitSync::class, 'is_syncing' );
		$guard->setAccessible( true );

		$this->assertTrue( $thrown, 'The test needs a write that throws.' );
		$this->assertFalse( $guard->getValue(), 'The guard is down again.' );
	}

	/**
	 * The source box offers GitHub to a contributor only as a disabled choice
	 * with the reason, and shows a github.com address as the example.
	 *
	 * @return void
	 */
	public function test_the_source_box_says_why_github_is_not_offered(): void {
		$post_id = $this->own_draft( 'contributor' );
		ob_start();
		( new GitSync() )->render_meta_box( get_post( $post_id ) );
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/value="github"[^>]*disabled/', $html );
		$this->assertStringContainsString( 'upload files', $html );
		$this->assertStringContainsString( 'placeholder="https://github.com/owner/repo/blob/main/page.md"', $html );
		$this->assertStringNotContainsString( 'living_handbook_git_sync_now', $html, 'No "Sync now" button.' );
	}
}
