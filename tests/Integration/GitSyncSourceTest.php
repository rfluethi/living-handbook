<?php
/**
 * GitHub source allowlist integration tests (SSRF guard).
 *
 * The allowlist decides which URLs the server will fetch. is_allowed_source is
 * exercised through reflection so the exact host and scheme rules are pinned,
 * and create_github_page is checked at the public surface: a rejected URL must
 * create no page and make no request.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use LivingHandbook\PostType\Handbook;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * The Markdown source host allowlist.
 */
final class GitSyncSourceTest extends WP_UnitTestCase {

	/**
	 * Invoke the private static is_allowed_source through reflection.
	 *
	 * @param string $url URL to test.
	 * @return bool
	 */
	private function is_allowed( string $url ): bool {
		$method = new ReflectionMethod( GitSync::class, 'is_allowed_source' );
		$method->setAccessible( true );
		return (bool) $method->invoke( null, $url );
	}

	/**
	 * An https URL on the default allowed host is accepted.
	 *
	 * @return void
	 */
	public function test_https_raw_host_is_allowed(): void {
		$this->assertTrue( $this->is_allowed( 'https://raw.githubusercontent.com/acme/docs/main/guide.md' ) );
	}

	/**
	 * A plain-text http URL is rejected even on the allowed host, so the source
	 * cannot be fetched over an unencrypted, tamperable connection.
	 *
	 * @return void
	 */
	public function test_http_scheme_is_rejected(): void {
		$this->assertFalse( $this->is_allowed( 'http://raw.githubusercontent.com/acme/docs/main/guide.md' ) );
	}

	/**
	 * A host that is not on the allowlist is rejected, so the server cannot be
	 * pointed at an arbitrary (possibly internal) address.
	 *
	 * @return void
	 */
	public function test_unlisted_host_is_rejected(): void {
		$this->assertFalse( $this->is_allowed( 'https://evil.example.com/guide.md' ) );
		$this->assertFalse( $this->is_allowed( 'https://127.0.0.1/guide.md' ) );
	}

	/**
	 * The allowlist can be widened through the documented filter.
	 *
	 * @return void
	 */
	public function test_filter_widens_the_allowlist(): void {
		$this->assertFalse( $this->is_allowed( 'https://git.example.com/guide.md' ) );

		$add = static function ( array $hosts ): array {
			$hosts[] = 'git.example.com';
			return $hosts;
		};
		add_filter( 'living_handbook_sync_allowed_hosts', $add );
		$this->assertTrue( $this->is_allowed( 'https://git.example.com/guide.md' ) );
		remove_filter( 'living_handbook_sync_allowed_hosts', $add );
	}

	/**
	 * create_github_page rejects an http URL up front: it returns 0 and creates
	 * no page, so no request is made.
	 *
	 * @return void
	 */
	public function test_create_github_page_rejects_http_url(): void {
		$before = wp_count_posts( Handbook::POST_TYPE );
		$id     = ( new GitSync() )->create_github_page( 'http://raw.githubusercontent.com/acme/docs/main/guide.md' );
		$after  = wp_count_posts( Handbook::POST_TYPE );

		$this->assertSame( 0, $id );
		$this->assertEquals( $before, $after, 'No handbook page may be created for a rejected URL.' );
	}

	/**
	 * create_github_page rejects a URL on an unlisted host the same way.
	 *
	 * @return void
	 */
	public function test_create_github_page_rejects_unlisted_host(): void {
		$id = ( new GitSync() )->create_github_page( 'https://evil.example.com/guide.md' );
		$this->assertSame( 0, $id );
	}
}
