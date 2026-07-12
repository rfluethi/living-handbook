<?php
/**
 * Unit tests for the transport block parser.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Import;

use LivingHandbook\Import\TransportBlock;
use PHPUnit\Framework\TestCase;

/**
 * Transport block parsing.
 */
final class TransportBlockTest extends TestCase {

	/**
	 * Reads every field and normalises the comma-separated audience list.
	 *
	 * @return void
	 */
	public function test_parses_fields(): void {
		$text = "## Transport-Metadaten\n"
			. "* Seitentyp: Anleitung\n"
			. "* Verantwortliche Rolle: Handbuch-Redaktion\n"
			. "* Themengebiet: Applikation\n"
			. "* Zielgruppe: Alle Mitglieder, Technik\n"
			. "* Eltern-Seite: Übersicht\n"
			. "* Reihenfolge: 3\n"
			. "* Textauszug: Kurz erklärt.\n"
			. "* Letzte Prüfung: 2026-07-08\n"
			. "* Prüfintervall: 90 Tage\n";

		$data = ( new TransportBlock() )->parse( $text );

		$this->assertSame( 'Anleitung', $data['page_type'] );
		$this->assertSame( 'Handbuch-Redaktion', $data['role'] );
		$this->assertSame( 'Applikation', $data['topic'] );
		$this->assertSame( array( 'Alle Mitglieder', 'Technik' ), $data['audiences'] );
		$this->assertSame( 'Übersicht', $data['parent'] );
		$this->assertSame( 3, $data['order'] );
		$this->assertSame( 'Kurz erklärt.', $data['excerpt'] );
		$this->assertSame( '2026-07-08', $data['reviewed'] );
		$this->assertSame( 90, $data['interval'] );
	}

	/**
	 * Treats bare placeholders as empty and resolves an assumption to its value.
	 *
	 * @return void
	 */
	public function test_handles_placeholders_and_assumptions(): void {
		$text = "* Seitentyp: [ANNAHME: FAQ]\n"
			. "* Verantwortliche Rolle: [Rolle]\n"
			. "* Letzte Prüfung: [JJJJ-MM-TT]\n"
			. "* Prüfintervall: [wird ergänzt]\n";

		$data = ( new TransportBlock() )->parse( $text );

		$this->assertSame( 'FAQ', $data['page_type'] );
		$this->assertSame( '', $data['role'] );
		$this->assertSame( '', $data['reviewed'] );
		$this->assertSame( 0, $data['interval'] );
	}
}
