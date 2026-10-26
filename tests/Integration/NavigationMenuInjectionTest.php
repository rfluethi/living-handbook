<?php
/**
 * A handbook name reaches the theme's navigation exactly as it was written.
 *
 * The handbook list is spliced into a core Navigation block with
 * preg_replace(), and the list was the replacement string. A replacement reads
 * "$1" or "\1" as a back reference, so up to 0.75.1 a handbook called
 * "Budget $10" showed up in the menu as "Budget " (group 10 does not exist and
 * is replaced by nothing), and a name with "$1" in it repeated a piece of the
 * menu markup.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\FrontendRenderer;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Setup\Onboarding;
use WP_UnitTestCase;

/**
 * FrontendRenderer::inject_handbook_menu.
 */
final class NavigationMenuInjectionTest extends WP_UnitTestCase {

	/**
	 * A public handbook with a name.
	 *
	 * @param string $name Handbook name.
	 * @return void
	 */
	private function handbook( string $name ): void {
		$term = wp_insert_term( $name, Handbooks::TAXONOMY );
		$this->assertIsArray( $term );
		update_term_meta( (int) $term['term_id'], Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
	}

	/**
	 * Inject into a block and return the result.
	 *
	 * @param string $name    Block name.
	 * @param string $content Rendered block HTML.
	 * @return string
	 */
	private function inject( string $name, string $content ): string {
		return ( new FrontendRenderer() )->inject_handbook_menu(
			$content,
			array(
				'blockName' => $name,
				'attrs'     => array( 'className' => 'has-handbook-menu' ),
			)
		);
	}

	/**
	 * Every injection point keeps the names: a submenu with a container, a
	 * navigation link turned into a submenu, and the top-level submenu of a
	 * whole navigation block.
	 *
	 * @return void
	 */
	public function test_dollar_signs_and_backslashes_survive_in_the_menu(): void {
		$this->handbook( 'Budget $10' );
		// wp_insert_term() unslashes the name, so the backslash goes in doubled.
		$this->handbook( wp_slash( 'Path \\1 and $1' ) );

		$overview = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		update_option( Onboarding::OPTION_OVERVIEW_PAGE, $overview );

		wp_set_current_user( 0 );

		$outputs = array(
			'submenu'    => $this->inject( 'core/navigation-submenu', '<li class="wp-block-navigation-item has-child"><a href="#">Books</a><ul class="wp-block-navigation__submenu-container"></ul></li>' ),
			'empty'      => $this->inject( 'core/navigation-submenu', '<li class="wp-block-navigation-item"><a href="#">Books</a></li>' ),
			'link'       => $this->inject( 'core/navigation-link', '<li class="wp-block-navigation-item"><a href="#">Books</a></li>' ),
			'navigation' => $this->inject( 'core/navigation', '<nav><ul class="wp-block-navigation__container"></ul></nav>' ),
		);

		foreach ( $outputs as $where => $html ) {
			$this->assertStringContainsString( '>Budget $10<', $html, $where );
			$this->assertStringContainsString( '>Path \\1 and $1<', $html, $where );
		}

		delete_option( Onboarding::OPTION_OVERVIEW_PAGE );
	}
}
