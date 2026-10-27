<?php
/**
 * Parser for the transport block that accompanies a Markdown draft.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the German transport block (a bullet list of «Label: Value» lines) into
 * a normalised array. Placeholders such as [JJJJ-MM-TT] or [Rolle] count as
 * empty; [ANNAHME: X] resolves to X. Pure string handling, no WordPress calls.
 *
 * Some fields accept more than one label. The taxonomy that is shown as "Topics"
 * in the interface ("Themen" in German) accepts, in order of preference,
 * «Thema», then «Bereich» and «Themengebiet». «Thema» matches the current
 * interface label and is the preferred one for new drafts; «Bereich» and the
 * older «Themengebiet» keep the existing corpus of drafts working.
 *
 * The labels are German because the drafts this block travels with are; they
 * are data, not interface text. One value has a meaning of its own:
 * «Eltern-Seite: oberste Ebene» puts the page at the top level
 * (Postprocessor::apply_transport()).
 */
final class TransportBlock {

	/**
	 * Accepted labels per field, in order of preference. The first label that
	 * carries a value wins.
	 *
	 * @var array<string, string[]>
	 */
	private const LABELS = array(
		'page_type' => array( 'seitentyp' ),
		'role'      => array( 'verantwortliche rolle' ),
		'topic'     => array( 'thema', 'bereich', 'themengebiet' ),
		'audiences' => array( 'zielgruppe' ),
		'handbook'  => array( 'handbuch' ),
		'parent'    => array( 'eltern-seite' ),
		'order'     => array( 'reihenfolge' ),
		'excerpt'   => array( 'textauszug' ),
		'reviewed'  => array( 'letzte prüfung' ),
		'interval'  => array( 'prüfintervall' ),
		'slug'      => array( 'slug' ),
	);

	/**
	 * Parses the raw transport section.
	 *
	 * @param string $text The transport section (from its heading to the end).
	 * @return array{page_type:string,role:string,topic:string,audiences:array<int,string>,handbook:string,parent:string,order:int,excerpt:string,reviewed:string,interval:int,slug:string}
	 */
	public function parse( string $text ): array {
		$values = array();
		if ( preg_match_all( '/^\*\s*([^:]+):\s*(.+)$/m', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $line ) {
				$values[ mb_strtolower( trim( $line[1] ) ) ] = trim( $line[2] );
			}
		}

		$audiences = array();
		foreach ( explode( ',', $this->first( $values, self::LABELS['audiences'] ) ) as $audience ) {
			$audience = trim( $audience );
			if ( '' !== $audience ) {
				$audiences[] = $audience;
			}
		}

		$reviewed = $this->first( $values, self::LABELS['reviewed'] );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reviewed ) ) {
			$reviewed = '';
		}

		$interval = 0;
		if ( preg_match( '/(\d+)/', $this->first( $values, self::LABELS['interval'] ), $found ) ) {
			$interval = (int) $found[1];
		}

		return array(
			'page_type' => $this->first( $values, self::LABELS['page_type'] ),
			'role'      => $this->first( $values, self::LABELS['role'] ),
			'topic'     => $this->first( $values, self::LABELS['topic'] ),
			'audiences' => $audiences,
			'handbook'  => $this->first( $values, self::LABELS['handbook'] ),
			'parent'    => $this->first( $values, self::LABELS['parent'] ),
			'order'     => (int) $this->first( $values, self::LABELS['order'] ),
			'excerpt'   => $this->first( $values, self::LABELS['excerpt'] ),
			'reviewed'  => $reviewed,
			'interval'  => $interval,
			'slug'      => $this->first( $values, self::LABELS['slug'] ),
		);
	}

	/**
	 * Read the first label that carries a value, so a field can accept more
	 * than one spelling.
	 *
	 * @param array<string, string> $values Parsed label and value pairs.
	 * @param string[]              $keys   Lower-case labels, most preferred first.
	 * @return string
	 */
	private function first( array $values, array $keys ): string {
		foreach ( $keys as $key ) {
			$value = $this->value( $values, $key );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * Read one transport value, resolving assumptions and treating a bare
	 * placeholder as empty.
	 *
	 * @param array<string, string> $values Parsed label and value pairs.
	 * @param string                $key    Lower-case label to read.
	 * @return string
	 */
	private function value( array $values, string $key ): string {
		$value = isset( $values[ $key ] ) ? $values[ $key ] : '';
		if ( preg_match( '/\[ANNAHME:\s*(.+?)\]/', $value, $assumption ) ) {
			$value = trim( $assumption[1] );
		}
		if ( preg_match( '/^\[.*\]$/', trim( $value ) ) ) {
			return '';
		}
		return trim( $value );
	}
}
