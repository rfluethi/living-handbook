<?php
/**
 * Resolving internal .md links, and defusing the ones that resolve to nothing.
 *
 * Two promises are tested here. A link to a page that exists becomes that page's
 * permalink, including a link to a folder's README, whose page takes the folder
 * slug. A link to no page becomes plain text, never a raw .md link that would
 * 404 in the browser; the importer still reports it so the gap is visible.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\Postprocessor;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Postprocessor::convert_md_links.
 */
final class UnresolvedLinksTest extends WP_UnitTestCase {

	/**
	 * Create a handbook page.
	 *
	 * @param string $title   Page title.
	 * @param string $content Post content.
	 * @param string $slug    Optional slug.
	 * @return int Post ID.
	 */
	private function page( string $title, string $content = '', string $slug = '' ): int {
		$args = array(
			'post_type'    => Handbook::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $content,
		);
		if ( '' !== $slug ) {
			$args['post_name'] = $slug;
		}
		return (int) self::factory()->post->create( $args );
	}

	/**
	 * A link to a page that exists is rewritten to that page's permalink. This is
	 * the behaviour the GitHub sync has to repeat on every pull.
	 *
	 * @return void
	 */
	public function test_it_resolves_a_link_to_an_existing_page(): void {
		$target = $this->page( 'Understanding access', '', 'understanding-access' );
		$source = $this->page( 'A', 'See <a href="../access/understanding-access.md">Understanding access</a>.' );

		$result = Postprocessor::convert_md_links( $source );

		$content = (string) get_post( $source )->post_content;
		$this->assertStringNotContainsString( '.md', $content, 'The raw .md link must be gone.' );
		$this->assertStringContainsString( (string) get_permalink( $target ), $content );
		$this->assertSame( 1, $result['converted'] );
		$this->assertSame( array(), $result['unresolved'] );
	}

	/**
	 * A link to a folder's README resolves to that folder's page, whose slug is
	 * the folder name, not "readme". This is the case that broke after the README
	 * slug change, and the most common cross-link in a real handbook.
	 *
	 * @return void
	 */
	public function test_a_readme_link_resolves_to_the_folder_page(): void {
		$area   = $this->page( 'Content', '', 'content' );
		$source = $this->page( 'A', 'See <a href="../content/README.md">Content</a>.' );

		Postprocessor::convert_md_links( $source );

		$content = (string) get_post( $source )->post_content;
		$this->assertStringContainsString( (string) get_permalink( $area ), $content );
		$this->assertStringNotContainsString( '.md', $content );
	}

	/**
	 * A link to no page becomes plain text: the anchor is dropped, the text kept,
	 * and no raw .md link is left to 404. It is also reported.
	 *
	 * @return void
	 */
	public function test_a_dead_link_becomes_plain_text_and_is_reported(): void {
		$source = $this->page( 'The review cycle', 'See <a href="checking-pages.md">Checking pages</a>.' );

		$result  = Postprocessor::convert_md_links( $source );
		$content = (string) get_post( $source )->post_content;

		$this->assertStringNotContainsString( '<a', $content, 'The dead anchor must be gone.' );
		$this->assertStringNotContainsString( '.md', $content, 'No raw .md link may remain.' );
		$this->assertStringContainsString( 'Checking pages', $content, 'The link text stays.' );
		$this->assertCount( 1, $result['unresolved'] );
		$this->assertSame( 'The review cycle', $result['unresolved'][0]['source'] );
		$this->assertSame( 'checking-pages.md', $result['unresolved'][0]['target'] );
	}

	/**
	 * finalize_report aggregates the unresolved links across all pages.
	 *
	 * @return void
	 */
	public function test_finalize_report_aggregates_unresolved(): void {
		$a = $this->page( 'A', 'See <a href="gone.md">Gone</a>.' );
		$b = $this->page( 'B', 'Just prose.' );

		$report = Postprocessor::finalize_report( array( $a, $b ) );

		$this->assertCount( 1, $report['unresolved'] );
		$this->assertSame( 'gone.md', $report['unresolved'][0]['target'] );
	}

	/**
	 * A link to another host is left alone, even though its file name ends in
	 * .md. This is the shipped handbook's own case: it links to the developer
	 * docs on github.com, and every one of those links was stripped to plain
	 * text and reported as a dead link, on every fresh installation.
	 *
	 * @return void
	 */
	public function test_a_link_to_another_host_is_left_alone(): void {
		$url    = 'https://github.com/rfluethi/living-handbook/blob/main/docs/hooks.md';
		$source = $this->page( 'Understanding access', 'See the <a href="' . $url . '">developer documentation on the hooks</a>.' );

		$result  = Postprocessor::convert_md_links( $source );
		$content = (string) get_post( $source )->post_content;

		$this->assertStringContainsString( $url, $content, 'The external link must survive untouched.' );
		$this->assertSame( array(), $result['unresolved'], 'An external link is not a dead internal link.' );
		$this->assertSame( 0, $result['converted'], 'Nothing was converted: it was not ours to convert.' );
	}

	/**
	 * The same for a protocol-relative link, which names a host without naming a
	 * scheme. Rarer, and it would fall through a check that only looks for
	 * "http".
	 *
	 * @return void
	 */
	public function test_a_protocol_relative_link_is_left_alone(): void {
		$source = $this->page( 'A', 'See <a href="//example.org/docs/blocks.md">the blocks</a>.' );

		$result  = Postprocessor::convert_md_links( $source );
		$content = (string) get_post( $source )->post_content;

		$this->assertStringContainsString( '//example.org/docs/blocks.md', $content );
		$this->assertSame( array(), $result['unresolved'] );
	}

	/**
	 * And the rule cuts only where it should: a relative link that happens to sit
	 * next to an external one is still resolved. Without this, "leave external
	 * links alone" could quietly become "leave every link alone".
	 *
	 * @return void
	 */
	public function test_a_relative_link_beside_an_external_one_still_resolves(): void {
		$target = $this->page( 'Checking pages', '', 'checking-pages' );
		$source = $this->page(
			'The review cycle',
			'See <a href="https://example.org/docs/hooks.md">the hooks</a> and <a href="checking-pages.md">Checking pages</a>.'
		);

		$result  = Postprocessor::convert_md_links( $source );
		$content = (string) get_post( $source )->post_content;

		$this->assertStringContainsString( 'https://example.org/docs/hooks.md', $content );
		$this->assertStringContainsString( (string) get_permalink( $target ), $content );
		$this->assertSame( 1, $result['converted'] );
		$this->assertSame( array(), $result['unresolved'] );
	}

	/**
	 * A page with no links is left alone.
	 *
	 * @return void
	 */
	public function test_a_page_without_links_yields_nothing(): void {
		$id     = $this->page( 'A', 'Just prose, no links.' );
		$result = Postprocessor::convert_md_links( $id );

		$this->assertSame( 0, $result['converted'] );
		$this->assertSame( array(), $result['unresolved'] );
	}
}
