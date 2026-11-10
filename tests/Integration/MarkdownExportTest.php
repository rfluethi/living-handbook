<?php
/**
 * The Markdown export and its way back in (E-0083).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Export\MarkdownExport;
use LivingHandbook\Export\MarkdownWriter;
use LivingHandbook\Files\ServerRules;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\MarkdownConverter;
use LivingHandbook\Import\MarkdownImportPage;
use LivingHandbook\Import\MkDocsImport;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_REST_Request;
use WP_Term;
use WP_UnitTestCase;

/**
 * A handbook goes out as Markdown files and comes back in as the same pages.
 */
final class MarkdownExportTest extends WP_UnitTestCase {

	/**
	 * Attachments and ZIPs made by a test.
	 *
	 * @var array<int, int|string>
	 */
	private array $made = array();

	/**
	 * Run as an administrator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Remove files.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->made as $item ) {
			if ( is_int( $item ) ) {
				wp_delete_attachment( $item, true );
			} elseif ( is_file( $item ) ) {
				wp_delete_file( $item );
			}
		}
		$this->made = array();
		ServerRules::remove();
		parent::tear_down();
	}

	/**
	 * A handbook.
	 *
	 * @param string $name       Name.
	 * @param string $visibility Visibility.
	 * @return WP_Term
	 */
	private function handbook( string $name = 'Team: Handbuch', string $visibility = Handbooks::VISIBILITY_MEMBERS ): WP_Term {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => $name,
			)
		);
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );
		$term = get_term( $term_id, Handbooks::TAXONOMY );
		$this->assertInstanceOf( WP_Term::class, $term );
		return $term;
	}

	/**
	 * A page in a handbook.
	 *
	 * @param WP_Term             $term  Handbook.
	 * @param string              $title Title.
	 * @param string              $html  Content.
	 * @param array<string,mixed> $args  More arguments.
	 * @return int
	 */
	private function page( WP_Term $term, string $title, string $html = '<!-- wp:paragraph --><p>Text.</p><!-- /wp:paragraph -->', array $args = array() ): int {
		$id = (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => Handbook::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => wp_slash( $html ),
				),
				$args
			)
		);
		wp_set_object_terms( $id, array( $term->term_id ), Handbooks::TAXONOMY );
		return $id;
	}

	/**
	 * The handbook every roundtrip test uses: an area with two pages, a page
	 * on its own, and the content worth checking.
	 *
	 * @return array{term: WP_Term, area: int, first: int, second: int, single: int, image: int}
	 */
	private function sample(): array {
		$term = $this->handbook();
		$area = $this->page( $term, 'Pflege', '<!-- wp:paragraph --><p>Alles zur Pflege.</p><!-- /wp:paragraph -->', array( 'menu_order' => 1 ) );

		$single = $this->page( $term, 'Über uns', '', array( 'menu_order' => 2 ) );
		$image  = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $single );
		$this->made[] = $image;
		$url    = (string) wp_get_attachment_url( $image );

		$first = $this->page(
			$term,
			'Seiten prüfen',
			'<!-- wp:heading --><h2 class="wp-block-heading">Schritte</h2><!-- /wp:heading -->'
			. '<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><!-- wp:list-item --><li>Öffnen</li><!-- /wp:list-item --><!-- wp:list-item --><li>Prüfen mit <strong>Sorgfalt</strong></li><!-- /wp:list-item --></ol><!-- /wp:list -->'
			. '<!-- wp:paragraph --><p>Ein *Sternchen* und 3. Zeile, siehe <a href="' . esc_url( (string) get_permalink( $single ) ) . '#team">Über uns</a>.</p><!-- /wp:paragraph -->'
			. '<!-- wp:details --><details class="wp-block-details"><summary>Hinweis: Wann prüfen</summary><!-- wp:paragraph --><p>Alle 180 Tage.</p><!-- /wp:paragraph --></details><!-- /wp:details -->'
			. '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>Rolle</th><th>Aufgabe</th></tr></thead><tbody><tr><td>Redaktion</td><td>a | b</td></tr></tbody></table></figure><!-- /wp:table -->'
			// Serialised by WordPress, as the editor does: the block parser of 7.2
			// reads a raw arrow in the attributes as the end of the comment.
			. get_comment_delimited_block_content( 'living-handbook/mermaid', array( 'code' => "graph TD;\n  A --> B;" ), '' )
			. '<!-- wp:living-handbook/feedback /-->'
			. '<!-- wp:code --><pre class="wp-block-code"><code>wp living-handbook protect-files</code></pre><!-- /wp:code -->',
			array(
				'post_parent'  => $area,
				'menu_order'   => 1,
				'post_excerpt' => 'Wie man prüft.',
				'post_name'    => 'seiten-pruefen',
			)
		);
		$second = $this->page( $term, 'Feedback auswerten', '<!-- wp:image --><figure class="wp-block-image"><img src="' . esc_url( $url ) . '" alt="Raps"/></figure><!-- /wp:image -->', array( 'post_parent' => $area, 'menu_order' => 2 ) );

		wp_set_object_terms( $first, array( 'Anleitung' ), Taxonomies::PAGE_TYPE );
		wp_set_object_terms( $first, array( 'Redaktion' ), Taxonomies::ROLE );
		wp_set_object_terms( $first, array( 'Pflege' ), Taxonomies::TOPIC );
		wp_set_object_terms( $first, array( 'Alle Mitglieder', 'Technik' ), Taxonomies::AUDIENCE );
		update_post_meta( $first, Metadata::REVIEWED, '2026-10-01' );
		update_post_meta( $first, Metadata::INTERVAL, 180 );

		return array(
			'term'   => $term,
			'area'   => $area,
			'first'  => $first,
			'second' => $second,
			'single' => $single,
			'image'  => $image,
		);
	}

	/**
	 * The files: a folder with README.md for a page with subpages, a file for
	 * the others, the images in assets, the navigation and the report.
	 *
	 * @return void
	 */
	public function test_the_layout(): void {
		$sample = $this->sample();
		$export = MarkdownExport::build( $sample['term'] );

		$this->assertSame(
			array( 'EXPORT.txt', 'docs/pflege/README.md', 'docs/pflege/feedback-auswerten.md', 'docs/pflege/seiten-pruefen.md', 'docs/uber-uns.md', 'mkdocs.yml' ),
			self::sorted( array_keys( $export['files'] ) )
		);
		$this->assertSame( array( 'docs/assets/canola.jpg' ), array_keys( $export['assets'] ) );
		$this->assertSame( (string) get_attached_file( $sample['image'] ), $export['assets']['docs/assets/canola.jpg'] );

		$yaml = $export['files']['mkdocs.yml'];
		$this->assertStringContainsString( 'site_name: "Team: Handbuch"', $yaml );
		$this->assertStringContainsString( "  - \"Pflege\":\n      - \"pflege/README.md\"\n      - \"Seiten prüfen\": \"pflege/seiten-pruefen.md\"\n      - \"Feedback auswerten\": \"pflege/feedback-auswerten.md\"\n  - \"Über uns\": \"uber-uns.md\"", $yaml );
	}

	/**
	 * The page: title, Markdown, relative links, the mermaid fence, the
	 * transport block; the feedback block is left out and named.
	 *
	 * @return void
	 */
	public function test_the_page(): void {
		$sample = $this->sample();
		$export = MarkdownExport::build( $sample['term'] );
		$md     = $export['files']['docs/pflege/seiten-pruefen.md'];

		$this->assertStringStartsWith( "# Seiten prüfen\n\n## Schritte\n\n1. Öffnen\n2. Prüfen mit **Sorgfalt**", $md );
		$this->assertStringContainsString( 'Ein \\*Sternchen\\* und 3. Zeile, siehe [Über uns](../uber-uns.md#team).', $md );
		$this->assertStringContainsString( "<details>\n<summary>Hinweis: Wann prüfen</summary>\n\nAlle 180 Tage.\n\n</details>", $md );
		$this->assertStringContainsString( "| Rolle | Aufgabe |\n| --- | --- |\n| Redaktion | a \\| b |", $md );
		$this->assertStringContainsString( "```mermaid\ngraph TD;\n  A --> B;\n```", $md );
		$this->assertStringContainsString( "```\nwp living-handbook protect-files\n```", $md );
		$this->assertStringEndsWith(
			"## Transport-Metadaten\n* Seitentyp: Anleitung\n* Slug: seiten-pruefen\n* Verantwortliche Rolle: Redaktion\n* Thema: Pflege\n* Zielgruppe: Alle Mitglieder, Technik\n* Eltern-Seite: Pflege\n* Reihenfolge: 1\n* Textauszug: Wie man prüft.\n* Letzte Aktualisierung: " . get_post_meta( $sample['first'], Metadata::UPDATED, true ) . "\n* Letzte Prüfung: 2026-10-01\n* Prüfintervall: 180 Tage\n",
			$md
		);
		$this->assertStringContainsString( '![Raps](../assets/canola.jpg)', $export['files']['docs/pflege/feedback-auswerten.md'] );
		$this->assertStringContainsString( '* Eltern-Seite: oberste Ebene', $export['files']['docs/pflege/README.md'] );

		$this->assertSame( array( 'Seiten prüfen' => array( 'Handbook feedback' ) ), $export['left_out'] );
		$this->assertStringContainsString( "- Seiten prüfen: Handbook feedback\n", $export['files']['EXPORT.txt'] );
		$this->assertStringContainsString( 'Living Handbook ' . LIVING_HANDBOOK_VERSION, $export['files']['EXPORT.txt'] );
	}

	/**
	 * Only what the person may read; a page with a password stays out, and
	 * its subpages move up.
	 *
	 * @return void
	 */
	public function test_what_is_left_out(): void {
		$term   = $this->handbook();
		$secret = $this->page( $term, 'Geheim', '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', array( 'post_password' => 'pw' ) );
		$this->page( $term, 'Darunter', '<!-- wp:paragraph --><p>y</p><!-- /wp:paragraph -->', array( 'post_parent' => $secret ) );
		$hidden = $this->page( $term, 'Versteckt' );
		add_filter(
			'living_handbook_can_view_post',
			static function ( $allowed, $post_id ) use ( $hidden ) {
				return $hidden === (int) $post_id ? false : $allowed;
			},
			10,
			2
		);

		$export = MarkdownExport::build( $term );

		$this->assertArrayHasKey( 'docs/darunter.md', $export['files'] );
		$this->assertArrayNotHasKey( 'docs/geheim.md', $export['files'] );
		$this->assertArrayNotHasKey( 'docs/versteckt.md', $export['files'] );
		$this->assertSame( array( 'Geheim' ), $export['password'] );
		$this->assertStringContainsString( "- Geheim\n", $export['files']['EXPORT.txt'] );
		$this->assertStringNotContainsString( 'Versteckt', $export['files']['EXPORT.txt'] . $export['files']['mkdocs.yml'] );
	}

	/**
	 * An area exports alone, as the top of its own ZIP.
	 *
	 * @return void
	 */
	public function test_an_area(): void {
		$sample = $this->sample();
		$export = MarkdownExport::build( $sample['term'], $sample['area'] );

		$this->assertSame( 'pflege', $export['root_slug'] );
		$this->assertArrayHasKey( 'docs/pflege/README.md', $export['files'] );
		$this->assertArrayNotHasKey( 'docs/uber-uns.md', $export['files'] );
		// The link to a page outside the area stays a link to the site.
		$this->assertStringContainsString( '](' . (string) get_permalink( $sample['single'] ) . '#team)', $export['files']['docs/pflege/seiten-pruefen.md'] );
	}

	/**
	 * Text that looks like Markdown stays text.
	 *
	 * @return void
	 */
	public function test_text_is_escaped(): void {
		$md = MarkdownWriter::convert( '<p># kein Titel</p><p>- kein Punkt<br>1. keine Zahl</p><p>a_b [c] `d` &lt;e&gt;</p>' );
		$this->assertSame( "\\# kein Titel\n\n\\- kein Punkt\\\n1\\. keine Zahl\n\na\\_b \\[c\\] \\`d\\` \\<e>", $md );
	}

	/**
	 * Nested lists, line breaks, code spans and addresses with spaces come out
	 * the way CommonMark reads them back.
	 *
	 * @return void
	 */
	public function test_writer_details(): void {
		$md = MarkdownWriter::convert( '<ul><li>Eins<ul><li>Zwei</li></ul></li><li>Drei<br>weiter</li></ul><p>Code <code>a`b</code> und <a href="/a b.pdf">PDF</a> <img src="x.png" alt="Bild [1]"></p><h3>Titel <a href="#t">#</a></h3>' );
		$this->assertSame( "- Eins\n  - Zwei\n- Drei\\\n  weiter\n\nCode ``a`b`` und [PDF](</a b.pdf>) ![Bild \\[1\\]](x.png)\n\n### Titel", $md );

		$html = ( new MarkdownConverter() )->convert( "# T\n\n" . $md )['html'];
		$this->assertStringContainsString( '<li>Zwei</li>', $html );
		$this->assertMatchesRegularExpression( '#Drei<br ?/?>\nweiter#', $html );
		$this->assertStringContainsString( '<code>a`b</code>', $html );
		$this->assertStringContainsString( 'alt="Bild [1]"', $html );
	}

	/**
	 * The way back: the ZIP goes through the Markdown import, page by page as
	 * the import screen does it, and the same handbook comes out: titles,
	 * structure, order, metadata, text, the image and the link between pages.
	 *
	 * @return void
	 */
	public function test_the_roundtrip(): void {
		$sample = $this->sample();
		$zip    = MarkdownExport::build_zip( $sample['term'] );
		$this->made[] = $zip;
		$this->assertFileExists( $zip );

		$read = MarkdownImportPage::read_zip( $zip );
		$this->assertIsArray( $read );
		$this->assertNotSame( '', $read['mkdocs'] );
		$this->assertSame( array( 'canola.jpg' ), array_keys( $read['images'] ) );

		$image_map = array();
		foreach ( $read['images'] as $name => $data ) {
			$image_map[ $name ] = MarkdownImportPage::sideload_image( $name, $data );
			$found              = attachment_url_to_postid( $image_map[ $name ] );
			if ( $found > 0 ) {
				$this->made[] = $found;
			}
		}
		$notes = array();
		$specs = MkDocsImport::build_specs( $read['mkdocs'], $read['markdown'], $image_map, new MarkdownConverter(), $notes );
		$this->assertSame( array(), $notes );
		$this->assertSame( array( 'Pflege', 'Seiten prüfen', 'Feedback auswerten', 'Über uns' ), array_column( $specs, 'navTitle' ) );

		$target = $this->handbook( 'Ziel' );
		$import = new MarkdownImportPage();
		$ids    = array();
		$by     = array();
		foreach ( $specs as $spec ) {
			$request = new WP_REST_Request( 'POST', '/living-handbook/v1/create' );
			foreach (
				array(
					'title'      => $spec['navTitle'],
					'content'    => $spec['html'],
					'slug'       => $spec['slug'],
					'handbook'   => $target->term_id,
					'transport'  => $spec['transport'] ?? array(),
					'parent'     => '' !== $spec['parentPath'] ? ( $by[ $spec['parentPath'] ] ?? 0 ) : 0,
					'sourcePath' => $spec['sourcePath'],
					'order'      => $spec['order'],
				) as $key => $value
			) {
				$request->set_param( $key, $value );
			}
			$created = $import->create_callback( $request );
			$this->assertArrayHasKey( 'id', $created, (string) wp_json_encode( $created ) );
			$by[ $spec['sourcePath'] ] = (int) $created['id'];
			$ids[]                     = (int) $created['id'];
		}
		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/finalize' );
		$request->set_param( 'ids', $ids );
		$import->finalize_callback( $request );

		list( $area, $first, $second, $single ) = $ids;

		$this->assertSame( 'Seiten prüfen', get_the_title( $first ) );
		$this->assertSame( $area, (int) wp_get_post_parent_id( $first ) );
		$this->assertSame( $area, (int) wp_get_post_parent_id( $second ) );
		$this->assertSame( 0, (int) wp_get_post_parent_id( $single ) );
		$this->assertLessThan( (int) get_post_field( 'menu_order', $second ), (int) get_post_field( 'menu_order', $first ) );
		$this->assertSame( 'seiten-pruefen', get_post_field( 'post_name', $first ) );
		$this->assertSame( array( $target->term_id ), wp_get_object_terms( $first, Handbooks::TAXONOMY, array( 'fields' => 'ids' ) ) );

		$this->assertSame( array( 'Anleitung' ), wp_get_object_terms( $first, Taxonomies::PAGE_TYPE, array( 'fields' => 'names' ) ) );
		$this->assertSame( array( 'Redaktion' ), wp_get_object_terms( $first, Taxonomies::ROLE, array( 'fields' => 'names' ) ) );
		$this->assertSame( array( 'Pflege' ), wp_get_object_terms( $first, Taxonomies::TOPIC, array( 'fields' => 'names' ) ) );
		$this->assertEqualsCanonicalizing( array( 'Alle Mitglieder', 'Technik' ), wp_get_object_terms( $first, Taxonomies::AUDIENCE, array( 'fields' => 'names' ) ) );
		$this->assertSame( '2026-10-01', get_post_meta( $first, Metadata::REVIEWED, true ) );
		$this->assertSame( 180, (int) get_post_meta( $first, Metadata::INTERVAL, true ) );
		$this->assertSame( 'Wie man prüft.', get_post_field( 'post_excerpt', $first ) );

		$content = (string) get_post_field( 'post_content', $first );
		$this->assertStringContainsString( '<strong>Sorgfalt</strong>', $content );
		$this->assertStringContainsString( 'Ein *Sternchen* und 3. Zeile', $content );
		$this->assertStringContainsString( '<summary>Hinweis: Wann prüfen</summary>', $content );
		$this->assertStringContainsString( '<td>a | b</td>', $content );
		$this->assertStringContainsString( 'language-mermaid', $content );
		// Without unfiltered_html (in a network everyone but a super admin) kses
		// writes the ampersand as &amp; instead of &#038;.
		$this->assertStringContainsString( 'href="' . str_replace( '&#038;', '&amp;', esc_url( (string) get_permalink( $single ) . '#team' ) ) . '"', str_replace( '&#038;', '&amp;', $content ), 'The link points at the imported page.' );
		$this->assertStringContainsString( (string) $image_map['canola.jpg'], (string) get_post_field( 'post_content', $second ) );
	}

	/**
	 * Paths in a list, sorted.
	 *
	 * @param array<int, string> $list List.
	 * @return array<int, string>
	 */
	private static function sorted( array $list ): array {
		sort( $list );
		return $list;
	}
}
