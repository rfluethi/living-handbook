<?php
/**
 * The filter bar of the handbook list follows the columns.
 *
 * WordPress has one place where a person says what the list should show, the
 * "Screen Options" panel, and it only governs columns. The filters above the
 * list were unaffected, so a list narrowed to title and handbook still offered
 * seven dropdowns. These tests hold the three parts of the rule: a filter is
 * hidden when its column is off, it is left out entirely when its taxonomy
 * has no term, and it stays visible while it is narrowing the list, because a
 * filter nobody can see is a filter nobody can undo.
 *
 * Hidden rather than left out matters: the Screen Options checkboxes take
 * effect without a reload, and a control that was never rendered could be
 * hidden live but never brought back.
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
	 * The column each filter belongs to.
	 */
	private const HANDBOOK_COLUMN = 'living_handbook_set';
	private const TOPIC_COLUMN    = 'taxonomy-' . Taxonomies::TOPIC;
	private const ROLE_COLUMN     = 'taxonomy-' . Taxonomies::ROLE;
	private const AUDIENCE_COLUMN = 'taxonomy-' . Taxonomies::AUDIENCE;
	private const REVIEW_COLUMN   = 'living_handbook_reviewed';
	private const SOURCE_COLUMN   = 'lh_source';

	/**
	 * Put the code on the handbook list screen and give every taxonomy a term,
	 * so a missing dropdown in a test is the rule at work and not an empty
	 * taxonomy.
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
	 * The opening wrapper tag of one column's filter, or an empty string when
	 * that filter was not rendered at all.
	 *
	 * @param string $out    Rendered filter bar.
	 * @param string $column Column key.
	 * @return string
	 */
	private function wrapper( string $out, string $column ): string {
		$found = array();
		if ( ! preg_match( '#<span class="living-handbook-list-filter" data-column="' . preg_quote( $column, '#' ) . '"[^>]*>#', $out, $found ) ) {
			return '';
		}
		return $found[0];
	}

	/**
	 * Whether the filter for a column is in the document at all.
	 *
	 * @param string $out    Rendered filter bar.
	 * @param string $column Column key.
	 * @return bool
	 */
	private function rendered( string $out, string $column ): bool {
		return '' !== $this->wrapper( $out, $column );
	}

	/**
	 * Whether the filter for a column is rendered and on screen.
	 *
	 * @param string $out    Rendered filter bar.
	 * @param string $column Column key.
	 * @return bool
	 */
	private function visible( string $out, string $column ): bool {
		$wrapper = $this->wrapper( $out, $column );
		return '' !== $wrapper && ! str_contains( $wrapper, ' hidden' );
	}

	/**
	 * Nothing hidden, every taxonomy filled: all seven dropdowns, on screen.
	 *
	 * @return void
	 */
	public function test_all_filters_are_shown_by_default(): void {
		$out = $this->filter_bar();

		$columns = array(
			self::HANDBOOK_COLUMN,
			'taxonomy-' . Taxonomies::PAGE_TYPE,
			self::TOPIC_COLUMN,
			self::ROLE_COLUMN,
			self::AUDIENCE_COLUMN,
			self::REVIEW_COLUMN,
			self::SOURCE_COLUMN,
		);
		foreach ( $columns as $column ) {
			$this->assertTrue( $this->visible( $out, $column ), $column . ' should be offered' );
		}
	}

	/**
	 * A hidden column hides its dropdown, and only its own. The dropdown stays in
	 * the document, so switching the column back on can bring it back without a
	 * reload.
	 *
	 * @return void
	 */
	public function test_a_hidden_column_hides_its_filter(): void {
		$this->hide( array( self::TOPIC_COLUMN ) );

		$out = $this->filter_bar();

		$this->assertTrue( $this->rendered( $out, self::TOPIC_COLUMN ) );
		$this->assertFalse( $this->visible( $out, self::TOPIC_COLUMN ) );
		$this->assertTrue( $this->visible( $out, self::AUDIENCE_COLUMN ) );
		$this->assertTrue( $this->visible( $out, self::HANDBOOK_COLUMN ) );
	}

	/**
	 * The handbook filter hangs on this plugin's own column, not on a taxonomy
	 * column: handbook_set is registered without one.
	 *
	 * @return void
	 */
	public function test_the_handbook_filter_follows_the_handbook_column(): void {
		$this->hide( array( self::HANDBOOK_COLUMN ) );

		$out = $this->filter_bar();

		$this->assertFalse( $this->visible( $out, self::HANDBOOK_COLUMN ) );
		$this->assertTrue( $this->visible( $out, self::TOPIC_COLUMN ) );
	}

	/**
	 * A taxonomy without a single term is left out of the document entirely,
	 * not just hidden. Unlike a column, that cannot change while the page is
	 * open, so there is nothing to bring back.
	 *
	 * @return void
	 */
	public function test_an_empty_taxonomy_has_no_filter(): void {
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

		$this->assertFalse( $this->rendered( $out, self::ROLE_COLUMN ) );
		$this->assertTrue( $this->visible( $out, self::TOPIC_COLUMN ) );
	}

	/**
	 * A filter that is currently narrowing the list stays on screen even with its
	 * column hidden, and says so, so the script leaves it alone too. Without
	 * this, the list would stay filtered by a term with no control to clear it.
	 *
	 * @return void
	 */
	public function test_an_active_filter_survives_a_hidden_column(): void {
		$_GET[ Taxonomies::TOPIC ] = 'onboarding';
		$this->hide( array( self::TOPIC_COLUMN ) );

		$out = $this->filter_bar();

		$this->assertTrue( $this->visible( $out, self::TOPIC_COLUMN ) );
		$this->assertStringContainsString( 'data-active="1"', $this->wrapper( $out, self::TOPIC_COLUMN ) );
	}

	/**
	 * An active filter survives even when its taxonomy was emptied in the
	 * meantime: the term is gone, the query var is not.
	 *
	 * @return void
	 */
	public function test_an_active_filter_survives_an_empty_taxonomy(): void {
		foreach ( get_terms(
			array(
				'taxonomy'   => Taxonomies::ROLE,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		) as $term_id ) {
			wp_delete_term( (int) $term_id, Taxonomies::ROLE );
		}
		$_GET[ Taxonomies::ROLE ] = 'editor';

		$this->assertTrue( $this->visible( $this->filter_bar(), self::ROLE_COLUMN ) );
	}

	/**
	 * The same for the review status, which has no taxonomy behind it.
	 *
	 * @return void
	 */
	public function test_the_status_filter_follows_the_review_column(): void {
		$this->hide( array( self::REVIEW_COLUMN ) );
		$this->assertFalse( $this->visible( $this->filter_bar(), self::REVIEW_COLUMN ) );

		$_GET['lh_status'] = 'overdue';
		$this->assertTrue( $this->visible( $this->filter_bar(), self::REVIEW_COLUMN ) );
	}

	/**
	 * And for the source filter, which lives in another class and had to be
	 * taught the same rule.
	 *
	 * @return void
	 */
	public function test_the_source_filter_follows_the_source_column(): void {
		$this->hide( array( self::SOURCE_COLUMN ) );
		$this->assertFalse( $this->visible( $this->filter_bar(), self::SOURCE_COLUMN ) );

		$_GET['lh_source'] = 'github';
		$this->assertTrue( $this->visible( $this->filter_bar(), self::SOURCE_COLUMN ) );
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
