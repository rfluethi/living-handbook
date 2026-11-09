<?php
/**
 * Registers the plugin's abilities.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The reading abilities of Living Handbook (Konzept 04, E-0056).
 *
 * Registered only where the Abilities API exists, WordPress 6.9 and later; the
 * plugin itself still runs from 6.8. Each ability reads, none writes content.
 * Through the MCP Adapter they become tools of an AI client, and every call
 * runs as the WordPress account the client signed in with: an account sees
 * through an ability exactly what it sees on the site.
 *
 * Every ability carries the same meta:
 *
 * - public, show_in_rest and mcp.public all true. WordPress 7.1 reads the
 *   high-level public flag, 6.9 only show_in_rest, the MCP Adapter mcp.public
 *   before public. Set together, the exposure is the same on every version.
 * - The annotations readonly, not destructive, idempotent. The search counts a
 *   search without a hit (E-0078); that is bookkeeping about the search, not a
 *   change to anything a reader or an editor sees, and it is not counted as
 *   writing here.
 */
final class Abilities {

	/**
	 * Category slug, also the namespace of every ability name.
	 */
	public const CATEGORY = 'living-handbook';

	/**
	 * Hooks the registration, where the API exists.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	/**
	 * Every ability, in the order a model would use them.
	 *
	 * @return array<int, Ability>
	 */
	public static function all(): array {
		return array(
			new ListHandbooks(),
			new GetStructure(),
			new Search(),
			new GetPage(),
			new ListDuePages(),
			new ListFeedback(),
			new ListFailedSearches(),
		);
	}

	/**
	 * Registers the category.
	 *
	 * @return void
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Living Handbook', 'living-handbook' ),
				'description' => __( 'Read the handbooks of this site: find, read and review pages.', 'living-handbook' ),
			)
		);
	}

	/**
	 * Registers every ability.
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		foreach ( self::all() as $ability ) {
			wp_register_ability(
				$ability->name(),
				array(
					'label'               => $ability->label(),
					'description'         => $ability->description(),
					'category'            => self::CATEGORY,
					'input_schema'        => $ability->input_schema(),
					'output_schema'       => $ability->output_schema(),
					'execute_callback'    => array( $ability, 'execute' ),
					'permission_callback' => array( $ability, 'permission' ),
					'meta'                => self::meta(),
				)
			);
		}
	}

	/**
	 * The meta every ability carries.
	 *
	 * @return array<string, mixed>
	 */
	public static function meta(): array {
		return array(
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
	}
}
