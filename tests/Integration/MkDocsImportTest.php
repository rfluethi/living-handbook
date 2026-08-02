<?php
/**
 * Reading the navigation out of a real mkdocs.yml.
 *
 * A MkDocs project configures Python plugins in the same file that holds its
 * navigation, with YAML tags only Python understands. A PHP parser stops there,
 * and the import used to fall back to a flat pile of files named after their
 * file names. That is the case these tests are about: the structure has to
 * survive a file this import cannot fully read.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\MarkdownConverter;
use LivingHandbook\Import\MkDocsImport;
use WP_UnitTestCase;

/**
 * mkdocs.yml navigation.
 */
final class MkDocsImportTest extends WP_UnitTestCase {

	/**
	 * The Markdown files a nav refers to.
	 *
	 * @return array<string, string>
	 */
	private function files(): array {
		return array(
			'docs/index.md'                         => "# Start\n\nWelcome.\n",
			'docs/01-basics/index.md'               => "# Basics\n\nOverview.\n",
			'docs/01-basics/01-000-what-is-it.md'   => "# What is it\n\nText.\n",
			'docs/01-basics/01-010-why-bother.md'   => "# Why bother\n\nText.\n",
			'docs/02-deeper/index.md'               => "# Deeper\n\nOverview.\n",
			'docs/02-deeper/02-010-first-step.md'   => "# First step\n\nText.\n",
		);
	}

	/**
	 * A nav that mirrors the files above.
	 *
	 * @return string
	 */
	private function nav(): string {
		return <<<'YAML'
nav:
  - "Einführung": index.md
  - "01 Basics":
    - "Übersicht": 01-basics/index.md
    - "Was ist das?": 01-basics/01-000-what-is-it.md
    - "Wozu?": 01-basics/01-010-why-bother.md
  - "02 Tiefer":
    - "Übersicht": 02-deeper/index.md
    - "Erster Schritt": 02-deeper/02-010-first-step.md
YAML;
	}

	/**
	 * Build the specs for a mkdocs.yml.
	 *
	 * @param string             $yaml  File contents.
	 * @param array<int, string> $notes Filled with the import notes.
	 * @return array<int, mixed>
	 */
	private function specs( string $yaml, array &$notes = array() ): array {
		return MkDocsImport::build_specs( $yaml, $this->files(), array(), new MarkdownConverter(), $notes );
	}

	/**
	 * The nav is used: titles, nesting and order come from the file, not from
	 * the file names.
	 *
	 * @return void
	 */
	public function test_a_plain_nav_gives_titles_and_structure(): void {
		$notes = array();
		$specs = $this->specs( $this->nav(), $notes );

		$this->assertNotEmpty( $specs );
		$this->assertSame( array(), $notes, 'A file that parses needs no explanation.' );

		// A section whose first child is an index.md becomes that page, under the
		// section's own title: "01 Basics" is the folder's index.md, and the
		// "Übersicht" entry does not become a second page beside it.
		$titles = wp_list_pluck( $specs, 'navTitle' );
		$this->assertSame(
			array( 'Einführung', '01 Basics', 'Was ist das?', 'Wozu?', '02 Tiefer', 'Erster Schritt' ),
			$titles,
			'Every page carries its nav title, in nav order.'
		);

		$this->assertSame( '01-basics/index.md', $specs[1]['sourcePath'] );
		$this->assertSame( '01-basics/index.md', $specs[2]['parentPath'], 'The pages of a section hang under its page.' );
	}

	/**
	 * A Python tag in the plugin configuration no longer costs the structure.
	 *
	 * This is the file MkDocs itself recommends for Mermaid diagrams: the tag
	 * !!python/name: is a built-in YAML tag no PHP parser resolves, and it used
	 * to throw away the whole file, nav included.
	 *
	 * @return void
	 */
	public function test_a_python_tag_does_not_cost_the_navigation(): void {
		$yaml = "site_name: Test\n" . $this->nav() . "\n" . <<<'YAML'
markdown_extensions:
  - admonition
  - pymdownx.superfences:
      custom_fences:
        - name: mermaid
          class: mermaid
          format: !!python/name:pymdownx.superfences.fence_code_format
YAML;

		$notes = array();
		$specs = $this->specs( $yaml, $notes );

		$this->assertCount( 6, $specs, 'The nav is used although the file as a whole cannot be read.' );
		$this->assertSame( 'Was ist das?', $specs[2]['navTitle'] );
		$this->assertNotEmpty( $notes, 'The report has to say that the file was only read in part.' );
	}

	/**
	 * A nav that cannot be read either leaves the import to its flat path, and
	 * says why instead of silently importing the wrong thing.
	 *
	 * @return void
	 */
	public function test_an_unreadable_file_is_reported(): void {
		$notes = array();
		$specs = $this->specs( "nav:\n  - \"Broken\": [unclosed\n", $notes );

		$this->assertSame( array(), $specs );
		$this->assertNotEmpty( $notes );
	}

	/**
	 * A file without a nav is not an error, but it is worth saying: the files are
	 * imported, only without structure.
	 *
	 * @return void
	 */
	public function test_a_missing_nav_is_reported(): void {
		$notes = array();
		$specs = $this->specs( "site_name: Test\ntheme:\n  name: material\n", $notes );

		$this->assertSame( array(), $specs );
		$this->assertNotEmpty( $notes );
	}

	/**
	 * The nav block is cut at the next top-level key, not at the first blank line
	 * or comment inside it.
	 *
	 * @return void
	 */
	public function test_the_nav_block_survives_blank_lines_and_comments(): void {
		$yaml = "site_name: Test\n"
			. "nav:\n"
			. "  - \"Einführung\": index.md\n"
			. "\n"
			. "  # a comment inside the block\n"
			. "  - \"02 Tiefer\":\n"
			. "    - \"Übersicht\": 02-deeper/index.md\n"
			. "plugins:\n"
			. "  - search\n"
			. "hooks:\n"
			. "  - x: !!python/name:whatever\n";

		$notes = array();
		$specs = $this->specs( $yaml, $notes );

		$this->assertCount( 2, $specs );
		$this->assertSame( array( 'Einführung', '02 Tiefer' ), wp_list_pluck( $specs, 'navTitle' ) );
	}
}
