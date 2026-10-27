<?php
/**
 * Moving pages into a handbook, and importing a bundle as ordinary pages.
 *
 * Both directions have one trap each that costs more than the feature is worth
 * if it is got wrong: a moved page whose old address stops working, and an
 * internal handbook that gets published by the act of importing it somewhere
 * else. Both are pinned here.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Admin\MoveToHandbook;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HandbookImport;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * MoveToHandbook and the bundle import's page mode.
 */
final class MigrationTest extends WP_UnitTestCase {

	/**
	 * Pretty permalinks, because that is what the addresses in this test are
	 * about. With the plain structure a page and a handbook page have the same
	 * empty path and there is nothing to redirect, which is correct behaviour and
	 * proves nothing.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rewrite;
		$this->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();
	}

	/**
	 * A handbook.
	 *
	 * @return int Term id.
	 */
	private function handbook(): int {
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( (int) $term->term_id, Handbooks::META_VISIBILITY, 'public' );

		return (int) $term->term_id;
	}

	/**
	 * An ordinary WordPress page.
	 *
	 * @param string $title  Title.
	 * @param int    $parent Parent page.
	 * @return int Post id.
	 */
	private function page( string $title, int $parent = 0 ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_parent' => $parent,
			)
		);
	}

	/**
	 * A moved page becomes a handbook page of the chosen handbook. Without the
	 * handbook it would not be moved but gone: access is fail-closed.
	 *
	 * @return void
	 */
	public function test_a_moved_page_lands_in_the_handbook(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$handbook = $this->handbook();
		$page     = $this->page( 'About us' );

		$this->assertTrue( MoveToHandbook::move( $page, $handbook ) );

		$this->assertSame( Handbook::POST_TYPE, get_post_type( $page ) );
		$this->assertSame( $handbook, Handbooks::for_post( $page ) );
	}

	/**
	 * The old address is remembered and answered with a permanent redirect.
	 * Without it, every link, bookmark and search result pointing at a page dies
	 * the day it becomes documentation.
	 *
	 * @return void
	 */
	public function test_the_old_address_is_remembered(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$handbook = $this->handbook();
		$page     = $this->page( 'About us' );

		$before = MoveToHandbook::path_of( $page );
		$this->assertNotSame( '', $before );

		MoveToHandbook::move( $page, $handbook );

		$this->assertSame( $before, (string) get_post_meta( $page, MoveToHandbook::META_MOVED_FROM, true ) );
		$this->assertNotSame( $before, MoveToHandbook::path_of( $page ), 'The address must actually have changed.' );
	}

	/**
	 * Children come along, always. A child left behind keeps a parent that is no
	 * longer a page, and its own address is built from that chain.
	 *
	 * @return void
	 */
	public function test_the_whole_subtree_comes_along(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$top   = $this->page( 'Top' );
		$child = $this->page( 'Child', $top );
		$grand = $this->page( 'Grandchild', $child );
		$other = $this->page( 'Unrelated' );

		$ids = MoveToHandbook::with_descendants( array( $top ) );

		$this->assertContains( $top, $ids );
		$this->assertContains( $child, $ids );
		$this->assertContains( $grand, $ids );
		$this->assertNotContains( $other, $ids );
	}

	/**
	 * A user who may not edit other people's posts moves nothing.
	 *
	 * @return void
	 */
	public function test_a_page_is_not_moved_without_the_capability(): void {
		$handbook = $this->handbook();
		$page     = $this->page( 'About us' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( MoveToHandbook::allowed() );
		$this->assertFalse( MoveToHandbook::move( $page, $handbook ) );
		$this->assertSame( 'page', get_post_type( $page ) );
	}

	/**
	 * A moved page and its subpage resolve at their new addresses without the
	 * rewrite rules being rebuilt. The handbook rules match any path below the
	 * handbook base, so nothing about a single page lives in them; this pins
	 * that, so the bulk move does not need to flush.
	 *
	 * @return void
	 */
	public function test_moved_pages_resolve_without_rebuilding_the_rules(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$handbook = $this->handbook();
		$top      = $this->page( 'Team' );
		$child    = $this->page( 'Onboarding', $top );
		$rules    = get_option( 'rewrite_rules' );

		foreach ( MoveToHandbook::with_descendants( array( $top ) ) as $id ) {
			$this->assertTrue( MoveToHandbook::move( $id, $handbook ) );
		}
		$this->assertSame( $rules, get_option( 'rewrite_rules' ), 'precondition: the rules were not rebuilt' );

		foreach ( array( $top, $child ) as $id ) {
			$this->go_to( (string) get_permalink( $id ) );
			$this->assertTrue( is_singular( Handbook::POST_TYPE ), 'Not a handbook page: ' . get_permalink( $id ) );
			$this->assertSame( $id, get_queried_object_id() );
		}
	}

	/**
	 * A handbook the user may not use moves nothing, and the notice says that
	 * rather than "no handbook was chosen". Up to 0.75.1 both cases got the
	 * same answer.
	 *
	 * @return void
	 */
	public function test_a_handbook_kept_from_the_user_is_named_as_the_reason(): void {
		$handbook = $this->handbook();
		update_term_meta( $handbook, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $handbook, Handbooks::META_ROLES, array( 'administrator' ) );
		$page = $this->page( 'About us' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_REQUEST['lh_handbook'] = (string) $handbook;
		$redirect                = ( new MoveToHandbook() )->handle_bulk_action( 'edit.php?post_type=page', 'lh_move_to_handbook', array( $page ) );
		unset( $_REQUEST['lh_handbook'] );

		$this->assertSame( 'page', get_post_type( $page ) );
		$this->assertStringContainsString( 'lh_handbook_refused=1', $redirect );

		$_REQUEST['lh_handbook_refused'] = '1';
		ob_start();
		( new MoveToHandbook() )->notice();
		$notice = (string) ob_get_clean();
		unset( $_REQUEST['lh_handbook_refused'] );

		$this->assertStringContainsString( 'kept from you', $notice );
		$this->assertStringNotContainsString( 'no handbook was chosen', $notice );
	}

	/**
	 * The redirect is looked up only on a site that has actually moved a page.
	 * Without that switch every 404 on every installation would ask the database
	 * whether some page used to live at that address, and on almost every
	 * installation the answer is no.
	 *
	 * @return void
	 */
	public function test_the_redirect_costs_nothing_before_the_first_move(): void {
		delete_option( 'living_handbook_moved_pages' );
		$this->assertFalse( (bool) get_option( 'living_handbook_moved_pages' ), 'The switch starts off.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		MoveToHandbook::move( $this->page( 'About us' ), $this->handbook() );

		$this->assertTrue( (bool) get_option( 'living_handbook_moved_pages' ), 'The first move turns it on.' );
	}

	/**
	 * The bulk dropdown holds one entry, not one per handbook. The handbook is
	 * chosen in a second control beside it, so the list of actions does not grow
	 * with every handbook a site creates.
	 *
	 * @return void
	 */
	public function test_the_bulk_dropdown_holds_one_entry(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->handbook();
		$this->handbook();

		$actions = ( new MoveToHandbook() )->add_bulk_actions( array( 'edit' => 'Edit' ) );

		$this->assertCount( 2, $actions, 'Two handbooks must still add exactly one action.' );
		$this->assertArrayHasKey( 'lh_move_to_handbook', $actions );
	}

	/**
	 * And no entry at all while there is no handbook to move into: an action
	 * that cannot be completed does not belong in the menu.
	 *
	 * @return void
	 */
	public function test_without_a_handbook_there_is_no_bulk_action(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$actions = ( new MoveToHandbook() )->add_bulk_actions( array( 'edit' => 'Edit' ) );

		$this->assertSame( array( 'edit' => 'Edit' ), $actions );
	}

	/**
	 * The second control offers every handbook and starts on an empty value, so
	 * the browser's own required validation refuses a submit with nothing
	 * chosen.
	 *
	 * @return void
	 */
	public function test_the_second_control_lists_the_handbooks(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$term = get_term( $this->handbook(), Handbooks::TAXONOMY );
		$this->assertInstanceOf( 'WP_Term', $term );

		ob_start();
		( new MoveToHandbook() )->render_handbook_select( 'page' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="lh_handbook"', $html );
		$this->assertStringContainsString( '<option value="">', $html );
		$this->assertStringContainsString( 'value="' . $term->term_id . '"', $html );

		ob_start();
		( new MoveToHandbook() )->render_handbook_select( 'post' );
		$this->assertSame( '', (string) ob_get_clean(), 'The control belongs to the page list only.' );
	}

	/**
	 * A manifest with one page.
	 *
	 * @return array<string, mixed>
	 */
	private function manifest(): array {
		return array(
			'format'   => 'living-handbook-bundle',
			'version'  => 1,
			'handbook' => array(
				'slug' => 'from-elsewhere',
				'name' => 'From elsewhere',
			),
			'pages'    => array(
				array(
					'key'     => 'welcome',
					'title'   => 'Welcome',
					'slug'    => 'welcome',
					'status'  => 'publish',
					'content' => '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->',
					'terms'   => array( 'handbook_topic' => array( 'onboarding' ) ),
					'meta'    => array(
						'last_reviewed'   => '2026-01-01',
						'review_interval' => 90,
					),
				),
			),
		);
	}

	/**
	 * Imported as pages: ordinary pages, no handbook, and always a draft even
	 * though the bundle says publish. A bundle from an internal handbook must
	 * not be published by the act of importing it.
	 *
	 * @return void
	 */
	public function test_a_bundle_can_be_imported_as_ordinary_draft_pages(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$report = ( new HandbookImport() )->import_manifest(
			$this->manifest(),
			static function ( string $file ) {
				unset( $file );
				return false;
			},
			HandbookImport::RULE_SKIP,
			0,
			true
		);

		$this->assertSame( 1, $report['created'] );

		$found = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'any',
				'name'        => 'welcome',
				'numberposts' => 1,
			)
		);
		$this->assertCount( 1, $found, 'The page was not created as a page.' );

		$page = $found[0];
		$this->assertSame( 'draft', $page->post_status, 'A page from a bundle is always a draft.' );
		$this->assertStringContainsString( 'Hello.', $page->post_content );
		$this->assertSame( 0, Handbooks::for_post( (int) $page->ID ), 'A page carries no handbook.' );
		$this->assertSame( '', (string) get_post_meta( (int) $page->ID, Metadata::REVIEWED, true ), 'Review data belongs to a handbook page.' );
	}

	/**
	 * A second run as ordinary pages finds the pages of the first one, so the
	 * run rules hold here too: skip leaves the page alone, update refreshes it,
	 * and neither makes a second draft.
	 *
	 * @return void
	 */
	public function test_a_second_import_as_pages_finds_the_first_ones_pages(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$run = function ( array $manifest, string $rule ): array {
			return ( new HandbookImport() )->import_manifest(
				$manifest,
				static function ( string $file ) {
					unset( $file );
					return false;
				},
				$rule,
				0,
				true
			);
		};

		$this->assertSame( 1, $run( $this->manifest(), HandbookImport::RULE_SKIP )['created'] );

		$again = $run( $this->manifest(), HandbookImport::RULE_SKIP );
		$this->assertSame( 0, $again['created'], 'Skip does not create a second draft.' );
		$this->assertSame( 1, $again['skipped'] );

		$changed                         = $this->manifest();
		$changed['pages'][0]['content'] = '<!-- wp:paragraph --><p>Changed.</p><!-- /wp:paragraph -->';
		$updated                         = $run( $changed, HandbookImport::RULE_UPDATE );
		$this->assertSame( 1, $updated['updated'] );
		$this->assertSame( 0, $updated['created'] );

		$pages = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);
		$this->assertCount( 1, $pages, 'One page, however often it is imported.' );
		$this->assertStringContainsString( 'Changed.', $pages[0]->post_content );
	}

	/**
	 * And without that switch nothing changes: the same bundle still becomes
	 * handbook pages with their handbook, their terms and their review data.
	 *
	 * @return void
	 */
	public function test_the_normal_import_is_untouched(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$report = ( new HandbookImport() )->import_manifest(
			$this->manifest(),
			static function ( string $file ) {
				unset( $file );
				return false;
			},
			HandbookImport::RULE_SKIP
		);

		$this->assertSame( 1, $report['created'] );

		$found = get_posts(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'any',
				'name'        => 'welcome',
				'numberposts' => 1,
			)
		);
		$this->assertCount( 1, $found );
		$this->assertGreaterThan( 0, Handbooks::for_post( (int) $found[0]->ID ) );
		$this->assertSame( '2026-01-01', (string) get_post_meta( (int) $found[0]->ID, Metadata::REVIEWED, true ) );
	}
}
