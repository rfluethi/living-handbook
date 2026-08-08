<?php
/**
 * A search result quotes the sentence it was found in.
 *
 * Eight results whose titles all begin with the same word are eight guesses.
 * The point of the snippet is that the sentence decides, so these tests hold
 * what the snippet must be: text from the page, around the hit, with the hit
 * marked, and nothing at all when the words are only in the title.
 *
 * The shape is segments, not markup. That is the part worth guarding: markup
 * built from page content would have to be escaped correctly at every step,
 * and segments have nothing to escape.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Filters;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Filters::rest_search and its snippet.
 */
final class SearchSnippetTest extends WP_UnitTestCase {

	/**
	 * The handbook every page in a test belongs to.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * A public handbook, so the access check passes for a logged-out visitor.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		do_action( 'rest_api_init' );

		$this->term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $this->term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
	}

	/**
	 * A published page in that handbook.
	 *
	 * @param string $title   Page title.
	 * @param string $content Page content.
	 * @return int Post id.
	 */
	private function page( string $title, string $content ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
		wp_set_object_terms( $id, array( $this->term_id ), Handbooks::TAXONOMY );

		return $id;
	}

	/**
	 * The endpoint's results for a search.
	 *
	 * @param string $search Search words.
	 * @return array<int, array<string, mixed>>
	 */
	private function results( string $search ): array {
		$request = new WP_REST_Request( 'GET', '/' . Filters::REST_NAMESPACE . Filters::REST_ROUTE_SEARCH );
		$request->set_param( 'term_id', $this->term_id );
		$request->set_param( 'q', $search );

		$data = rest_do_request( $request )->get_data();

		return is_array( $data ) && isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();
	}

	/**
	 * The snippet of the first result, as one string.
	 *
	 * @param array<int, array<string, mixed>> $results Results.
	 * @return string
	 */
	private function text( array $results ): string {
		$out = '';
		foreach ( $results[0]['snippet'] as $part ) {
			$out .= $part['text'];
		}
		return $out;
	}

	/**
	 * The words are quoted from the page's own text, and marked.
	 *
	 * @return void
	 */
	public function test_the_hit_is_quoted_and_marked(): void {
		$this->page( 'Access', 'Before that sentence. Only the review team may publish a page. After that sentence.' );

		$results = $this->results( 'review team' );

		$this->assertCount( 1, $results );
		$this->assertStringContainsString( 'review team may publish', $this->text( $results ) );

		$marked = array();
		foreach ( $results[0]['snippet'] as $part ) {
			if ( $part['mark'] ) {
				$marked[] = $part['text'];
			}
		}
		$this->assertSame( array( 'review team' ), $marked );
	}

	/**
	 * A title-only match carries no snippet: there is nothing to quote, and an
	 * arbitrary first sentence would say nothing about why the page matched.
	 *
	 * @return void
	 */
	public function test_a_title_only_match_has_no_snippet(): void {
		$this->page( 'Onboarding', 'This page is about something else entirely.' );

		$results = $this->results( 'Onboarding' );

		$this->assertCount( 1, $results );
		$this->assertSame( array(), $results[0]['snippet'] );
	}

	/**
	 * Block markup, shortcodes and tags do not reach the snippet.
	 *
	 * @return void
	 */
	public function test_the_snippet_is_plain_text(): void {
		$this->page(
			'Formatting',
			"<!-- wp:paragraph -->\n<p>The <strong>escalation</strong> path is here.</p>\n<!-- /wp:paragraph -->"
		);

		$text = $this->text( $this->results( 'escalation' ) );

		$this->assertStringContainsString( 'escalation path is here', $text );
		$this->assertStringNotContainsString( '<', $text );
		$this->assertStringNotContainsString( 'wp:paragraph', $text );
	}

	/**
	 * The page's own spelling is quoted, not the visitor's.
	 *
	 * @return void
	 */
	public function test_the_page_spelling_is_kept(): void {
		$this->page( 'Casing', 'The Escalation path is here.' );

		$results = $this->results( 'escalation' );

		foreach ( $results[0]['snippet'] as $part ) {
			if ( $part['mark'] ) {
				$this->assertSame( 'Escalation', $part['text'] );
			}
		}
	}

	/**
	 * A long page is cut around the hit rather than sent whole, and the cut is
	 * marked with an ellipsis.
	 *
	 * @return void
	 */
	public function test_a_long_page_is_cut_around_the_hit(): void {
		$this->page( 'Long', str_repeat( 'padding word ', 200 ) . 'the needle is here ' . str_repeat( 'more padding ', 200 ) );

		$text = $this->text( $this->results( 'needle' ) );

		$this->assertStringContainsString( 'needle', $text );
		$this->assertStringContainsString( '…', $text );
		$this->assertLessThan( 300, mb_strlen( $text ) );
	}

	/**
	 * A page nobody may read contributes nothing, snippet included. The snippet
	 * reads the post content, so it must never run for a page that did not pass
	 * the access check.
	 *
	 * @return void
	 */
	public function test_a_page_behind_the_access_check_returns_nothing(): void {
		update_term_meta( $this->term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
		$this->page( 'Internal', 'The secret escalation path is here.' );

		$this->assertSame( array(), $this->results( 'escalation' ) );
	}
}
