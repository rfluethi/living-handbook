<?php
/**
 * HTML sanitizer integration tests.
 *
 * The sanitizer is the one allowlist for HTML that entered the plugin from a
 * Markdown source (a pasted draft, a ZIP, or a GitHub pull). It must strip
 * anything executable while keeping the markup the plugin's own features rely
 * on. It uses wp_kses, so it is tested with WordPress loaded.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\HtmlSanitizer;
use WP_UnitTestCase;

/**
 * The external-HTML allowlist.
 */
final class HtmlSanitizerTest extends WP_UnitTestCase {

	/**
	 * A script tag is removed, while ordinary text is kept.
	 *
	 * @return void
	 */
	public function test_strips_script_tag(): void {
		$clean = HtmlSanitizer::clean( '<p>ok</p><script>alert(1)</script>' );
		$this->assertStringContainsString( '<p>ok</p>', $clean );
		$this->assertStringNotContainsString( '<script', $clean );
	}

	/**
	 * An inline event handler is stripped from an otherwise allowed element.
	 *
	 * @return void
	 */
	public function test_strips_event_handler(): void {
		$clean = HtmlSanitizer::clean( '<a href="https://example.com" onclick="steal()">link</a>' );
		$this->assertStringNotContainsString( 'onclick', $clean );
		$this->assertStringContainsString( 'href="https://example.com"', $clean );
	}

	/**
	 * A javascript: URL is not preserved as a working link target.
	 *
	 * @return void
	 */
	public function test_strips_javascript_url(): void {
		$clean = HtmlSanitizer::clean( '<a href="javascript:alert(1)">x</a>' );
		$this->assertStringNotContainsString( 'javascript:', $clean );
	}

	/**
	 * The Mermaid code fence keeps its class, so the diagram is still recognised.
	 *
	 * @return void
	 */
	public function test_keeps_mermaid_class(): void {
		$clean = HtmlSanitizer::clean( '<pre class="mermaid">graph TD; A--&gt;B</pre>' );
		$this->assertStringContainsString( 'class="mermaid"', $clean );
	}

	/**
	 * The disclosure markup (details/summary) survives.
	 *
	 * @return void
	 */
	public function test_keeps_details_and_summary(): void {
		$clean = HtmlSanitizer::clean( '<details><summary>More</summary><p>body</p></details>' );
		$this->assertStringContainsString( '<details>', $clean );
		$this->assertStringContainsString( '<summary>', $clean );
	}

	/**
	 * A read-only GitHub task-list checkbox survives as a display element.
	 *
	 * @return void
	 */
	public function test_keeps_disabled_task_checkbox(): void {
		$clean = HtmlSanitizer::clean( '<input type="checkbox" disabled checked class="task-list-item-checkbox">' );
		$this->assertStringContainsString( 'type="checkbox"', $clean );
		$this->assertStringContainsString( 'disabled', $clean );
	}
}
