<?php
/**
 * The reading abilities (Konzept 04, E-0056).
 *
 * The point of these tests is the access rule. Every ability must hand out
 * exactly what the account may read on the site, nothing from a handbook it
 * may not read, nothing marked as excluded from AI access, nothing behind a
 * password, and the editorial abilities only to editors. A failure here is a
 * leak through the AI access.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Abilities\Abilities;
use LivingHandbook\Abilities\Pages;
use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Search\SearchLog;
use WP_Error;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Seven abilities, one access rule.
 */
final class AbilitiesTest extends WP_UnitTestCase {

	/**
	 * Skip where the Abilities API does not exist (WordPress 6.8).
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API arrives with WordPress 6.9.' );
		}
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . SearchLog::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the test's own table state.
		update_option( SearchLog::OPTION_ENABLED, '1' );
	}

	/**
	 * A handbook.
	 *
	 * @param string          $visibility Visibility.
	 * @param array<int, int> $users      Named readers, for a restricted one.
	 * @return int
	 */
	private function handbook( string $visibility, array $users = array() ): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => Handbooks::TAXONOMY,
				'name'     => 'Handbook ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );
		if ( array() !== $users ) {
			update_term_meta( $term_id, Handbooks::META_USERS, $users );
		}
		return $term_id;
	}

	/**
	 * A published page.
	 *
	 * @param int                  $term_id Handbook.
	 * @param string               $title   Title.
	 * @param array<string, mixed> $args    More post fields.
	 * @return int
	 */
	private function page( int $term_id, string $title, array $args = array() ): int {
		$post_id = (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'    => Handbook::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_excerpt' => '',
					'post_content' => '<!-- wp:paragraph --><p>Inhalt von ' . $title . '</p><!-- /wp:paragraph -->',
				),
				$args
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY );
		return $post_id;
	}

	/**
	 * Runs an ability as the current user.
	 *
	 * @param string               $slug  Ability without namespace.
	 * @param array<string, mixed> $input Input.
	 * @return mixed
	 */
	private function run_ability( string $slug, ?array $input = null ) {
		$ability = wp_get_ability( Abilities::CATEGORY . '/' . $slug );
		$this->assertNotNull( $ability, $slug . ' is not registered.' );
		return $ability->execute( $input );
	}

	/**
	 * A user with a role, made current.
	 *
	 * @param string $role Role.
	 * @return int
	 */
	private function as_user( string $role ): int {
		$user_id = (int) self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	/**
	 * All seven are registered, in the category, with the same exposure and
	 * annotations, and visible to the MCP Adapter's rule.
	 *
	 * @return void
	 */
	public function test_the_abilities_are_registered_and_exposed(): void {
		$names = array( 'list-handbooks', 'get-structure', 'search', 'get-page', 'list-due-pages', 'list-feedback', 'list-failed-searches' );
		foreach ( $names as $slug ) {
			$ability = wp_get_ability( 'living-handbook/' . $slug );
			$this->assertNotNull( $ability, $slug );
			$this->assertSame( Abilities::CATEGORY, $ability->get_category() );

			$meta = $ability->get_meta();
			$this->assertTrue( $meta['show_in_rest'], $slug );
			$this->assertTrue( $meta['public'], $slug );
			// The MCP Adapter's own rule (McpAbilityExposure, 0.7.1): mcp.public wins.
			$this->assertTrue( $meta['mcp']['public'], $slug );
			$this->assertTrue( $meta['annotations']['readonly'], $slug );
			$this->assertFalse( $meta['annotations']['destructive'], $slug );
		}
		$this->assertNotNull( wp_get_ability_category( Abilities::CATEGORY ) );
	}

	/**
	 * The REST API of the Abilities API lists them for a signed-in user.
	 *
	 * @return void
	 */
	public function test_the_rest_api_lists_them(): void {
		$this->as_user( 'subscriber' );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' ) );
		$names    = array_column( (array) $response->get_data(), 'name' );

		$this->assertContains( 'living-handbook/search', $names );
		$this->assertContains( 'living-handbook/get-page', $names );

		// Read-only, so the run route takes GET, with the input as a parameter.
		$term_id = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$run     = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/living-handbook/list-handbooks/run' );
		$result  = rest_do_request( $run );
		$this->assertSame( 200, $result->get_status(), (string) wp_json_encode( $result->get_data() ) );
		$this->assertContains( $term_id, array_column( $result->get_data()['handbooks'], 'id' ) );
	}

	/**
	 * A guest runs none of them.
	 *
	 * @return void
	 */
	public function test_a_guest_runs_nothing(): void {
		wp_set_current_user( 0 );
		$this->handbook( Handbooks::VISIBILITY_PUBLIC );

		$result = $this->run_ability( 'list-handbooks' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * A subscriber sees the public and the members handbook, not a restricted
	 * one it is not named in; a named one it sees.
	 *
	 * @return void
	 */
	public function test_list_handbooks_follows_the_access_rule(): void {
		$user       = $this->as_user( 'subscriber' );
		$public     = $this->handbook( Handbooks::VISIBILITY_PUBLIC );
		$members    = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$restricted = $this->handbook( Handbooks::VISIBILITY_RESTRICTED );
		$named      = $this->handbook( Handbooks::VISIBILITY_RESTRICTED, array( $user ) );
		$this->page( $public, 'Eins' );
		$this->page( $public, 'Zwei' );
		$this->page( $public, 'Ausgeschlossen', array( 'meta_input' => array( Metadata::AI_EXCLUDE => '1' ) ) );

		$result = $this->run_ability( 'list-handbooks' );
		$ids    = array_column( $result['handbooks'], 'id' );

		$this->assertContains( $public, $ids );
		$this->assertContains( $members, $ids );
		$this->assertContains( $named, $ids );
		$this->assertNotContains( $restricted, $ids );
		$counts = array_column( $result['handbooks'], 'pages', 'id' );
		$this->assertSame( 2, $counts[ $public ] );
	}

	/**
	 * The structure leaves out excluded and password pages, moves their
	 * children up, and refuses a handbook the person may not read with the
	 * same error as a missing one.
	 *
	 * @return void
	 */
	public function test_get_structure_keeps_the_rule_and_the_tree(): void {
		$this->as_user( 'subscriber' );
		$term_id  = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$top      = $this->page( $term_id, 'Oben', array( 'menu_order' => 1 ) );
		$excluded = $this->page(
			$term_id,
			'Personalliste',
			array(
				'post_parent' => $top,
				'meta_input'  => array( Metadata::AI_EXCLUDE => '1' ),
			)
		);
		$child    = $this->page( $term_id, 'Darunter', array( 'post_parent' => $excluded ) );
		$this->page( $term_id, 'Geschützt', array( 'post_password' => 'x' ) );

		$result = $this->run_ability( 'get-structure', array( 'handbook' => $term_id ) );

		$this->assertSame( array( 'Oben', 'Darunter' ), array_column( $result['pages'], 'title' ) );
		$by_id = array_column( $result['pages'], null, 'id' );
		$this->assertSame( $top, $by_id[ $child ]['parent'] );
		$this->assertSame( 2, $by_id[ $child ]['depth'] );
		$this->assertArrayHasKey( 'review', $by_id[ $top ] );
		$this->assertNotSame( '', $by_id[ $top ]['url'] );

		$shallow = $this->run_ability(
			'get-structure',
			array(
				'handbook' => $term_id,
				'depth'    => 1,
			)
		);
		$this->assertSame( array( 'Oben' ), array_column( $shallow['pages'], 'title' ) );

		$restricted = $this->handbook( Handbooks::VISIBILITY_RESTRICTED );
		$denied     = $this->run_ability( 'get-structure', array( 'handbook' => $restricted ) );
		$missing    = $this->run_ability( 'get-structure', array( 'handbook' => 'gibt-es-nicht' ) );
		$this->assertSame( Pages::NOT_FOUND, $denied->get_error_code() );
		$this->assertSame( $missing->get_error_code(), $denied->get_error_code() );
		$this->assertSame( $missing->get_error_message(), $denied->get_error_message() );
	}

	/**
	 * The search finds readable pages only, with source and review, and counts
	 * an empty search with source "ai" for each handbook searched.
	 *
	 * @return void
	 */
	public function test_search_keeps_the_rule_and_counts_misses(): void {
		$this->as_user( 'subscriber' );
		$members    = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$restricted = $this->handbook( Handbooks::VISIBILITY_RESTRICTED );
		$this->page( $members, 'Spesenregel' );
		$this->page( $members, 'Spesen privat', array( 'meta_input' => array( Metadata::AI_EXCLUDE => '1' ) ) );
		$this->page( $members, 'Spesen geschützt', array( 'post_password' => 'x' ) );
		$this->page( $restricted, 'Spesen geheim' );

		$result = $this->run_ability( 'search', array( 'query' => 'Spesen' ) );

		$this->assertSame( array( 'Spesenregel' ), array_column( $result['results'], 'title' ) );
		$this->assertSame( 'none', $result['results'][0]['review']['status'] );
		$this->assertStringContainsString( 'Inhalt von Spesenregel', $result['results'][0]['excerpt'] );

		$this->run_ability( 'search', array( 'query' => 'Reisekostenpauschale' ) );
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT term_id, source, term FROM ' . SearchLog::table(), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- reading the table is the point.
		$this->assertContains(
			array(
				'term_id' => (string) $members,
				'source'  => 'ai',
				'term'    => 'reisekostenpauschale',
			),
			$rows
		);
		$this->assertNotContains( (string) $restricted, array_column( $rows, 'term_id' ) );
	}

	/**
	 * A page: its text with structure, its source, and the same refusal for a
	 * page that is missing, excluded, behind a password or in a handbook the
	 * person may not read.
	 *
	 * @return void
	 */
	public function test_get_page_returns_text_and_refuses_the_rest(): void {
		$this->as_user( 'subscriber' );
		$members    = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$restricted = $this->handbook( Handbooks::VISIBILITY_RESTRICTED );
		$page       = $this->page(
			$members,
			'Ablauf',
			array( 'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Schritte</h2><!-- /wp:heading --><!-- wp:list --><ul class="wp-block-list"><li>Antrag</li><li>Prüfung</li></ul><!-- /wp:list -->' )
		);

		$result = $this->run_ability( 'get-page', array( 'id' => $page ) );
		$this->assertIsArray( $result );
		$this->assertStringContainsString( "## Schritte\n\n- Antrag\n- Prüfung", $result['content'] );
		$this->assertSame( 'Schritte Antrag Prüfung', $result['excerpt'] );
		$this->assertSame( get_permalink( $page ), $result['url'] );
		$this->assertFalse( $result['truncated'] );

		$by_url = $this->run_ability( 'get-page', array( 'url' => get_permalink( $page ) ) );
		$this->assertSame( $page, $by_url['id'] );

		foreach (
			array(
				$this->page( $members, 'Privat', array( 'meta_input' => array( Metadata::AI_EXCLUDE => '1' ) ) ),
				$this->page( $members, 'Passwort', array( 'post_password' => 'x' ) ),
				$this->page( $restricted, 'Geheim' ),
				$this->page( $members, 'Entwurf', array( 'post_status' => 'draft' ) ),
				999999,
			) as $id
		) {
			$refused = $this->run_ability( 'get-page', array( 'id' => $id ) );
			$this->assertInstanceOf( WP_Error::class, $refused, 'Page ' . $id );
			$this->assertSame( Pages::NOT_FOUND, $refused->get_error_code() );
		}
	}

	/**
	 * Outside a public handbook, the addresses of uploads are left out.
	 *
	 * @return void
	 */
	public function test_upload_addresses_are_hidden_outside_public_handbooks(): void {
		$this->as_user( 'subscriber' );
		$upload  = wp_get_upload_dir()['baseurl'] . '/2026/11/plan.pdf';
		$content = '<!-- wp:paragraph --><p><a href="' . $upload . '">Plan</a> und <a href="https://example.org/x">extern</a></p><!-- /wp:paragraph -->';

		$public  = $this->page( $this->handbook( Handbooks::VISIBILITY_PUBLIC ), 'Offen', array( 'post_content' => $content ) );
		$members = $this->page( $this->handbook( Handbooks::VISIBILITY_MEMBERS ), 'Intern', array( 'post_content' => $content ) );

		$this->assertStringContainsString( '[Plan](' . $upload . ')', $this->run_ability( 'get-page', array( 'id' => $public ) )['content'] );
		$hidden = $this->run_ability( 'get-page', array( 'id' => $members ) )['content'];
		$this->assertStringNotContainsString( $upload, $hidden );
		$this->assertStringContainsString( 'Plan und [extern](https://example.org/x)', $hidden );
	}

	/**
	 * The editorial abilities refuse a subscriber and serve an editor.
	 *
	 * @return void
	 */
	public function test_the_editorial_abilities_need_the_editors_right(): void {
		$this->as_user( 'subscriber' );
		foreach ( array( 'list-due-pages', 'list-feedback', 'list-failed-searches' ) as $slug ) {
			$result = $this->run_ability( $slug );
			$this->assertInstanceOf( WP_Error::class, $result, $slug );
		}

		$this->as_user( 'editor' );
		foreach ( array( 'list-due-pages', 'list-feedback', 'list-failed-searches' ) as $slug ) {
			$this->assertIsArray( $this->run_ability( $slug ), $slug );
		}
	}

	/**
	 * Due, overdue, unreviewed and unassigned pages, the most urgent first.
	 *
	 * @return void
	 */
	public function test_list_due_pages(): void {
		$editor  = $this->as_user( 'editor' );
		$term_id = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$today   = wp_date( 'Y-m-d' );
		$long    = wp_date( 'Y-m-d', time() - 400 * DAY_IN_SECONDS );
		$recent  = wp_date( 'Y-m-d', time() - 120 * DAY_IN_SECONDS );

		$this->page( $term_id, 'Aktuell', array( 'meta_input' => array( Metadata::REVIEWED => $today, Metadata::INTERVAL => 180, Metadata::REVIEWER => $editor ) ) );
		$this->page( $term_id, 'Ohne Prüfer', array( 'meta_input' => array( Metadata::REVIEWED => $today, Metadata::INTERVAL => 180 ) ) );
		$this->page( $term_id, 'Fällig', array( 'meta_input' => array( Metadata::REVIEWED => $recent, Metadata::INTERVAL => 90, Metadata::REVIEWER => $editor ) ) );
		$this->page( $term_id, 'Überfällig', array( 'meta_input' => array( Metadata::REVIEWED => $long, Metadata::INTERVAL => 90, Metadata::REVIEWER => $editor ) ) );
		$this->page( $term_id, 'Nie geprüft', array( 'meta_input' => array( Metadata::REVIEWER => $editor ) ) );

		$result = $this->run_ability( 'list-due-pages', array( 'handbook' => $term_id ) );

		$this->assertSame( 5, $result['total'] );
		$this->assertSame( array( 'Überfällig', 'Fällig', 'Nie geprüft', 'Ohne Prüfer' ), array_column( $result['pages'], 'title' ) );
		$this->assertSame( array( 'no_reviewer' ), $result['pages'][3]['problems'] );
		$this->assertNotSame( '', $result['pages'][0]['reviewer'] );

		$strict = $this->run_ability(
			'list-due-pages',
			array(
				'handbook'         => $term_id,
				'without_reviewer' => false,
			)
		);
		$this->assertSame( array( 'Überfällig', 'Fällig', 'Nie geprüft' ), array_column( $strict['pages'], 'title' ) );
	}

	/**
	 * Unhelpful pages by share, with a minimum of votes.
	 *
	 * @return void
	 */
	public function test_list_feedback(): void {
		$this->as_user( 'editor' );
		$term_id = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$this->page( $term_id, 'Schlecht', array( 'meta_input' => array( Feedback::YES => 1, Feedback::NO => 9 ) ) );
		$this->page( $term_id, 'Gemischt', array( 'meta_input' => array( Feedback::YES => 5, Feedback::NO => 5 ) ) );
		$this->page( $term_id, 'Zu wenig', array( 'meta_input' => array( Feedback::YES => 0, Feedback::NO => 2 ) ) );
		$this->page( $term_id, 'Gut', array( 'meta_input' => array( Feedback::YES => 10 ) ) );

		$result = $this->run_ability( 'list-feedback', array( 'handbook' => $term_id ) );

		$this->assertSame( array( 'Schlecht', 'Gemischt' ), array_column( $result['pages'], 'title' ) );
		$this->assertSame( 0.9, $result['pages'][0]['votes']['not_helpful_share'] );
		$this->assertSame( 10, $result['pages'][0]['votes']['total'] );
	}

	/**
	 * Failed searches of the readable handbooks only.
	 *
	 * An editor reads a members handbook but not a restricted one it is not
	 * named in (E-0075), so the searches there are not shown to it.
	 *
	 * @return void
	 */
	public function test_list_failed_searches_keeps_to_readable_handbooks(): void {
		$members    = $this->handbook( Handbooks::VISIBILITY_MEMBERS );
		$restricted = $this->handbook( Handbooks::VISIBILITY_RESTRICTED );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		SearchLog::record( $members, 'Parkplatz' );
		SearchLog::record( $members, 'Parkplatz' );
		SearchLog::record( $restricted, 'Kündigung' );

		$this->as_user( 'editor' );
		$result = $this->run_ability( 'list-failed-searches' );

		$this->assertTrue( $result['enabled'] );
		$this->assertSame( array( 'parkplatz' ), array_column( $result['searches'], 'term' ) );
		$this->assertSame( 2, $result['searches'][0]['hits'] );
		$this->assertSame( $members, $result['searches'][0]['handbook']['id'] );
	}
}
