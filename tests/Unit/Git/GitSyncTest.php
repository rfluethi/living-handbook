<?php
/**
 * Unit tests for the GitHub source URL normalisation.
 *
 * These run without WordPress: normalize_url is pure string handling, so it is
 * the one part of GitSync that can be checked in isolation.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Git;

use LivingHandbook\Git\GitSync;
use PHPUnit\Framework\TestCase;

/**
 * GitHub source URL normalisation.
 */
final class GitSyncTest extends TestCase {

	/**
	 * A github.com blob URL becomes a raw.githubusercontent.com URL.
	 *
	 * @return void
	 */
	public function test_blob_url_is_rewritten_to_raw(): void {
		$this->assertSame(
			'https://raw.githubusercontent.com/acme/docs/main/guide.md',
			GitSync::normalize_url( 'https://github.com/acme/docs/blob/main/guide.md' )
		);
	}

	/**
	 * A plain-text http blob URL is upgraded to an https raw URL, so a later
	 * https-only allowlist check does not reject a link a user pasted as http.
	 *
	 * @return void
	 */
	public function test_http_blob_url_is_upgraded_to_https_raw(): void {
		$this->assertSame(
			'https://raw.githubusercontent.com/acme/docs/main/guide.md',
			GitSync::normalize_url( 'http://github.com/acme/docs/blob/main/guide.md' )
		);
	}

	/**
	 * A raw URL is already in the target form and passes through unchanged.
	 *
	 * @return void
	 */
	public function test_raw_url_passes_through_unchanged(): void {
		$url = 'https://raw.githubusercontent.com/acme/docs/main/guide.md';
		$this->assertSame( $url, GitSync::normalize_url( $url ) );
	}

	/**
	 * A URL that is neither a github.com blob nor a raw URL is left untouched;
	 * the host allowlist, not this method, decides whether it may be fetched.
	 *
	 * @return void
	 */
	public function test_non_github_url_passes_through(): void {
		$url = 'https://example.com/guide.md';
		$this->assertSame( $url, GitSync::normalize_url( $url ) );
	}

	/**
	 * The branch and the full file path after /blob/ are preserved.
	 *
	 * @return void
	 */
	public function test_branch_and_nested_path_are_preserved(): void {
		$this->assertSame(
			'https://raw.githubusercontent.com/acme/docs/main/docs/section/guide.md',
			GitSync::normalize_url( 'https://github.com/acme/docs/blob/main/docs/section/guide.md' )
		);
	}

	/**
	 * Surrounding whitespace is trimmed before matching.
	 *
	 * @return void
	 */
	public function test_surrounding_whitespace_is_trimmed(): void {
		$this->assertSame(
			'https://raw.githubusercontent.com/acme/docs/main/guide.md',
			GitSync::normalize_url( "  https://github.com/acme/docs/blob/main/guide.md \n" )
		);
	}

	/**
	 * An empty or whitespace-only URL yields an empty string.
	 *
	 * @return void
	 */
	public function test_empty_url_returns_empty(): void {
		$this->assertSame( '', GitSync::normalize_url( '' ) );
		$this->assertSame( '', GitSync::normalize_url( '   ' ) );
	}
}
