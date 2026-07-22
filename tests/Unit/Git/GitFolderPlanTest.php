<?php
/**
 * Turning a repository tree into a page hierarchy.
 *
 * The folder import reads a whole repository tree and has to decide, per path,
 * what becomes a page and what hangs under what. That decision is pure: no
 * network, no database. It is also the part of the import that is impossible to
 * check by reading it, because the interesting cases are the awkward ones, a
 * folder without an index file, a README at the top of the import, a level that
 * exists only as a path segment. These tests are those cases.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Git;

use LivingHandbook\Git\GitSync;
use PHPUnit\Framework\TestCase;

/**
 * The tree to hierarchy mapping.
 */
final class GitFolderPlanTest extends TestCase {

	/**
	 * Build tree entries the way the GitHub API returns them.
	 *
	 * @param array<int, string> $paths Blob paths.
	 * @return array<int, array<string, string>>
	 */
	private function tree( array $paths ): array {
		$entries = array();
		foreach ( $paths as $path ) {
			$entries[] = array(
				'path' => $path,
				'type' => 'blob',
			);
		}
		return $entries;
	}

	/**
	 * Only Markdown under the chosen folder, shallow first.
	 *
	 * @return void
	 */
	public function test_it_takes_only_markdown_under_the_chosen_folder(): void {
		$entries   = $this->tree(
			array(
				'README.md',
				'docs/guide.md',
				'docs/logo.png',
				'docs/deep/nested/page.md',
				'docs/sub/a.md',
				'other/elsewhere.md',
			)
		);
		$entries[] = array(
			'path' => 'docs/sub',
			'type' => 'tree',
		);

		$this->assertSame(
			array( 'docs/guide.md', 'docs/sub/a.md', 'docs/deep/nested/page.md' ),
			GitSync::markdown_under( $entries, 'docs' )
		);
	}

	/**
	 * A folder with an index.md is represented by that file, and its siblings
	 * hang under it.
	 *
	 * @return void
	 */
	public function test_a_folder_with_an_index_is_represented_by_it(): void {
		$plan = GitSync::plan_folder_import(
			array( 'docs/sub/index.md', 'docs/sub/a.md' ),
			'docs'
		);

		$this->assertSame(
			array( array( 'path' => 'docs/sub', 'index' => 'docs/sub/index.md' ) ),
			$plan['folders']
		);
		$this->assertSame(
			array( array( 'path' => 'docs/sub/a.md', 'folder' => 'docs/sub' ) ),
			$plan['files']
		);
	}

	/**
	 * index.md wins over README.md when a folder carries both.
	 *
	 * @return void
	 */
	public function test_index_wins_over_readme(): void {
		$plan = GitSync::plan_folder_import(
			array( 'docs/sub/README.md', 'docs/sub/index.md' ),
			'docs'
		);

		$this->assertSame( 'docs/sub/index.md', $plan['folders'][0]['index'] );
		// The README is not consumed, so it stays a page of its own rather than
		// disappearing.
		$this->assertSame(
			array( array( 'path' => 'docs/sub/README.md', 'folder' => 'docs/sub' ) ),
			$plan['files']
		);
	}

	/**
	 * A folder without an index file still becomes a level, so the navigation
	 * keeps the shape the repository has.
	 *
	 * @return void
	 */
	public function test_a_folder_without_an_index_still_becomes_a_level(): void {
		$plan = GitSync::plan_folder_import( array( 'docs/sub/a.md' ), 'docs' );

		$this->assertSame(
			array( array( 'path' => 'docs/sub', 'index' => '' ) ),
			$plan['folders']
		);
	}

	/**
	 * A level that exists only as a path segment gets a folder too, and the
	 * folders come shallow first so a parent always exists before its children.
	 *
	 * @return void
	 */
	public function test_intermediate_levels_are_filled_in_shallow_first(): void {
		$plan = GitSync::plan_folder_import( array( 'docs/one/two/three/deep.md' ), 'docs' );

		$this->assertSame(
			array( 'docs/one', 'docs/one/two', 'docs/one/two/three' ),
			array_column( $plan['folders'], 'path' )
		);
	}

	/**
	 * The README of the folder the import points at has no folder above it, so
	 * it must survive as an ordinary page. Losing it silently was the failure
	 * this arrangement invites.
	 *
	 * @return void
	 */
	public function test_the_readme_of_the_base_folder_is_kept(): void {
		$plan = GitSync::plan_folder_import(
			array( 'docs/README.md', 'docs/guide.md' ),
			'docs'
		);

		$this->assertSame( array(), $plan['folders'] );
		$this->assertSame(
			array(
				array( 'path' => 'docs/README.md', 'folder' => 'docs' ),
				array( 'path' => 'docs/guide.md', 'folder' => 'docs' ),
			),
			$plan['files']
		);
	}

	/**
	 * A flat folder produces no levels at all, which is the behaviour the import
	 * had before it learned to recurse.
	 *
	 * @return void
	 */
	public function test_a_flat_folder_stays_flat(): void {
		$plan = GitSync::plan_folder_import( array( 'docs/a.md', 'docs/b.md' ), 'docs' );

		$this->assertSame( array(), $plan['folders'] );
		$this->assertCount( 2, $plan['files'] );
	}
}
