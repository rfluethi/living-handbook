<?php
/**
 * Ability living-handbook/list-handbooks.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use LivingHandbook\Handbook\HandbookVersion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The handbooks the person may read: where a model can look.
 */
final class ListHandbooks extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'list-handbooks';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'List handbooks', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Lists the handbooks you may read, with name, slug, description, address, number of pages and version. Start here to know where to search.', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function output_schema(): array {
		$handbook                              = self::handbook_schema();
		$handbook['properties']['description'] = array( 'type' => 'string' );
		$handbook['properties']['pages']       = array( 'type' => 'integer' );
		$handbook['properties']['version']     = array(
			'type'        => 'string',
			'description' => 'Version of the handbook\'s content, such as 2.1.14 (not the plugin version).',
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'handbooks' => array(
					'type'  => 'array',
					'items' => $handbook,
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * The page count is what the other abilities would hand out: readable,
	 * without a password, not excluded from AI access.
	 *
	 * @param array<string, mixed> $input Validated input, unused.
	 * @return array<string, mixed>
	 */
	public function run( array $input ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the signature is the Ability's.
		$out = array();
		foreach ( Pages::readable_handbooks() as $term ) {
			$entry                = Pages::handbook_reference( $term );
			$entry['description'] = wp_strip_all_tags( (string) $term->description );
			$entry['pages']       = count( Pages::tree( (int) $term->term_id ) );
			$entry['version']     = HandbookVersion::get( (int) $term->term_id );
			$out[]                = $entry;
		}

		return array( 'handbooks' => $out );
	}
}
