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
 */
final class TransportBlock {

	/**
	 * Parse the raw transport section.
	 *
	 * @param string $text The transport section (from its heading to the end).
	 * @return array{page_type:string,role:string,topic:string,audiences:array<int,string>,handbook:string,parent:string,order:int,excerpt:string,reviewed:string,interval:int}
	 */
	public function parse( string $text ): array {
		$values = array();
		if ( preg_match_all( '/^\*\s*([^:]+):\s*(.+)$/m', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $line ) {
				$values[ mb_strtolower( trim( $line[1] ) ) ] = trim( $line[2] );
			}
		}

		$audiences = array();
		foreach ( explode( ',', $this->value( $values, 'zielgruppe' ) ) as $audience ) {
			$audience = trim( $audience );
			if ( '' !== $audience ) {
				$audiences[] = $audience;
			}
		}

		$reviewed = $this->value( $values, 'letzte prüfung' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reviewed ) ) {
			$reviewed = '';
		}

		$interval = 0;
		if ( preg_match( '/(\d+)/', $this->value( $values, 'prüfintervall' ), $found ) ) {
			$interval = (int) $found[1];
		}

		return array(
			'page_type' => $this->value( $values, 'seitentyp' ),
			'role'      => $this->value( $values, 'verantwortliche rolle' ),
			'topic'     => $this->value( $values, 'themengebiet' ),
			'audiences' => $audiences,
			'handbook'  => $this->value( $values, 'handbuch' ),
			'parent'    => $this->value( $values, 'eltern-seite' ),
			'order'     => (int) $this->value( $values, 'reihenfolge' ),
			'excerpt'   => $this->value( $values, 'textauszug' ),
			'reviewed'  => $reviewed,
			'interval'  => $interval,
		);
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
