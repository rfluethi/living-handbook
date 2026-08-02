<?php
/**
 * Saving who may read a handbook.
 *
 * These three term meta fields are the whole access model: every check in
 * AccessController reads them, and the tests around that controller assume they
 * hold what the form saved. This is the other half, the writing, and it is the
 * half where a mistake widens access instead of narrowing it: a value that is
 * not understood must fall back to members, never to public, and a request
 * without a nonce or without the capability must change nothing at all.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\HandbookAdmin;
use LivingHandbook\Handbook\Handbooks;
use WP_UnitTestCase;

/**
 * The handbook access form.
 */
final class HandbookAdminTest extends WP_UnitTestCase {

	/**
	 * Clear the request between tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * A handbook to save into.
	 *
	 * @param string $name Handbook name.
	 * @return int Term id.
	 */
	private function handbook( string $name = 'A handbook' ): int {
		$term = wp_insert_term( $name, Handbooks::TAXONOMY );
		$this->assertIsArray( $term );
		return (int) $term['term_id'];
	}

	/**
	 * Fill $_POST as the form would, with a valid nonce.
	 *
	 * @param array<string, mixed> $fields Form fields.
	 * @return void
	 */
	private function submit( array $fields ): void {
		$_POST = array_merge(
			array( 'living_handbook_access_nonce' => wp_create_nonce( 'living_handbook_access' ) ),
			$fields
		);
	}

	/**
	 * Sign in as someone who may manage handbooks.
	 *
	 * @return void
	 */
	private function as_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * The ordinary case: the three fields are stored as submitted.
	 *
	 * @return void
	 */
	public function test_a_valid_submission_stores_the_access_configuration(): void {
		$this->as_admin();
		$handbook = $this->handbook();
		$user     = self::factory()->user->create( array( 'user_login' => 'anna' ) );

		$this->submit(
			array(
				'living_handbook_visibility' => Handbooks::VISIBILITY_RESTRICTED,
				'living_handbook_roles'      => array( 'editor', 'author' ),
				'living_handbook_users_raw'  => 'anna',
			)
		);
		( new HandbookAdmin() )->save( $handbook );

		$this->assertSame( Handbooks::VISIBILITY_RESTRICTED, get_term_meta( $handbook, Handbooks::META_VISIBILITY, true ) );
		$this->assertSame( array( 'editor', 'author' ), get_term_meta( $handbook, Handbooks::META_ROLES, true ) );
		$this->assertSame( array( $user ), get_term_meta( $handbook, Handbooks::META_USERS, true ) );
	}

	/**
	 * A visibility the plugin does not know falls back to members, the closed
	 * side. Falling back to public would open a handbook because of a typo.
	 *
	 * @return void
	 */
	public function test_an_unknown_visibility_falls_back_to_members(): void {
		$this->as_admin();
		$handbook = $this->handbook();

		$this->submit( array( 'living_handbook_visibility' => 'everyone-please' ) );
		( new HandbookAdmin() )->save( $handbook );

		$this->assertSame( Handbooks::VISIBILITY_MEMBERS, get_term_meta( $handbook, Handbooks::META_VISIBILITY, true ) );
	}

	/**
	 * No visibility field at all is the same case: members, not public.
	 *
	 * @return void
	 */
	public function test_a_missing_visibility_falls_back_to_members(): void {
		$this->as_admin();
		$handbook = $this->handbook();

		$this->submit( array() );
		( new HandbookAdmin() )->save( $handbook );

		$this->assertSame( Handbooks::VISIBILITY_MEMBERS, get_term_meta( $handbook, Handbooks::META_VISIBILITY, true ) );
	}

	/**
	 * Without a valid nonce nothing is written. An admin screen fires the save
	 * hook on other occasions too, and an accidental run must not reset a
	 * restricted handbook to the default.
	 *
	 * @return void
	 */
	public function test_without_a_nonce_nothing_is_written(): void {
		$this->as_admin();
		$handbook = $this->handbook();
		update_term_meta( $handbook, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );

		$_POST = array( 'living_handbook_visibility' => Handbooks::VISIBILITY_PUBLIC );
		( new HandbookAdmin() )->save( $handbook );

		$this->assertSame( Handbooks::VISIBILITY_RESTRICTED, get_term_meta( $handbook, Handbooks::META_VISIBILITY, true ) );

		$_POST = array(
			'living_handbook_access_nonce' => 'not a nonce',
			'living_handbook_visibility'   => Handbooks::VISIBILITY_PUBLIC,
		);
		( new HandbookAdmin() )->save( $handbook );

		$this->assertSame( Handbooks::VISIBILITY_RESTRICTED, get_term_meta( $handbook, Handbooks::META_VISIBILITY, true ) );
	}

	/**
	 * A valid nonce is not enough: whoever saves must be allowed to manage the
	 * vocabulary. A nonce travels in a page an author may well have open.
	 *
	 * @return void
	 */
	public function test_without_the_capability_nothing_is_written(): void {
		$this->as_admin();
		$handbook = $this->handbook();
		update_term_meta( $handbook, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->submit( array( 'living_handbook_visibility' => Handbooks::VISIBILITY_PUBLIC ) );
		( new HandbookAdmin() )->save( $handbook );

		$this->assertSame( Handbooks::VISIBILITY_RESTRICTED, get_term_meta( $handbook, Handbooks::META_VISIBILITY, true ) );
	}

	/**
	 * Only roles this site has are stored. A submitted role that does not exist
	 * would sit in the meta forever and match nobody, or match somebody later.
	 *
	 * @return void
	 */
	public function test_only_roles_this_site_has_are_stored(): void {
		$this->as_admin();
		$handbook = $this->handbook();

		$this->submit(
			array(
				'living_handbook_visibility' => Handbooks::VISIBILITY_RESTRICTED,
				'living_handbook_roles'      => array( 'editor', 'chief-of-everything' ),
			)
		);
		( new HandbookAdmin() )->save( $handbook );

		$this->assertSame( array( 'editor' ), get_term_meta( $handbook, Handbooks::META_ROLES, true ) );
	}

	/**
	 * People are given by login or by id, unknown ones are dropped, and the same
	 * person twice stays one entry.
	 *
	 * @return void
	 */
	public function test_users_are_resolved_by_login_and_by_id(): void {
		$this->as_admin();
		$handbook = $this->handbook();
		$anna     = self::factory()->user->create( array( 'user_login' => 'anna' ) );
		$bruno    = self::factory()->user->create( array( 'user_login' => 'bruno' ) );

		$this->submit(
			array(
				'living_handbook_visibility' => Handbooks::VISIBILITY_RESTRICTED,
				'living_handbook_users_raw'  => 'anna, ' . $bruno . ' , anna, niemand, 999999',
			)
		);
		( new HandbookAdmin() )->save( $handbook );

		$this->assertSame( array( $anna, $bruno ), get_term_meta( $handbook, Handbooks::META_USERS, true ) );
	}

	/**
	 * Saving an empty list clears the previous one: taking a person off the list
	 * has to take effect.
	 *
	 * @return void
	 */
	public function test_an_empty_list_clears_the_previous_one(): void {
		$this->as_admin();
		$handbook = $this->handbook();
		$anna     = self::factory()->user->create( array( 'user_login' => 'anna' ) );

		$this->submit(
			array(
				'living_handbook_visibility' => Handbooks::VISIBILITY_RESTRICTED,
				'living_handbook_roles'      => array( 'editor' ),
				'living_handbook_users_raw'  => 'anna',
			)
		);
		$admin = new HandbookAdmin();
		$admin->save( $handbook );
		$this->assertSame( array( $anna ), get_term_meta( $handbook, Handbooks::META_USERS, true ) );

		$this->submit( array( 'living_handbook_visibility' => Handbooks::VISIBILITY_RESTRICTED ) );
		$admin->save( $handbook );

		$this->assertSame( array(), get_term_meta( $handbook, Handbooks::META_ROLES, true ) );
		$this->assertSame( array(), get_term_meta( $handbook, Handbooks::META_USERS, true ) );
	}

	/**
	 * What the handbook list shows in its Access column, including a handbook
	 * that was never saved: it reads as members, the same default the save uses.
	 *
	 * @return void
	 */
	public function test_the_access_column_describes_the_configuration(): void {
		$this->as_admin();
		$admin    = new HandbookAdmin();
		$handbook = $this->handbook();

		$this->assertNotSame( '', $admin->access_column_value( '', 'lh_access', $handbook ), 'A handbook nobody configured is not blank.' );

		update_term_meta( $handbook, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_PUBLIC );
		$public = $admin->access_column_value( '', 'lh_access', $handbook );

		update_term_meta( $handbook, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_RESTRICTED );
		update_term_meta( $handbook, Handbooks::META_ROLES, array( 'editor' ) );
		update_term_meta( $handbook, Handbooks::META_USERS, array( self::factory()->user->create() ) );
		$restricted = $admin->access_column_value( '', 'lh_access', $handbook );

		$this->assertNotSame( $public, $restricted, 'Public and restricted must not read the same.' );
		$this->assertStringContainsString( '1', $restricted, 'The number of people is part of the summary.' );
	}

	/**
	 * Another column is none of its business and is handed back untouched.
	 *
	 * @return void
	 */
	public function test_another_column_is_left_alone(): void {
		$this->as_admin();
		$handbook = $this->handbook();

		$this->assertSame(
			'whatever was there',
			( new HandbookAdmin() )->access_column_value( 'whatever was there', 'description', $handbook )
		);
	}
}
