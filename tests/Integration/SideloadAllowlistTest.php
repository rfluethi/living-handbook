<?php
/**
 * What an import may put into the media library.
 *
 * Every import path hands its files to MarkdownImportPage::sideload_image().
 * Up to 0.75.0 that took any name: the GitHub sync passes whatever a page in a
 * foreign repository references, and wp_upload_bits() accepts .html for anyone
 * with unfiltered_html, which is every editor and administrator on a single
 * site. "![x](evil.html)" in somebody else's repository was stored XSS here.
 * And the SVG cleaning looked at the name before sanitize_file_name() trimmed
 * its trailing dot, so "logo.svg." was stored as an SVG without being cleaned.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\MarkdownImportPage;
use WP_UnitTestCase;

/**
 * Extension allowlist and content check for imported media.
 */
final class SideloadAllowlistTest extends WP_UnitTestCase {

	/**
	 * A one pixel PNG.
	 *
	 * @return string
	 */
	private function png(): string {
		return (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A one pixel PNG for the test.
	}

	/**
	 * Run as an administrator, who holds unfiltered_html: the person the hole
	 * was open for.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$user = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		// In a network only a super admin holds unfiltered_html.
		if ( is_multisite() ) {
			grant_super_admin( $user );
		}
		wp_set_current_user( $user );
		$this->assertTrue( current_user_can( 'unfiltered_html' ), 'The test needs a user the core upload rules let HTML through for.' );
	}

	/**
	 * An HTML file referenced as an image is not stored.
	 *
	 * @return void
	 */
	public function test_an_html_file_is_refused(): void {
		$url = MarkdownImportPage::sideload_image( 'evil.html', '<script>alert(document.cookie)</script>' );

		$this->assertSame( '', $url );
		$this->assertSame( 0, $this->count_attachments() );
	}

	/**
	 * A file whose name says image and whose content says otherwise is refused.
	 *
	 * @return void
	 */
	public function test_html_dressed_as_png_is_refused(): void {
		$this->assertSame( '', MarkdownImportPage::sideload_image( 'pixel.png', '<html><script>alert(1)</script></html>' ) );
	}

	/**
	 * A trailing dot no longer hides an SVG from the cleaning.
	 *
	 * @return void
	 */
	public function test_a_trailing_dot_does_not_skip_the_svg_cleaning(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="4"/></svg>';

		// A site that allows unfiltered uploads (ALLOW_UNFILTERED_UPLOADS) is the
		// one where the old code stored the uncleaned file.
		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap ): array {
				return 'unfiltered_upload' === $cap ? array( 'exist' ) : $caps;
			},
			10,
			2
		);

		$url = MarkdownImportPage::sideload_image( 'logo.svg.', $svg );

		if ( '' !== $url ) {
			$id     = attachment_url_to_postid( $url );
			$stored = (string) file_get_contents( (string) get_attached_file( $id ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading what was stored.
			$this->assertStringNotContainsString( '<script', $stored, 'An SVG is cleaned, whatever dots its name ends in.' );
		}
		$this->assertStringEndsWith( '.svg', MarkdownImportPage::image_file_name( 'logo.svg.', $svg ), 'It is judged by the name it is stored under.' );
	}

	/**
	 * A real image still arrives.
	 *
	 * @return void
	 */
	public function test_a_real_png_is_stored(): void {
		$url = MarkdownImportPage::sideload_image( 'pixel.png', $this->png() );

		$this->assertNotSame( '', $url );
		$this->assertStringEndsWith( '.png', $url );
	}

	/**
	 * A bundle's PDF still travels, its HTML does not.
	 *
	 * @return void
	 */
	public function test_bundle_media_keeps_documents_but_not_html(): void {
		$pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

		$this->assertNotSame( '', MarkdownImportPage::sideload_media( '12-handout.pdf', $pdf ), 'A document a page links to is part of the bundle.' );
		$this->assertNotSame( '', MarkdownImportPage::sideload_media( '13-pixel.png', $this->png() ) );
		$this->assertSame( '', MarkdownImportPage::sideload_media( '14-page.html', '<script>alert(1)</script>' ) );
		$this->assertSame( '', MarkdownImportPage::sideload_media( '15-tool.js', 'alert(1)' ) );
		$this->assertSame( '', MarkdownImportPage::sideload_media( '16-fake.pdf', '<html><script>alert(1)</script></html>' ), 'The content has to match the extension.' );
	}

	/**
	 * The start of an AVIF file: the ftyp box with the given brands, then a
	 * free box. Enough for the signature; no image library has to read it.
	 *
	 * @param string $major      Major brand.
	 * @param string $compatible Compatible brands, four characters each.
	 * @return string
	 */
	private function avif( string $major = 'avif', string $compatible = 'mif1miaf' ): string {
		$ftyp = $major . pack( 'N', 0 ) . $compatible;
		return pack( 'N', 8 + strlen( $ftyp ) ) . 'ftyp' . $ftyp . pack( 'N', 8 ) . 'free';
	}

	/**
	 * An AVIF image, which WordPress accepts as an upload, is imported. Up to
	 * 0.76.0 it was neither on the image list nor on the document list, and
	 * was dropped without a word.
	 *
	 * @return void
	 */
	public function test_an_avif_image_is_stored(): void {
		$this->assertStringEndsWith( '.avif', MarkdownImportPage::sideload_image( 'photo.avif', $this->avif() ) );
		$this->assertStringEndsWith( '.avif', MarkdownImportPage::sideload_media( '17-sequence.avif', $this->avif( 'avis', 'msf1' ) ), 'A bundle carries it too.' );
		$this->assertNotSame( '', MarkdownImportPage::image_file_name( 'brand.avif', $this->avif( 'mif1', 'avifmiaf' ) ), 'A compatible brand is enough.' );
	}

	/**
	 * A file named .avif has to be one.
	 *
	 * @return void
	 */
	public function test_html_dressed_as_avif_is_refused(): void {
		$this->assertSame( '', MarkdownImportPage::sideload_image( 'x.avif', '<html><script>alert(1)</script></html>' ) );
		$this->assertSame( '', MarkdownImportPage::sideload_media( '18-x.avif', '<html><script>alert(1)</script></html>' ) );
		$this->assertSame( '', MarkdownImportPage::image_file_name( 'movie.avif', $this->avif( 'isom', 'mp41' ) ), 'Another ISO media file is no AVIF.' );
	}

	/**
	 * How many attachments exist.
	 *
	 * @return int
	 */
	private function count_attachments(): int {
		return count(
			get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}
}
