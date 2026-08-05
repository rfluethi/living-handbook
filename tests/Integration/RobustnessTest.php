<?php
/**
 * Findings from the 0.52.0 review that survived the rounds that were meant to
 * close them, because each travelled under a heading about something else.
 *
 * They have nothing in common except that: a stylesheet field that lets a page
 * call a foreign host, a diagram that arrives without its text alternative, and
 * an e-mail address in a file that leaves the site. Each is pinned here so it
 * cannot come back unnoticed.
 *
 * A fourth, the missing depth limit in Navigation::branch(), turned out not to
 * be a finding at all: a page has one parent, so a cycle is a component with no
 * path from the root, and the walk starts at the root. The reasoning is written
 * into that method rather than guarded against here.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookExport;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Settings;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Hardening and robustness.
 */
final class RobustnessTest extends WP_UnitTestCase {

	/**
	 * A stylesheet that reaches out to a foreign host is stripped of exactly that.
	 *
	 * Only an administrator writes in this field, so this is not a hole another
	 * account can use. It is the accident: a snippet pasted from somewhere pulls
	 * a font or a stylesheet, and from then on every reader of every handbook
	 * page announces themselves to a third party. A handbook is the wrong place
	 * for that to happen unnoticed.
	 *
	 * @return void
	 */
	public function test_custom_css_cannot_call_a_foreign_host(): void {
		$settings = new Settings();

		$clean = $settings->sanitize_css(
			"@import url('https://fonts.example.com/all.css');\n"
			. ".living-handbook-nav { background: url(https://tracker.example.com/p.gif); }\n"
			. '.living-handbook-toc { background: url(/wp-content/uploads/own.png); }'
		);

		$this->assertStringNotContainsString( '@import', $clean );
		$this->assertStringNotContainsString( 'tracker.example.com', $clean );
		$this->assertStringContainsString( '/wp-content/uploads/own.png', $clean, 'A local reference is nobody else\'s business.' );
	}

	/**
	 * The site's own host and a data: URL stay, because neither leaves the site.
	 *
	 * @return void
	 */
	public function test_custom_css_keeps_what_stays_at_home(): void {
		$settings = new Settings();
		$own      = home_url( '/wp-content/uploads/logo.png' );

		$clean = $settings->sanitize_css(
			'.a { background: url(' . $own . '); }'
			. '.b { background: url(data:image/gif;base64,R0lGOD); }'
		);

		$this->assertStringContainsString( $own, $clean );
		$this->assertStringContainsString( 'data:image/gif', $clean );
	}

	/**
	 * The "<" stays gone, which is what kept the value inside its style element.
	 *
	 * @return void
	 */
	public function test_custom_css_cannot_close_its_style_element(): void {
		$settings = new Settings();

		$this->assertStringNotContainsString( '<', $settings->sanitize_css( '.a{}</style><script>alert(1)</script>' ) );
	}

	/**
	 * A diagram brings its text alternative along.
	 *
	 * Mermaid has accTitle and accDescr for exactly this. Without them the
	 * accessible label of the rendered diagram falls back to the diagram source,
	 * so a screen reader reads out "graph TD; A-->B" instead of what the picture
	 * says.
	 *
	 * @return void
	 */
	public function test_a_mermaid_diagram_keeps_its_text_alternative(): void {
		$sync   = new \LivingHandbook\Git\GitSync();
		$method = new \ReflectionMethod( $sync, 'mermaid_to_html' );
		$method->setAccessible( true );

		$html = (string) $method->invoke(
			$sync,
			"<pre><code class=\"language-mermaid\">graph TD;\n  accTitle: Weg einer Anfrage\n  accDescr: Von der Anfrage bis zur Entscheidung\n  A--&gt;B;\n</code></pre>"
		);

		$this->assertStringContainsString( 'data-title="Weg einer Anfrage"', $html );
		$this->assertStringContainsString( 'data-description="Von der Anfrage bis zur Entscheidung"', $html );
		$this->assertStringContainsString( 'class="mermaid"', $html );
	}

	/**
	 * A diagram without the directives is still converted, just without the
	 * attributes: the feature must not make the plain case worse.
	 *
	 * @return void
	 */
	public function test_a_mermaid_diagram_without_directives_is_unchanged(): void {
		$sync   = new \LivingHandbook\Git\GitSync();
		$method = new \ReflectionMethod( $sync, 'mermaid_to_html' );
		$method->setAccessible( true );

		$html = (string) $method->invoke( $sync, '<pre><code class="language-mermaid">graph TD; A--&gt;B;</code></pre>' );

		$this->assertSame( '<pre class="mermaid">graph TD; A--&gt;B;</pre>', $html );
	}

	/**
	 * An anonymous vote is not free: it is a database write per request, with no
	 * dedup by design, so a script can turn the counter into noise and the
	 * postmeta table into a write log. The ceiling stops that and says so in the
	 * status code, without pretending to be a one-vote-per-person rule.
	 *
	 * @return void
	 */
	public function test_anonymous_feedback_stops_at_the_ceiling(): void {
		update_option( Settings::OPTION_PUBLIC_FEEDBACK, 1 );
		wp_set_current_user( 0 );

		$post_id = $this->public_page();

		// A low ceiling, so the test states the rule rather than the number.
		add_filter( 'living_handbook_anonymous_feedback_limit', static fn(): int => 3 );

		$accepted = 0;
		for ( $i = 0; $i < 5; $i++ ) {
			$response = $this->vote( $post_id );
			if ( 200 === $response->get_status() ) {
				++$accepted;
			}
		}

		$this->assertSame( 3, $accepted, 'The ceiling did not hold.' );
		$this->assertSame( 3, (int) get_post_meta( $post_id, Feedback::YES, true ), 'A refused vote must not be counted.' );
		$this->assertSame( 429, $this->vote( $post_id )->get_status() );
	}

	/**
	 * The ceiling counts per page, so a busy page cannot silence a quiet one.
	 *
	 * @return void
	 */
	public function test_the_ceiling_is_per_page(): void {
		update_option( Settings::OPTION_PUBLIC_FEEDBACK, 1 );
		wp_set_current_user( 0 );
		add_filter( 'living_handbook_anonymous_feedback_limit', static fn(): int => 1 );

		$busy  = $this->public_page();
		$quiet = $this->public_page();

		$this->vote( $busy );
		$this->assertSame( 429, $this->vote( $busy )->get_status() );
		$this->assertSame( 200, $this->vote( $quiet )->get_status() );
	}

	/**
	 * A signed-in vote is unaffected: it has its own rule, one per user and page.
	 *
	 * @return void
	 */
	public function test_a_signed_in_vote_ignores_the_anonymous_ceiling(): void {
		add_filter( 'living_handbook_anonymous_feedback_limit', static fn(): int => 1 );
		$post_id = $this->public_page();

		foreach ( array( 'editor', 'author', 'subscriber' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
			$this->assertSame( 200, $this->vote( $post_id )->get_status(), $role );
		}

		$this->assertSame( 3, (int) get_post_meta( $post_id, Feedback::YES, true ) );
	}

	/**
	 * A published page in a public handbook, so the endpoint's own access check
	 * lets the vote through and the test is about the ceiling, not about access.
	 *
	 * @return int
	 */
	private function public_page(): int {
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( (int) $term->term_id, Handbooks::META_VISIBILITY, 'public' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $post_id, array( (int) $term->term_id ), Handbooks::TAXONOMY );

		return $post_id;
	}

	/**
	 * Send one "yes" vote through the endpoint.
	 *
	 * @param int $post_id Page to vote on.
	 * @return \WP_REST_Response
	 */
	private function vote( int $post_id ) {
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/feedback' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'value', 'yes' );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * An export bundle carries the login, not the e-mail address.
	 *
	 * The bundle is a file that leaves the site. Both identifiers serve the same
	 * purpose, re-attaching the reviewer on the target site, and one of them is
	 * more personal than the other.
	 *
	 * @return void
	 */
	public function test_an_export_bundle_does_not_carry_an_email_address(): void {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'pruefstelle',
				'user_email' => 'geheim@example.com',
			)
		);

		$export = new HandbookExport();
		$method = new \ReflectionMethod( $export, 'user_identifier' );
		$method->setAccessible( true );

		$this->assertSame( 'pruefstelle', $method->invoke( $export, $user_id ) );
		$this->assertSame( '', $method->invoke( $export, 0 ) );
	}

	/**
	 * The import no longer decides about comments behind the site's back.
	 *
	 * It used to write comment_status => closed on every imported page, on top of
	 * the site default, so opening comments on a handbook needed an edit per
	 * page and the setting looked broken.
	 *
	 * @return void
	 */
	public function test_the_import_leaves_the_comment_default_to_the_site(): void {
		$sources = array( 'src/Import/HandbookImport.php', 'src/Import/MarkdownImportPage.php' );

		foreach ( $sources as $source ) {
			$code = (string) file_get_contents( LIVING_HANDBOOK_DIR . $source );
			$this->assertStringNotContainsString( "'comment_status'", $code, $source );
		}

		$this->assertContains( 'comments', (array) get_all_post_type_supports( Handbook::POST_TYPE ) ? array_keys( get_all_post_type_supports( Handbook::POST_TYPE ) ) : array() );
	}
}
