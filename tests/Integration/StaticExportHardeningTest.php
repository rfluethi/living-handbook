<?php
/**
 * The website export on the paths that only go wrong on somebody else's site.
 *
 * An edited template, a stylesheet that points at more than fonts and images,
 * a render that throws halfway, a request with parameters of the wrong kind:
 * none of them happens with the shipped template and the test theme, and each
 * of them used to leave something behind that the next page or the next
 * request paid for.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Export\StaticSite;
use LivingHandbook\Export\TemplateRender;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;
use ZipArchive;

/**
 * Export\TemplateRender and Export\StaticSite against unusual input.
 */
final class StaticExportHardeningTest extends WP_UnitTestCase {

	/**
	 * Files the tests produced.
	 *
	 * @var array<int, string>
	 */
	private array $files = array();

	/**
	 * A REST server, and a user who is allowed to export.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Remove what the tests produced.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->files = array();

		parent::tear_down();
	}

	/**
	 * A public handbook with one page in it.
	 *
	 * @return int Term id.
	 */
	private function handbook_with_a_page(): int {
		$term = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Company',
			)
		);
		update_term_meta( $term, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );

		$page = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'One',
				'post_content' => 'Text.',
			)
		);
		wp_set_object_terms( $page, array( $term ), Handbooks::TAXONOMY );

		return $term;
	}

	/**
	 * Save an edited single-handbook template for the current theme.
	 *
	 * @param string $content Block markup.
	 * @return void
	 */
	private function edit_template( string $content ): void {
		$edited = (int) self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_name'    => 'single-handbook',
				'post_title'   => 'Handbook page',
				'post_content' => $content,
			)
		);
		wp_set_object_terms( $edited, get_stylesheet(), 'wp_theme' );
	}

	/**
	 * One pass of the export.
	 *
	 * @param array<string, mixed> $params Request parameters.
	 * @return \WP_REST_Response
	 */
	private function pass( array $params ) {
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/static-export' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * Run an export to the end and open the archive.
	 *
	 * @param int    $term  Handbook.
	 * @param string $theme The look.
	 * @return ZipArchive
	 */
	private function export( int $term, string $theme = '' ): ZipArchive {
		$data  = (array) $this->pass(
			array(
				'handbook' => $term,
				'theme'    => $theme,
			)
		)->get_data();
		$guard = 20;
		while ( empty( $data['done'] ) && isset( $data['job'] ) && $guard-- > 0 ) {
			$data = (array) $this->pass( array( 'job' => $data['job'] ) )->get_data();
		}
		$this->assertTrue( (bool) ( $data['done'] ?? false ), 'The export never finished: ' . wp_json_encode( $data ) );

		$state         = get_transient( 'living_handbook_static_' . (string) $data['job'] );
		$path          = (string) $state['zip'];
		$this->files[] = $path;

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $path ) );

		return $zip;
	}

	/**
	 * A block that has to go takes nothing else with it.
	 *
	 * Up to 0.75.1 the blocks were cut with a pattern whose attribute part could
	 * run past the end of its own comment. A comments block with attributes, as
	 * the Site Editor writes it, then swallowed everything up to the next
	 * self-closing block with attributes: here the page title.
	 *
	 * @return void
	 */
	public function test_cutting_a_block_with_attributes_leaves_its_neighbours(): void {
		$this->edit_template(
			'<!-- wp:comments {"className":"wp-block-comments-query-loop"} --><div class="wp-block-comments wp-block-comments-query-loop">'
			. '<!-- wp:comments-title /--><!-- wp:post-comments-form /--></div><!-- /wp:comments -->'
			. '<!-- wp:paragraph --><p>Kept by hand.</p><!-- /wp:paragraph -->'
			. '<!-- wp:post-title {"level":2} /-->'
			. '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:living-handbook/feedback /--><!-- wp:post-content /--></div><!-- /wp:group -->'
		);

		$markup = TemplateRender::markup();

		$this->assertStringNotContainsString( 'wp:comments', $markup, 'The comments block goes, with everything inside it.' );
		$this->assertStringNotContainsString( 'wp:post-comments-form', $markup );
		$this->assertStringNotContainsString( 'living-handbook/feedback', $markup, 'A block to cut inside another block goes too.' );
		$this->assertStringContainsString( 'Kept by hand.', $markup );
		$this->assertStringContainsString( 'wp:post-title {"level":2}', $markup, 'The title after it stays.' );
		$this->assertStringContainsString( 'wp:post-content', $markup, 'And so does the group around the cut block.' );
	}
}
