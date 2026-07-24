<?php
/**
 * Reporting internal .md links that resolve to no page.
 *
 * After the import rewrites every link whose target exists, anything still
 * pointing at a .md file is dead: a typo, or a page not in the import. This is
 * the check that turns those into a list the importer can hand back, so they are
 * covered here rather than found by clicking.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\Postprocessor;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Postprocessor::unresolved_md_links.
 */
final class UnresolvedLinksTest extends WP_UnitTestCase {

	/**
	 * Create a handbook page with the given content.
	 *
	 * @param string $title   Page title.
	 * @param string $content Post content.
	 * @return int Post ID.
	 */
	private function page( string $title, string $content ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * A link still pointing at a .md file is reported, with the page it is on and
	 * the file it points at.
	 *
	 * @return void
	 */
	public function test_it_reports_a_dead_md_link(): void {
		$id = $this->page( 'The review cycle', 'See <a href="checking-pages.md">Checking pages</a>.' );

		$unresolved = Postprocessor::unresolved_md_links( array( $id ) );

		$this->assertCount( 1, $unresolved );
		$this->assertSame( 'The review cycle', $unresolved[0]['source'] );
		$this->assertSame( 'checking-pages.md', $unresolved[0]['target'] );
	}

	/**
	 * Only the file name is reported, not the path in the link, because the
	 * import resolves by file name.
	 *
	 * @return void
	 */
	public function test_it_reduces_a_path_to_the_file_name(): void {
		$id = $this->page( 'A', 'See <a href="../access/understanding-access.md">it</a>.' );

		$unresolved = Postprocessor::unresolved_md_links( array( $id ) );

		$this->assertSame( 'understanding-access.md', $unresolved[0]['target'] );
	}

	/**
	 * A resolved link (already a real URL) is not a .md link, so it is not
	 * reported. This is the counter-check: the method must not flag good links.
	 *
	 * @return void
	 */
	public function test_it_ignores_a_resolved_link(): void {
		$id = $this->page( 'A', 'See <a href="https://example.com/handbook/checking/">Checking</a>.' );

		$this->assertSame( array(), Postprocessor::unresolved_md_links( array( $id ) ) );
	}

	/**
	 * A page with no links at all yields nothing.
	 *
	 * @return void
	 */
	public function test_a_page_without_links_yields_nothing(): void {
		$id = $this->page( 'A', 'Just prose, no links.' );

		$this->assertSame( array(), Postprocessor::unresolved_md_links( array( $id ) ) );
	}

	/**
	 * A link to a page that exists is rewritten to that page's permalink. This is
	 * the behaviour the GitHub sync has to repeat on every pull; without it, the
	 * first scheduled sync after an import turned every cross-link into a 404.
	 *
	 * @return void
	 */
	public function test_it_resolves_a_link_to_an_existing_page(): void {
		$target = (int) self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Understanding access',
				'post_name'   => 'understanding-access',
			)
		);
		$source = $this->page( 'A', 'See <a href="../access/understanding-access.md">Understanding access</a>.' );

		Postprocessor::convert_md_links( $source );

		$content = (string) get_post( $source )->post_content;
		$this->assertStringNotContainsString( '.md', $content, 'The raw .md link must be gone.' );
		$this->assertStringContainsString( (string) get_permalink( $target ), $content );
		$this->assertSame( array(), Postprocessor::unresolved_md_links( array( $source ) ) );
	}
}
