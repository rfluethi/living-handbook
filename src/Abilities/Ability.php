<?php
/**
 * What every ability of the plugin has.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One ability: its name, its texts, its schemas, who may run it, what it does.
 *
 * Abilities::register_abilities() turns each into a wp_register_ability()
 * call and adds the meta every one of them carries, so no single ability can
 * leave out an annotation or the exposure flags.
 */
abstract class Ability {

	/**
	 * Name without the namespace, such as "search".
	 *
	 * @return string
	 */
	abstract public function slug(): string;

	/**
	 * Short label.
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * What it does and when a model should use it.
	 *
	 * @return string
	 */
	abstract public function description(): string;

	/**
	 * Input schema.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function input_schema(): array;

	/**
	 * Output schema.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function output_schema(): array;

	/**
	 * Runs it.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|WP_Error
	 */
	abstract public function run( array $input );

	/**
	 * Whether the current person may run it. Reading abilities: any signed-in
	 * account; what it sees is decided page by page.
	 *
	 * @return bool
	 */
	public function permitted(): bool {
		return Pages::may_read();
	}

	/**
	 * Full name, with the plugin's namespace.
	 *
	 * @return string
	 */
	final public function name(): string {
		return Abilities::CATEGORY . '/' . $this->slug();
	}

	/**
	 * The execute callback: input normalised to an array.
	 *
	 * @param mixed $input Input from the Abilities API.
	 * @return array<string, mixed>|WP_Error
	 */
	final public function execute( $input = null ) {
		return $this->run( is_array( $input ) ? $input : array() );
	}

	/**
	 * The permission callback.
	 *
	 * @param mixed $input Input, unused: the right does not depend on it.
	 * @return bool
	 */
	final public function permission( $input = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the Abilities API passes it.
		return $this->permitted();
	}

	/**
	 * Schema of a page reference, as Pages::reference() builds it.
	 *
	 * @return array<string, mixed>
	 */
	protected static function page_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'title'    => array( 'type' => 'string' ),
				'url'      => array(
					'type'        => 'string',
					'description' => 'Address of the page. Link it when you use it.',
				),
				'handbook' => array(
					'type'        => 'string',
					'description' => 'Slug of the handbook.',
				),
				'review'   => array(
					'type'        => 'object',
					'description' => 'Review status. Say when a page is due or overdue for review.',
					'properties'  => array(
						'status'        => array(
							'type' => 'string',
							'enum' => array( 'ok', 'due', 'overdue', 'none' ),
						),
						'label'         => array( 'type' => 'string' ),
						'last_reviewed' => array( 'type' => 'string' ),
						'interval_days' => array( 'type' => 'integer' ),
					),
				),
			),
		);
	}

	/**
	 * Schema of a handbook reference.
	 *
	 * @return array<string, mixed>
	 */
	protected static function handbook_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'   => array( 'type' => 'integer' ),
				'slug' => array( 'type' => 'string' ),
				'name' => array( 'type' => 'string' ),
				'url'  => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Schema of the optional handbook input.
	 *
	 * @return array<string, mixed>
	 */
	protected static function handbook_input(): array {
		return array(
			'type'        => array( 'string', 'integer' ),
			'description' => 'Slug or id of a handbook, from list-handbooks. Leave out for all handbooks you may read.',
		);
	}
}
