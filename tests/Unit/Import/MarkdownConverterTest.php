<?php
/**
 * Unit tests for the transport-block marker detection.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Import;

use LivingHandbook\Import\MarkdownConverter;
use PHPUnit\Framework\TestCase;

/**
 * Marker detection: fenced code blocks are skipped, the last occurrence wins.
 */
final class MarkdownConverterTest extends TestCase {

	/**
	 * Finds the marker at the end of an ordinary draft.
	 *
	 * @return void
	 */
	public function test_finds_marker(): void {
		$body     = "# Titel\n\nText.\n\n";
		$markdown = $body . "## Transport-Metadaten\n* Reihenfolge: 1\n";

		$this->assertSame( strlen( $body ), MarkdownConverter::transport_marker_position( $markdown ) );
	}

	/**
	 * Returns null when there is no marker.
	 *
	 * @return void
	 */
	public function test_returns_null_without_marker(): void {
		$this->assertNull( MarkdownConverter::transport_marker_position( "# Titel\n\nText.\n" ) );
	}

	/**
	 * A marker inside a fenced code block is an example, not a marker.
	 *
	 * @return void
	 */
	public function test_ignores_marker_inside_code_fence(): void {
		$markdown = "# Titel\n\n```markdown\n## Transport-Metadaten\n* Reihenfolge: 1\n```\n\nText danach.\n";

		$this->assertNull( MarkdownConverter::transport_marker_position( $markdown ) );
	}

	/**
	 * With an example in a fence and a real marker at the end, the real one wins.
	 *
	 * @return void
	 */
	public function test_prefers_real_marker_after_fenced_example(): void {
		$before   = "# Titel\n\n```markdown\n## Transport-Metadaten\n* Reihenfolge: 1\n```\n\nText danach.\n\n";
		$markdown = $before . "## Transport-Metadaten\n* Reihenfolge: 2\n";

		$this->assertSame( strlen( $before ), MarkdownConverter::transport_marker_position( $markdown ) );
	}

	/**
	 * When the marker appears twice outside code, the last occurrence wins.
	 *
	 * @return void
	 */
	public function test_last_occurrence_wins(): void {
		$before   = "## Transport-Metadaten\n\nErklärender Text über den Steckbrief.\n\n";
		$markdown = $before . "## Transport-Metadaten\n* Reihenfolge: 2\n";

		$this->assertSame( strlen( $before ), MarkdownConverter::transport_marker_position( $markdown ) );
	}

	/**
	 * Tilde fences and longer closing fences are recognised too.
	 *
	 * @return void
	 */
	public function test_handles_tilde_and_long_fences(): void {
		$before   = "# Titel\n\n~~~text\n## Transport-Metadaten\n~~~~\n\n";
		$markdown = $before . "## Transport-Metadaten\n* Reihenfolge: 1\n";

		$this->assertSame( strlen( $before ), MarkdownConverter::transport_marker_position( $markdown ) );
	}
}
