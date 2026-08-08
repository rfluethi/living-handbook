<?php
/**
 * The filter bar of the handbook list follows the columns.
 *
 * WordPress has one place where a person says what the list should show, the
 * "Screen Options" panel, and it only governs columns. The filters above the
 * list were unaffected, so a list narrowed to title and handbook still offered
 * five dropdowns. These tests hold the two halves of the rule: a filter goes
 * when its column is off or its vocabulary is empty, and a filter that is
 * currently narrowing the list stays regardless, because a filter nobody can
 * see is a filter nobody can undo.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Admin\Maintenance;
use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_UnitTestCase;

/**
 * Maintenance::taxonomy_filter_dropdowns, Maintenance::status_filter_dropdown
 * and GitSync::source_filter_dropdown.
 */
final class ListFiltersTest extends WP_UnitTestCase {

	/**
	 * Put the code on the handbook list screen and give every vocabulary a term,
	 * so a missing dropdown in a test is the rule at work and not an empty
	 * vocabulary.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		set_current_screen( 'edit-' . Handbook::POST_TYPE );

		foreach ( array( Handbooks::TAXONOMY, Taxonomies::PAGE_TYPE, Taxonomies::TOPIC, Taxonomies::ROLE, Taxonomies::AUDIENCE ) as $taxonomy ) {
			self::factory()->term->create(
				array(
					'taxonomy' => $taxonomy,
					'name'     => 'Term in ' . $taxonomy,
				)
			);
		}
	}

	/**
	 * Reset the request and the screen between tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_GET = array();
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	/**
	 * Hide columns for the duration of one test.
	 *
	 * Goes through the filter WordPress itself reads in get_hidden_columns, which
	 * is the same value the Screen Options panel stores per user.
	 *
	 * @param array<int, string> $columns Column keys to hide.
	 * @return void
	 */
	private function hide( array $columns ): void {
		add_filter(
			'hidden_columns',
			static function () use ( $columns ): array {
				return $columns;
			}
		);
	}

	/**
	 * Whether the bar offers a control under this name.
	 *
	 * The quoting differs by origin: wp_dropdown_categories writes single quotes,
	 * this plugin's own selects double ones. The test asks about the control, not
	 * about who printed it.
	 *
	 * @param string $out  Rendered filter bar.
	 * @param string $name Form field name.
	 * @return bool
	 */
	private function offers( string $out, string $name ): bool {
		return str_contains( $out, 'name="' . $name . '"' ) || str_contains( $out, "name='" . $name . "'" );
	}

	/**
	 * The rendered filter bar of the handbook list.
	 *
	 * @return string
	 */
	private function filter_bar(): string {
		$maintenance = new Maintenance();

		ob_start();
		$maintenance->taxonomy_filter_dropdowns( Handbook::POST_TYPE );
		$maintenance->status_filter_dropdown( Handbook::POST_TYPE );
		( new GitSync() )->source_filter_dropdown( Handbook::POST_TYPE );

		return (string) ob_get_clean();
	}

	/**
	 * Nothing hidden, every vocabulary filled: all seven dropdowns.
	 *
	 * @return void
	 */
	public function test_all_filters_are_shown_by_default(): void {
		$out = $this->filter_bar();

		foreach ( array( Handbooks::TAXONOMY, Taxonomies::PAGE_TYPE, Taxonomies::TOPIC, Taxonomies::ROLE, Taxonomies::AUDIENCE, 'lh_status', 'lh_source' ) as $name ) {
			$this->assertTrue( $this->offers( $out, $name ), $name . ' should be offered' );
		}
	}

	/**
	 * A hidden taxonomy column takes its dropdown with it, and only its own.
	 *
	 * @return void
	 */
	public function test_a_hidden_column_removes_its_filter(): void {
		$this->hide( array( 'taxonomy-' . Taxonomies::TOPIC ) );

		$out = $this->filter_bar();

		$this->assertFalse( $this->offers( $out, Taxonomies::TOPIC ) );
		$this->assertTrue( $this->offers( $out, Taxonomies::AUDIENCE ) );
		$this->assertTrue( $this->offers( $out, Handbooks::TAXONOMY ) );
	}

	/**
	 * The handbook filter hangs on this plugin's own column, not on a taxonomy
	 * column: handbook_set is registered without one.
	 *
	 * @return void
	 */
	public function test_the_handbook_filter_follows_the_handbook_column(): void {
		$this->hide( array( 'living_handbook_set' ) );

		$out = $this->filter_bar();

		$this->assertFalse( $this->offers( $out, Handbooks::TAXONOMY ) );
		$this->assertTrue( $this->offers( $out, Taxonomies::TOPIC ) );
	}

	/**
	 * A vocabulary without a single term offers nothing to choose, so it is left
	 * out even with its column on screen.
	 *
	 * @return void
	 */
	public function test_an_empty_vocabulary_has_no_filter(): void {
		foreach ( get_terms(
			array(
				'taxonomy'   => Taxonomies::ROLE,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		) as $term_id ) {
			wp_delete_term( (int) $term_id, Taxonomies::ROLE );
		}

		$out = $this->filter_bar();

		$this->assertFalse( $this->offers( $out, Taxonomies::ROLE ) );
		$this->assertTrue( $this->offers( $out, Taxonomies::TOPIC ) );
	}

	/**
	 * A filter that is currently narrowing the list survives its hidden column.
	 * Without this, the list would stay filtered by a term with no control on
	 * screen to clear it.
	 *
	 * @return void
	 */
	public function test_an_active_filter_survives_a_hidden_column(): void {
		$_GET[ Taxonomies::TOPIC ] = 'onboarding';
		$this->hide( array( 'taxonomy-' . Taxonomies::TOPIC ) );

		$this->assertTrue( $this->offers( $this->filter_bar(), Taxonomies::TOPIC ) );
	}

	/**
	 * The same for the review status, which has no taxonomy behind it.
	 *
	 * @return void
	 */
	public function test_the_status_filter_follows_the_review_column(): void {
		$this->hide( array( 'living_handbook_reviewed' ) );
		$this->assertFalse( $this->offers( $this->filter_bar(), 'lh_status' ) );

		$_GET['lh_status'] = 'overdue';
		$this->assertTrue( $this->offers( $this->filter_bar(), 'lh_status' ) );
	}

	/**
	 * And for the source filter, which lives in another class and had to be
	 * taught the same rule.
	 *
	 * @return void
	 */
	public function test_the_source_filter_follows_the_source_column(): void {
		$this->hide( array( 'lh_source' ) );
		$this->assertFalse( $this->offers( $this->filter_bar(), 'lh_source' ) );

		$_GET['lh_source'] = 'github';
		$this->assertTrue( $this->offers( $this->filter_bar(), 'lh_source' ) );
	}

	/**
	 * Another post type's list is not this plugin's business.
	 *
	 * @return void
	 */
	public function test_another_post_type_gets_no_filters(): void {
		$maintenance = new Maintenance();

		ob_start();
		$maintenance->taxonomy_filter_dropdowns( 'page' );
		$maintenance->status_filter_dropdown( 'page' );

		$this->assertSame( '', (string) ob_get_clean() );
	}
}
