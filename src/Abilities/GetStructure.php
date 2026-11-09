<?php
/**
 * Ability living-handbook/get-structure.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page tree of one handbook, flat and in navigation order.
 *
 * A flat list with a depth and a parent id rather than nested objects: a model
 * reads it top to bottom like the navigation, and a long handbook does not
 * nest ten levels of brackets.
 */
final class GetStructure extends Ability {

	/**
	 * Most pages in one answer. A larger handbook is cut, and says so.
	 */
	public const MAX_PAGES = 1000;

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'get-structure';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Get handbook structure', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Returns the page tree of one handbook in navigation order: title, id, parent, depth, page type, address and review status of every page you may read. Use it to see how a handbook is organised, then read single pages with get-page. Limit the depth for large handbooks.', 'living-handbook' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function input_schema(): array {
		$handbook                = self::handbook_input();
		$handbook['description'] = 'Slug or id of the handbook, from list-handbooks.';

		return array(
			'type'                 => 'object',
			'properties'           => array(
				'handbook' => $handbook,
				'depth'    => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 20,
					'description' => 'Deepest level to return, 1 for the top level only. Leave out for all levels.',
				),
			),
			'required'             => array( 'handbook' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function output_schema(): array {
		$page                         = self::page_schema();
		$page['properties']['parent'] = array(
			'type'        => 'integer',
			'description' => 'Id of the parent page in this list, 0 at the top.',
		);
		$page['properties']['depth']  = array( 'type' => 'integer' );
		$page['properties']['order']  = array( 'type' => 'integer' );
		$page['properties']['type']   = array( 'type' => 'string' );

		return array(
			'type'       => 'object',
			'properties' => array(
				'handbook'  => self::handbook_schema(),
				'pages'     => array(
					'type'  => 'array',
					'items' => $page,
				),
				'truncated' => array(
					'type'        => 'boolean',
					'description' => 'True when the handbook has more pages than one answer holds. Ask again with a smaller depth.',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( array $input ) {
		$term = Pages::handbook( $input['handbook'] ?? '' );
		if ( ! $term instanceof WP_Term ) {
			return $term;
		}

		$tree  = Pages::tree( (int) $term->term_id, (int) ( $input['depth'] ?? 0 ) );
		$pages = array();
		foreach ( array_slice( $tree, 0, self::MAX_PAGES ) as $node ) {
			$post            = $node['post'];
			$entry           = Pages::reference( $post, $term );
			$entry['parent'] = $node['parent'];
			$entry['depth']  = $node['depth'];
			$entry['order']  = (int) $post->menu_order;
			$entry['type']   = Pages::page_type( $post );
			$pages[]         = $entry;
		}

		return array(
			'handbook'  => Pages::handbook_reference( $term ),
			'pages'     => $pages,
			'truncated' => count( $tree ) > self::MAX_PAGES,
		);
	}
}
