<?php
/**
 * SVG files that arrive through an import are cleaned before they are stored.
 *
 * Every import path (ZIP, folder, GitHub sync, bundle) hands its images to
 * MarkdownImportPage::sideload_image(), and an SVG is the one image format that
 * can carry script. The cleaning is done by a bundled library, so these tests
 * are what notices when a version of that library lets something through:
 * 0.75.0 shipped svg-sanitize 0.22.0, which kept a javascript: link behind a
 * DTD entity (CVE-2026-107380), and no test said so.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\MarkdownImportPage;
use WP_UnitTestCase;

/**
 * MarkdownImportPage::sideload_image() for SVG files.
 */
final class SvgSanitizeTest extends WP_UnitTestCase {

	/**
	 * The stored file behind a media URL.
	 *
	 * @param string $url Media URL.
	 * @return string File contents.
	 */
	private function stored( string $url ): string {
		$id = attachment_url_to_postid( $url );
		$this->assertGreaterThan( 0, $id, 'The SVG should have become an attachment.' );
		$file = (string) get_attached_file( $id );
		return (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a test fixture.
	}

	/**
	 * Script, a javascript: link and an event handler are removed, the drawing
	 * itself is kept.
	 *
	 * @return void
	 */
	public function test_script_and_handlers_are_removed(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script>'
			. '<a href="javascript:alert(2)"><rect onload="alert(3)" width="10" height="10"/></a>'
			. '<circle r="4"/></svg>';

		$url = MarkdownImportPage::sideload_image( 'drawing.svg', $svg );
		$this->assertNotSame( '', $url );

		$stored = $this->stored( $url );
		$this->assertStringNotContainsString( '<script', $stored );
		$this->assertStringNotContainsString( 'javascript:', $stored );
		$this->assertStringNotContainsString( 'onload', $stored );
		$this->assertStringContainsString( '<circle', $stored, 'The harmless part of the drawing stays.' );
	}

	/**
	 * A DTD entity named like an HTML character reference (&Tab;) hid a
	 * javascript: link from the check in svg-sanitize up to 0.22.0. The file is
	 * either refused or stored without the link; it is never stored with it.
	 *
	 * @return void
	 */
	public function test_entity_hidden_javascript_link_is_not_stored(): void {
		$svg = '<!DOCTYPE svg [<!ENTITY Tab "#">]>'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 12">'
			. '<a href="&Tab;javascript:alert(document.domain)"><rect width="40" height="12"/></a></svg>';

		$url = MarkdownImportPage::sideload_image( 'click-me.svg', $svg );
		if ( '' === $url ) {
			$this->assertSame( '', $url, 'Refused outright.' );
			return;
		}

		$stored = $this->stored( $url );
		$this->assertStringNotContainsString( 'javascript:', $stored );
		$this->assertStringNotContainsString( '&Tab;', $stored );
	}
}
