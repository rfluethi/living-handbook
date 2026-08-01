<?php
/**
 * The read channels and write paths hardened after the 0.52.0 review.
 *
 * Covers the oEmbed channel (which is not the post query and was open), the
 * capability of the app-handbook import (which publishes straight away), the
 * per-user cache key of the area cards, and the meta sanitizing that a REST
 * write would otherwise bypass.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Frontend\Cards;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Access channels and write paths that the post-query filters do not cover.
 */
final class HardeningTest extends WP_UnitTestCase {

	/**
	 * Create a handbook term with the given visibility.
	 *
	 * @param string $visibility Visibility value.
	 * @return int Term id.
	 */
	private function make_handbook( string $visibility ): int {
		$term = wp_insert_term( 'HB ' . $visibility . wp_rand(), Handbooks::TAXONOMY );
		$this->assertIsArray( $term );
		update_term_meta( (int) $term['term_id'], Handbooks::META_VISIBILITY, $visibility );
		return (int) $term['term_id'];
	}

	/**
	 * Create a published handbook page inside a handbook.
	 *
	 * @param int    $term_id Handbook term id.
	 * @param string $title   Page title.
	 * @return int Post id.
	 */
	private function make_page( int $term_id, string $title = 'Internal page' ): int {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		wp_set_object_terms( $page_id, array( $term_id ), Handbooks::TAXONOMY );
		return (int) $page_id;
	}

	/**
	 * The oEmbed payload of a page in a members handbook is emptied for a guest.
	 *
	 * Asserted on the filter, not on get_oembed_response_data(): since WordPress
	 * 6.8 core already refuses a type that is not embeddable, so the end-to-end
	 * call says nothing about our own guard on 6.7, the version the plugin still
	 * supports.
	 *
	 * @return void
	 */
	public function test_oembed_data_is_emptied_for_an_internal_page(): void {
		$page_id = $this->make_page( $this->make_handbook( Handbooks::VISIBILITY_MEMBERS ), 'Salary bands' );
		$payload = array(
			'title'       => 'Salary bands',
			'author_name' => 'Someone',
		);

		wp_set_current_user( 0 );
		$data = apply_filters( 'oembed_response_data', $payload, get_post( $page_id ), 600, 400 );

		$this->assertEmpty( $data, 'oEmbed must not describe a page of an internal handbook.' );
	}

	/**
	 * The same payload passes through untouched for a public handbook.
	 *
	 * @return void
	 */
	public function test_oembed_data_survives_for_a_public_page(): void {
		$page_id = $this->make_page( $this->make_handbook( Handbooks::VISIBILITY_PUBLIC ), 'Public page' );
		$payload = array( 'title' => 'Public page' );

		wp_set_current_user( 0 );
		$data = apply_filters( 'oembed_response_data', $payload, get_post( $page_id ), 600, 400 );

		$this->assertSame( 'Public page', $data['title'] ?? '' );
	}

	/**
	 * The oEmbed lookup does not resolve a URL of an internal page for a guest.
	 *
	 * @return void
	 */
	public function test_oembed_lookup_returns_no_post_for_a_guest(): void {
		$page_id = $this->make_page( $this->make_handbook( Handbooks::VISIBILITY_RESTRICTED ) );

		wp_set_current_user( 0 );
		$resolved = apply_filters( 'oembed_request_post_id', $page_id, (string) get_permalink( $page_id ) );

		$this->assertSame( 0, (int) $resolved );
	}

	/**
	 * A contributor may not load the app handbook: that path publishes at once.
	 *
	 * @return void
	 */
	public function test_contributor_cannot_load_the_app_handbook(): void {
		$contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		wp_set_current_user( (int) $contributor );

		$request = new WP_REST_Request( 'POST', '/living-handbook/v1/import-github' );
		$request->set_param( 'app_handbook', 1 );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status(), 'A contributor must not publish through the app-handbook import.' );
	}

	/**
	 * Two viewers with different visibility do not share one area-card cache.
	 *
	 * @return void
	 */
	public function test_area_cards_cache_key_is_scoped_to_the_viewer(): void {
		$public_term = $this->make_handbook( Handbooks::VISIBILITY_PUBLIC );
		$this->make_handbook( Handbooks::VISIBILITY_RESTRICTED );

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( (int) $editor );
		$editor_key = Cards::areas_cache_key( $public_term );

		wp_set_current_user( 0 );
		$guest_key = Cards::areas_cache_key( $public_term );

		$this->assertNotSame(
			$editor_key,
			$guest_key,
			'An editor and a guest must not share one cached copy of the area cards.'
		);
	}

	/**
	 * A review date written straight to the meta is kept to the expected format.
	 *
	 * @return void
	 */
	public function test_review_date_meta_is_sanitized(): void {
		$page_id = $this->make_page( $this->make_handbook( Handbooks::VISIBILITY_PUBLIC ) );

		update_post_meta( $page_id, Metadata::REVIEWED, 'next tuesday' );
		$this->assertSame( '', get_post_meta( $page_id, Metadata::REVIEWED, true ) );

		update_post_meta( $page_id, Metadata::REVIEWED, '2026-07-04' );
		$this->assertSame( '2026-07-04', get_post_meta( $page_id, Metadata::REVIEWED, true ) );
	}

	/**
	 * The heading depth stays inside the range the table of contents renders.
	 *
	 * @return void
	 */
	public function test_toc_depth_meta_is_clamped(): void {
		$page_id = $this->make_page( $this->make_handbook( Handbooks::VISIBILITY_PUBLIC ) );

		update_post_meta( $page_id, Metadata::TOC_DEPTH, 999 );
		$this->assertSame( 0, (int) get_post_meta( $page_id, Metadata::TOC_DEPTH, true ) );

		update_post_meta( $page_id, Metadata::TOC_DEPTH, 3 );
		$this->assertSame( 3, (int) get_post_meta( $page_id, Metadata::TOC_DEPTH, true ) );
	}
}
