<?php
/**
 * A handbook leaves as a website that works with no server behind it.
 *
 * The bundle export moves a handbook to another WordPress. This one is for the
 * reader who has none, and that raises two questions worth a test each.
 *
 * The first is access. A static copy carries pages out of every rule this site
 * has, so what goes in has to be decided against the person who pressed the
 * button, not against a logged-out visitor and not against nobody at all. The
 * route refuses a handbook its caller may not read, and the pages inside are the
 * pages that caller may read.
 *
 * The second is whether the result actually works when it is unpacked. That is
 * a question about paths: every link relative, every image copied along, and a
 * search that does not ask a server. Those are the tests that would fail
 * silently in the browser of somebody who has long since left the building.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;
use ZipArchive;

/**
 * Export\StaticSite and Export\SiteRenderer through the REST route that drives them.
 */
final class StaticExportTest extends WP_UnitTestCase {

	/**
	 * Files created during a test, removed afterwards.
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

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * Remove the archives the tests produced.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			if ( is_readable( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->files = array();

		parent::tear_down();
	}

	/**
	 * A handbook.
	 *
	 * @param string $name       Name.
	 * @param string $visibility Visibility constant.
	 * @return int Term id.
	 */
	private function handbook( string $name, string $visibility = Handbooks::VISIBILITY_PUBLIC ): int {
		$id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => $name,
			)
		);
		update_term_meta( $id, Handbooks::META_VISIBILITY, $visibility );

		return $id;
	}

	/**
	 * A published page in a handbook.
	 *
	 * @param int    $term_id Handbook.
	 * @param string $title   Title.
	 * @param string $content Content.
	 * @param int    $parent  Parent page id.
	 * @return int Post id.
	 */
	private function page( int $term_id, string $title, string $content = 'Text.', int $parent = 0 ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
				'post_parent'  => $parent,
			)
		);
		wp_set_object_terms( $id, array( $term_id ), Handbooks::TAXONOMY );

		return $id;
	}

	/**
	 * One pass of the export.
	 *
	 * @param array<string, mixed> $params Request parameters.
	 * @return array<string, mixed> The response data.
	 */
	private function pass( array $params ): array {
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/static-export' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = rest_do_request( $request );

		return (array) $response->get_data();
	}

	/**
	 * Run an export to the end and return the archive.
	 *
	 * @param int    $term_id Handbook.
	 * @param int    $area    Area page id, 0 for the whole handbook.
	 * @param string $theme   The look, empty for the default.
	 * @return ZipArchive
	 */
	private function export( int $term_id, int $area = 0, string $theme = '' ): ZipArchive {
		$data  = $this->pass(
			array(
				'handbook' => $term_id,
				'area'     => $area,
				'theme'    => $theme,
			)
		);
		$guard = 50;
		while ( empty( $data['done'] ) && $guard-- > 0 ) {
			$this->assertArrayHasKey( 'job', $data, 'A pass that is not done has to say how to continue: ' . wp_json_encode( $data ) );
			$data = $this->pass( array( 'job' => $data['job'] ) );
		}

		$this->assertTrue( (bool) ( $data['done'] ?? false ), 'The export never finished: ' . wp_json_encode( $data ) );

		$state = get_transient( 'living_handbook_static_' . (string) $data['job'] );
		$this->assertIsArray( $state );
		$path = (string) $state['zip'];
		$this->files[] = $path;

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( $path ), 'The archive could not be opened.' );

		return $zip;
	}

	/**
	 * The archive holds a file per page, at the path its place in the hierarchy
	 * says, plus the files that make it a website.
	 *
	 * @return void
	 */
	public function test_the_export_is_a_website(): void {
		$term   = $this->handbook( 'Company' );
		$parent = $this->page( $term, 'Upkeep' );
		$this->page( $term, 'Review pages', 'How to review.', $parent );

		$zip = $this->export( $term );

		$this->assertNotFalse( $zip->locateName( 'index.html' ) );
		$this->assertNotFalse( $zip->locateName( 'assets/style.css' ) );
		$this->assertNotFalse( $zip->locateName( 'assets/site.js' ) );
		$this->assertNotFalse( $zip->locateName( 'assets/search-index.js' ) );
		$this->assertNotFalse( $zip->locateName( 'README.txt' ) );
		$this->assertNotFalse( $zip->locateName( 'upkeep.html' ) );
		$this->assertNotFalse( $zip->locateName( 'upkeep/review-pages.html' ), 'A subpage sits under its parent.' );

		$start = (string) $zip->getFromName( 'index.html' );
		$this->assertStringContainsString( 'Review pages', $start, 'The start page lists the whole handbook.' );
	}

	/**
	 * Every path inside a page is relative, so the folder works wherever it is
	 * unpacked and whatever it is renamed to. A page two levels down reaches the
	 * stylesheet with "../".
	 *
	 * @return void
	 */
	public function test_the_paths_are_relative(): void {
		$term   = $this->handbook( 'Company' );
		$parent = $this->page( $term, 'Upkeep' );
		$this->page( $term, 'Review pages', 'How to review.', $parent );

		$zip  = $this->export( $term );
		$deep = (string) $zip->getFromName( 'upkeep/review-pages.html' );

		$this->assertStringContainsString( 'href="../assets/style.css"', $deep );
		$this->assertStringContainsString( 'href="../index.html"', $deep );
		// The page list on a page one level down has to climb out of its folder to
		// reach the pages above it, which is the case a link between siblings does
		// not exercise.
		$this->assertStringContainsString( 'href="../upkeep.html"', $deep );
		$this->assertStringContainsString( 'href="review-pages.html"', $deep, 'And a page in the same folder is named without a detour.' );
		$this->assertStringNotContainsString( 'href="http://example.org/', $deep, 'Nothing may point back at the site it came from.' );
	}

	/**
	 * A link from one handbook page to another becomes a link to the file next to
	 * it. Without this the export is a folder of pages that cannot reach each
	 * other, which is the one thing a handbook may not be.
	 *
	 * @return void
	 */
	public function test_links_between_pages_point_into_the_export(): void {
		$term   = $this->handbook( 'Company' );
		$target = $this->page( $term, 'Review pages' );
		$link   = '<a href="' . get_permalink( $target ) . '">the review</a>';
		$this->page( $term, 'Start here', 'See ' . $link );

		$zip  = $this->export( $term );
		$html = (string) $zip->getFromName( 'start-here.html' );

		$this->assertStringContainsString( 'href="review-pages.html"', $html );
	}

	/**
	 * An image travels with the export and is pointed at inside the folder. This
	 * is the difference between a website and a folder of pages with broken
	 * pictures on a laptop that is offline.
	 *
	 * @return void
	 */
	public function test_images_travel_with_the_export(): void {
		$uploads = wp_upload_dir();
		$this->assertFalse( $uploads['error'] );

		$relative = 'lh-test/picture.png';
		$file     = $uploads['basedir'] . '/' . $relative;
		wp_mkdir_p( dirname( $file ) );
		// A one-pixel PNG, the smallest thing that is really an image file.
		file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- writing a fixture.
		$this->files[] = $file;

		$url  = $uploads['baseurl'] . '/' . $relative;
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'With a picture', '<p><img src="' . $url . '" alt="A dot"></p>' );

		$zip  = $this->export( $term );
		$html = (string) $zip->getFromName( 'with-a-picture.html' );

		$this->assertNotFalse( $zip->locateName( 'assets/media/' . $relative ), 'The file itself is in the archive.' );
		$this->assertStringContainsString( 'src="assets/media/' . $relative . '"', $html );
		$this->assertStringNotContainsString( $uploads['baseurl'], $html, 'Nothing still points at the site the export came from.' );
	}

	/**
	 * A diagram is drawn in the export, which means the library that draws it
	 * travels along. It is 3.5 MB, so it travels only when there is something to
	 * draw, and the pages that carry no diagram do not load it either.
	 *
	 * @return void
	 */
	public function test_a_diagram_brings_the_library_with_it(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'Plain page', 'Nothing to draw here.' );
		$this->page(
			$term,
			'With a diagram',
			'<!-- wp:living-handbook/mermaid {"code":"graph TD; A--\u003eB;"} /-->'
		);

		$zip = $this->export( $term );

		$this->assertNotFalse( $zip->locateName( 'assets/mermaid.js' ) );
		$this->assertNotFalse( $zip->locateName( 'assets/mermaid-view.js' ) );

		$with = (string) $zip->getFromName( 'with-a-diagram.html' );
		$this->assertStringContainsString( 'class="mermaid"', $with, 'The diagram source is in the file, ready to be drawn.' );
		$this->assertStringContainsString( 'assets/mermaid.js', $with );

		$plain = (string) $zip->getFromName( 'plain-page.html' );
		$this->assertStringNotContainsString( 'assets/mermaid.js', $plain, 'A page without a diagram does not load 3.5 MB to draw nothing.' );
	}

	/**
	 * An export with no diagram in it does not carry the library at all.
	 *
	 * @return void
	 */
	public function test_an_export_without_diagrams_stays_small(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'Plain page', 'Nothing to draw here.' );

		$zip = $this->export( $term );

		$this->assertFalse( $zip->locateName( 'assets/mermaid.js' ) );
	}

	/**
	 * The images and diagrams enlarge on a click, which the plugin's own frontend
	 * script does. The export ships it and gives the page the class and the
	 * labels that script looks for, rather than carrying a second copy of the
	 * same behaviour that would drift from the first.
	 *
	 * @return void
	 */
	public function test_the_export_ships_the_script_that_enlarges_images(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );

		$zip  = $this->export( $term );
		$html = (string) $zip->getFromName( 'one.html' );

		$this->assertNotFalse( $zip->locateName( 'assets/frontend.js' ) );
		$this->assertStringContainsString( 'assets/frontend.js', $html );
		$this->assertStringContainsString( 'living-handbook-page', $html, 'The class the script scopes itself to.' );
		$this->assertStringContainsString( 'lightboxClose', $html, 'And the labels it reads for the overlay.' );
		$this->assertStringNotContainsString( '"rest"', $html, 'But no REST configuration: there is no server to ask.' );
	}

	/**
	 * The look is chosen at export time, and it reaches the stylesheet.
	 *
	 * @return void
	 */
	public function test_the_chosen_look_is_in_the_stylesheet(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );

		$zip   = $this->export( $term, 0, 'dark' );
		$style = (string) $zip->getFromName( 'assets/style.css' );

		$this->assertStringContainsString( '--lh-user-surface: #16191d', $style );
		$this->assertStringContainsString( 'color-scheme: dark', $style );
		$this->assertStringContainsString( '@media print', $style, 'Every look gets the print rules.' );
	}

	/**
	 * A look nobody has falls back to the default rather than to no styling at
	 * all, and the default is the one that follows the site's own settings.
	 *
	 * @return void
	 */
	public function test_an_unknown_look_falls_back_to_the_site(): void {
		update_option( 'living_handbook_colors', array( 'surface' => '#fafafa' ) );

		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );

		$zip   = $this->export( $term, 0, 'no-such-look' );
		$style = (string) $zip->getFromName( 'assets/style.css' );

		$this->assertStringContainsString( '--lh-user-surface:#fafafa', $style, 'The site colours, which is what "like this site" means.' );
		$this->assertStringNotContainsString( '#16191d', $style );

		delete_option( 'living_handbook_colors' );
	}

	/**
	 * The page comes out of the block template, not out of a layout decided here.
	 *
	 * This is the whole point of the second attempt at the export: a site that
	 * moved the navigation, the badges or the metadata footer in the Site Editor
	 * had those decisions ignored, because the export built its own page. Now it
	 * renders the same template the front end renders, so the blocks are where
	 * the site put them.
	 *
	 * @return void
	 */
	public function test_the_page_is_rendered_from_the_block_template(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One', 'Text.' );

		$zip  = $this->export( $term );
		$html = (string) $zip->getFromName( 'one.html' );

		$this->assertStringContainsString( 'wp-block-post-title', $html, 'The title comes from the core block, as on the site.' );
		$this->assertStringContainsString( 'living-handbook-nav', $html, 'And the handbook navigation the template places beside it.' );
		$this->assertStringContainsString( 'wp-block-columns', $html, 'In the columns the template arranges them in.' );
		$this->assertStringContainsString( 'living-handbook-meta', $html, 'Down to the metadata footer.' );
	}

	/**
	 * And when the template was edited, the edited one is what gets rendered.
	 * Anything else would mean the export shows a page nobody has.
	 *
	 * @return void
	 */
	public function test_an_edited_template_wins(): void {
		$edited = (int) self::factory()->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_name'    => 'single-handbook',
				'post_title'   => 'Handbook page',
				'post_content' => '<!-- wp:paragraph --><p>Rearranged by hand.</p><!-- /wp:paragraph --><!-- wp:post-title /-->',
			)
		);
		wp_set_object_terms( $edited, get_stylesheet(), 'wp_theme' );

		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One', 'Text.' );

		$zip  = $this->export( $term );
		$html = (string) $zip->getFromName( 'one.html' );

		$this->assertStringContainsString( 'Rearranged by hand.', $html );
	}

	/**
	 * What cannot work in a file is cut from the template before rendering,
	 * rather than rendered and then hidden: a feedback prompt whose buttons do
	 * nothing, a comment form with nowhere to post, a typeahead search that asks
	 * a server. The theme's header and footer go too, because their menus lead
	 * back to a site the reader may not be able to open.
	 *
	 * @return void
	 */
	public function test_what_needs_a_server_is_not_in_the_export(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One', 'Text.' );

		$zip  = $this->export( $term );
		$html = (string) $zip->getFromName( 'one.html' );

		$this->assertStringNotContainsString( 'living-handbook-feedback', $html );
		$this->assertStringNotContainsString( 'living-handbook-page-search', $html );
		$this->assertStringNotContainsString( 'wp-block-comments', $html );
		$this->assertStringNotContainsString( 'wp-block-template-part', $html );
	}

	/**
	 * The same against a theme that actually has a header and a footer.
	 *
	 * This test exists because the first version of the cut did nothing at all
	 * and nobody noticed: core blocks are serialised without their namespace, so
	 * the markup says `wp:template-part`, not `wp:core/template-part`, and a
	 * filter that matches nothing removes nothing, silently. Against the empty
	 * test theme the result looked identical either way. Against a real theme
	 * the export carried the site's header, its menu and its footer, and its own
	 * page tree beside the template's, which is what Rico saw and I did not.
	 *
	 * @return void
	 */
	public function test_a_real_theme_does_not_leak_its_frame_into_the_export(): void {
		if ( ! wp_get_theme( 'twentytwentyfive' )->exists() ) {
			$this->markTestSkipped( 'Twenty Twenty-Five is not installed here.' );
		}
		switch_theme( 'twentytwentyfive' );

		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One', 'Text.' );

		$zip   = $this->export( $term );
		$html  = (string) $zip->getFromName( 'one.html' );
		$style = (string) $zip->getFromName( 'assets/style.css' );

		$this->assertStringNotContainsString( 'wp-block-template-part', $html, "The theme's header and footer stay behind." );
		$this->assertStringNotContainsString( 'wp-block-site-title', $html );
		$this->assertStringNotContainsString( 'id="lh-nav"', $html, 'And the export does not add a second page tree beside the template\'s.' );
		$this->assertSame( 1, substr_count( $html, 'living-handbook-nav ' ), 'Exactly one navigation, the one the template places.' );

		$this->assertStringContainsString( '--wp--preset--font-family--', $style, "The theme's fonts are in the stylesheet…" );
		$this->assertSame( 1, preg_match( '#body\.lh-body \{ margin: 0; \}#', $style ), '…and the export sets nothing on body that would paint over them.' );
	}

	/**
	 * The people are left out of the metadata footer, avatars above all: that is
	 * a request to an external service from a file that may be read offline, in
	 * a mail attachment, or somewhere the names have no business being.
	 *
	 * @return void
	 */
	public function test_no_names_and_no_avatars_travel(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One', 'Text.' );

		$zip  = $this->export( $term );
		$html = (string) $zip->getFromName( 'one.html' );

		$this->assertStringNotContainsString( 'gravatar.com', $html );
		$this->assertStringNotContainsString( 'living-handbook-person', $html );
		$this->assertStringContainsString( 'living-handbook-meta', $html, 'The dates and the responsible role stay.' );
	}

	/**
	 * The stylesheet carries what makes the export look like the site: the core
	 * block styles and the theme's own global styles, which is where its palette,
	 * its fonts and its spacing live.
	 *
	 * @return void
	 */
	public function test_the_stylesheet_carries_the_theme(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );

		$zip   = $this->export( $term );
		$style = (string) $zip->getFromName( 'assets/style.css' );

		// A declaration, not a reference: the core stylesheet is full of
		// var(--wp--preset--color--…) and would satisfy a laxer check while the
		// palette itself was missing.
		$this->assertSame( 1, preg_match( '#--wp--preset--color--[a-z-]+:\s*#i', $style ), "The theme's palette, as the properties everything else falls back to." );
		$this->assertStringContainsString( 'wp-block-columns', $style, 'And the core block styles, or a columns block is two stacked divs.' );
	}

	/**
	 * A file the stylesheet points at travels with the export and is pointed at
	 * inside the folder. Fonts above all: a @font-face left pointing at the
	 * server turns into a fallback font on the reader's machine, silently.
	 *
	 * @return void
	 */
	public function test_files_a_stylesheet_points_at_travel_too(): void {
		$uploads  = wp_upload_dir();
		$relative = 'lh-test/font.woff2';
		$file     = $uploads['basedir'] . '/' . $relative;
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, 'not really a font' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- writing a fixture.
		$this->files[] = $file;

		$url  = $uploads['baseurl'] . '/' . $relative;
		$look = static function ( array $themes ) use ( $url ): array {
			$themes['fonted'] = array(
				'label' => 'With a font',
				'css'   => '@font-face { font-family: "Test"; src: url(' . $url . ') format("woff2"); }',
			);
			return $themes;
		};
		add_filter( 'living_handbook_static_export_themes', $look );

		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );

		$zip   = $this->export( $term, 0, 'fonted' );
		$style = (string) $zip->getFromName( 'assets/style.css' );

		remove_filter( 'living_handbook_static_export_themes', $look );

		$this->assertStringNotContainsString( $url, $style, 'No address that only works on the server it came from.' );
		$this->assertSame( 1, preg_match( '#url\("(site/[^"]*font\.woff2)"\)#', $style, $found ), 'The rule points at a path inside the export.' );
		// And that path is a file in the archive: the stylesheet and the archive
		// have to agree, or the font is quietly missing on the reader's machine.
		$this->assertNotFalse( $zip->locateName( 'assets/' . $found[1] ), 'The file the stylesheet names is in the archive.' );
	}

	/**
	 * A handbook the exporting user may not read is refused, rather than
	 * exported empty or exported anyway.
	 *
	 * @return void
	 */
	public function test_a_handbook_you_cannot_read_is_refused(): void {
		$term = $this->handbook( 'Board', Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $term, Handbooks::META_ROLES, array( 'administrator' ) );
		$this->page( $term, 'Minutes' );

		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/static-export' );
		$request->set_param( 'handbook', $term );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * The export asks per page, not once per handbook. A site that narrows access
	 * further through the filter gets that decision honoured here too, instead of
	 * a static copy that quietly carries the page it just hid.
	 *
	 * @return void
	 */
	public function test_a_page_the_filter_hides_stays_out(): void {
		$term   = $this->handbook( 'Company' );
		$this->page( $term, 'Open page' );
		$closed = $this->page( $term, 'Closed page' );

		$hide = static function ( bool $allowed, int $post_id ) use ( $closed ): bool {
			return $post_id === $closed ? false : $allowed;
		};
		add_filter( 'living_handbook_can_view_post', $hide, 10, 2 );

		$zip = $this->export( $term );

		remove_filter( 'living_handbook_can_view_post', $hide, 10 );

		$this->assertNotFalse( $zip->locateName( 'open-page.html' ) );
		$this->assertFalse( $zip->locateName( 'closed-page.html' ) );
		$this->assertStringNotContainsString( 'Closed page', (string) $zip->getFromName( 'index.html' ), 'And it is not in the page list either.' );
	}

	/**
	 * Only what was asked for: an area export carries its own subtree and
	 * nothing else.
	 *
	 * @return void
	 */
	public function test_an_area_export_carries_only_its_subtree(): void {
		$term  = $this->handbook( 'Company' );
		$area  = $this->page( $term, 'Upkeep' );
		$this->page( $term, 'Review pages', 'How to review.', $area );
		$this->page( $term, 'Somewhere else' );

		$zip = $this->export( $term, $area );

		$this->assertNotFalse( $zip->locateName( 'upkeep.html' ) );
		$this->assertNotFalse( $zip->locateName( 'upkeep/review-pages.html' ) );
		$this->assertFalse( $zip->locateName( 'somewhere-else.html' ) );
	}

	/**
	 * A page with headings gets a table of contents, built on the server from the
	 * anchors, so it is in the file rather than assembled by a script that would
	 * have to run first.
	 *
	 * @return void
	 */
	public function test_a_page_gets_a_table_of_contents(): void {
		$term = $this->handbook( 'Company' );
		$this->page(
			$term,
			'Review pages',
			"<!-- wp:heading --><h2>Why review</h2><!-- /wp:heading -->\n<!-- wp:heading --><h2>How often</h2><!-- /wp:heading -->"
		);

		$zip  = $this->export( $term );
		$html = (string) $zip->getFromName( 'review-pages.html' );

		$this->assertStringContainsString( 'living-handbook-toc__list', $html, 'The template\'s own table-of-contents block.' );
		$this->assertStringContainsString( 'href="#why-review"', $html, 'Filled here rather than in the browser, so it survives without scripting.' );
		$this->assertStringContainsString( '>Why review</a>', $html, 'And the entry reads as the heading does, without the anchor link that sits in it.' );
	}

	/**
	 * The search index is a script that sets a global, not a JSON file a page
	 * would have to fetch: a document opened over file:// is its own origin in
	 * most browsers and may not fetch the file lying next to it.
	 *
	 * @return void
	 */
	public function test_the_search_index_is_a_script(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'Review pages', 'Reviewing keeps a handbook honest.' );

		$zip   = $this->export( $term );
		$index = (string) $zip->getFromName( 'assets/search-index.js' );

		$this->assertStringStartsWith( 'window.LH_PAGES = [', $index );
		$this->assertStringContainsString( 'Review pages', $index );
		$this->assertStringContainsString( 'Reviewing keeps a handbook honest.', $index );
		$this->assertStringContainsString( 'review-pages.html', $index );
		$this->assertStringNotContainsString( ' # ', $index, 'The anchor links of the headings are not text somebody searches for.' );
	}

	/**
	 * An export that runs out of time keeps its place and finishes on the next
	 * pass. With the budget at zero every pass renders one page, which is the
	 * shape a handbook of two thousand pages has on a real server.
	 *
	 * @return void
	 */
	public function test_an_export_continues_where_it_paused(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );
		$this->page( $term, 'Two' );
		$this->page( $term, 'Three' );

		add_filter( 'living_handbook_static_export_time_budget', '__return_zero' );

		$first = $this->pass( array( 'handbook' => $term ) );
		$this->assertFalse( (bool) ( $first['done'] ?? false ), 'A zero budget cannot render three pages in one pass.' );
		$this->assertSame( 3, (int) $first['total'] );
		$this->assertSame( 2, (int) $first['remaining'] );

		$second = $this->pass( array( 'job' => $first['job'] ) );
		$this->assertSame( 1, (int) $second['remaining'] );

		$third = $this->pass( array( 'job' => $first['job'] ) );
		remove_filter( 'living_handbook_static_export_time_budget', '__return_zero' );

		$this->assertTrue( (bool) ( $third['done'] ?? false ) );

		$state         = get_transient( 'living_handbook_static_' . (string) $first['job'] );
		$this->files[] = (string) $state['zip'];

		$zip = new ZipArchive();
		$this->assertTrue( $zip->open( (string) $state['zip'] ) );
		$this->assertNotFalse( $zip->locateName( 'one.html' ) );
		$this->assertNotFalse( $zip->locateName( 'three.html' ), 'The pages rendered before the pause are still in the archive.' );
		$this->assertNotFalse( $zip->locateName( 'index.html' ), 'And the site files are written when the last pass finishes.' );
	}

	/**
	 * The download link works when a browser follows it.
	 *
	 * This one is here because it did not. The URL was built with
	 * wp_nonce_url(), which escapes its result for HTML; the answer travels as
	 * JSON, the script assigns it to link.href, and "&#038;" turned everything
	 * after the first parameter into the fragment. WordPress then answered "the
	 * link you followed has expired", which is what a missing nonce looks like
	 * from the outside and told nobody where to look.
	 *
	 * @return void
	 */
	public function test_the_download_link_carries_its_parameters(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );

		$data = $this->pass( array( 'handbook' => $term ) );
		$this->assertTrue( (bool) ( $data['done'] ?? false ) );

		$url = (string) $data['url'];
		$this->assertStringNotContainsString( '&#038;', $url, 'An HTML-escaped ampersand does not survive the trip through JSON.' );
		$this->assertStringNotContainsString( '&amp;', $url );

		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $params );

		$this->assertSame( 'living_handbook_static_download', $params['action'] ?? '' );
		$this->assertSame( (string) $data['job'], $params['job'] ?? '' );
		$this->assertNotFalse(
			wp_verify_nonce( (string) ( $params['_wpnonce'] ?? '' ), 'living_handbook_static_download' ),
			'And the nonce has to be the one the download handler checks.'
		);

		$state         = get_transient( 'living_handbook_static_' . (string) $data['job'] );
		$this->files[] = (string) $state['zip'];
	}

	/**
	 * A job belongs to the person who started it. Anything else would let one
	 * editor pick up another's export, which is a copy of pages they may never
	 * have been allowed to read.
	 *
	 * @return void
	 */
	public function test_a_job_belongs_to_the_user_who_started_it(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );
		$this->page( $term, 'Two' );

		add_filter( 'living_handbook_static_export_time_budget', '__return_zero' );
		$first = $this->pass( array( 'handbook' => $term ) );
		remove_filter( 'living_handbook_static_export_time_budget', '__return_zero' );

		$this->assertArrayHasKey( 'job', $first );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/static-export' );
		$request->set_param( 'job', $first['job'] );
		$response = rest_do_request( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Somebody who may not edit other people's pages may not carry a whole
	 * handbook out of the site either. The same rule as the bundle export.
	 *
	 * @return void
	 */
	public function test_an_author_cannot_export(): void {
		$term = $this->handbook( 'Company' );
		$this->page( $term, 'One' );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'author' ) ) );

		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/static-export' );
		$request->set_param( 'handbook', $term );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}
}
