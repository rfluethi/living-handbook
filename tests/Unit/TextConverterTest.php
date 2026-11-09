<?php
/**
 * HTML to text for the get-page ability.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use LivingHandbook\Abilities\TextConverter;
use PHPUnit\Framework\TestCase;

/**
 * The structure of a page survives, the markup does not.
 */
final class TextConverterTest extends TestCase {

	/**
	 * Headings, paragraphs and inline marks.
	 *
	 * @return void
	 */
	public function test_headings_paragraphs_and_marks(): void {
		$html = '<h2 id="a" class="wp-block-heading">Zugang für Gäste <a class="living-handbook-anchor" href="#a" aria-label="Link">#</a></h2><p>Ein <strong>wichtiger</strong> und <em>kurzer</em> Satz mit <code>wp-config.php</code>.</p><h3>Weiter</h3><p>Zweite<br>Zeile</p>';

		$this->assertSame(
			"## Zugang für Gäste\n\nEin **wichtiger** und *kurzer* Satz mit `wp-config.php`.\n\n### Weiter\n\nZweite\nZeile",
			TextConverter::convert( $html )
		);
	}

	/**
	 * Nested lists, ordered and not, with their start number.
	 *
	 * @return void
	 */
	public function test_lists_nest(): void {
		$html = '<ul><li>Eins<ul><li>Eins a</li><li>Eins b</li></ul></li><li>Zwei</li></ul><ol start="3"><li>Drei</li><li><p>Vier</p></li></ol>';

		$this->assertSame(
			"- Eins\n  - Eins a\n  - Eins b\n- Zwei\n\n3. Drei\n4. Vier",
			TextConverter::convert( $html )
		);
	}

	/**
	 * A table becomes a pipe table; a pipe in a cell is escaped.
	 *
	 * @return void
	 */
	public function test_tables(): void {
		$html = '<figure class="wp-block-table"><table><thead><tr><th>Rolle</th><th>Recht</th></tr></thead><tbody><tr><td>Redaktion</td><td>lesen | schreiben</td></tr><tr><td>Gast</td></tr></tbody></table></figure>';

		$this->assertSame(
			"| Rolle | Recht |\n| --- | --- |\n| Redaktion | lesen \\| schreiben |\n| Gast |  |",
			TextConverter::convert( $html )
		);
	}

	/**
	 * Mermaid as a fenced mermaid block, other code with its language, and a
	 * fence longer than the backticks inside.
	 *
	 * @return void
	 */
	public function test_code_and_mermaid(): void {
		$html = '<figure class="living-handbook-figure"><pre class="mermaid">graph TD;
  A--&gt;B;</pre></figure><pre class="wp-block-code"><code class="language-php">echo "```";</code></pre>';

		$this->assertSame(
			"```mermaid\ngraph TD;\n  A-->B;\n```\n\n````php\necho \"```\";\n````",
			TextConverter::convert( $html )
		);
	}

	/**
	 * A collapsible section comes out opened, its summary in bold.
	 *
	 * @return void
	 */
	public function test_details_are_opened(): void {
		$html = '<details class="wp-block-details"><summary>Mehr dazu</summary><p>Versteckter Text.</p></details>';

		$this->assertSame( "**Mehr dazu**\n\nVersteckter Text.", TextConverter::convert( $html ) );
	}

	/**
	 * Links and images keep their address, unless the callback hides it.
	 *
	 * @return void
	 */
	public function test_addresses_can_be_hidden(): void {
		$html = '<p><a href="https://example.org/seite">Seite</a>, <a href="https://example.org/wp-content/uploads/plan.pdf">Plan</a>, <a href="#oben">oben</a>.</p><figure><img src="https://example.org/wp-content/uploads/bild.png" alt="Ablauf"></figure>';

		$this->assertSame(
			"[Seite](https://example.org/seite), [Plan](https://example.org/wp-content/uploads/plan.pdf), oben.\n\n![Ablauf](https://example.org/wp-content/uploads/bild.png)",
			TextConverter::convert( $html )
		);

		$hide = static fn( string $url ): bool => false !== strpos( $url, '/wp-content/uploads/' );
		$this->assertSame(
			"[Seite](https://example.org/seite), Plan, oben.\n\n[Image: Ablauf]",
			TextConverter::convert( $html, $hide )
		);
	}

	/**
	 * Scripts, styles, forms and aria-hidden parts are left out.
	 *
	 * @return void
	 */
	public function test_noise_is_dropped(): void {
		$html = '<p>Text</p><script>alert(1)</script><style>p{}</style><form><input name="q"></form><p><span aria-hidden="true">★</span>Rest</p>';

		$this->assertSame( "Text\n\nRest", TextConverter::convert( $html ) );
	}

	/**
	 * Umlauts survive the HTML parser.
	 *
	 * @return void
	 */
	public function test_utf8_survives(): void {
		$this->assertSame( 'Prüfintervall für Übersichtsseiten', TextConverter::convert( '<p>Prüfintervall für Übersichtsseiten</p>' ) );
		$this->assertSame( '', TextConverter::convert( '   ' ) );
	}
}
