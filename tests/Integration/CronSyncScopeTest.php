<?php
/**
 * The scheduled sync has to cover internal handbooks too.
 *
 * The read filters in AccessController protect the display path. They used to
 * catch the sync as well: cron runs with no user and outside wp-admin, so the
 * lookup in run_sync() was narrowed to public handbooks and every members or
 * restricted handbook was silently never synced. These tests pin down that the
 * maintenance lookup sees all handbooks while a plain front-end query does not.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * Scope of the scheduled sync across the three handbook visibilities.
 */
final class CronSyncScopeTest extends WP_UnitTestCase {

	/**
	 * Source URLs the sync asked for during a run.
	 *
	 * @var array<int, string>
	 */
	private array $requested = array();

	/**
	 * Short-circuit every outbound request and record the URL.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->requested = array();
		add_filter( 'pre_http_request', array( $this, 'record_request' ), 10, 3 );
	}

	/**
	 * Remove the request filter.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'record_request' ), 10 );
		parent::tear_down();
	}

	/**
	 * Record a request and answer it with a small Markdown document.
	 *
	 * @param mixed                $preempt Short-circuit value.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param string               $url     Requested URL.
	 * @return array<string, mixed>
	 */
	public function record_request( $preempt, $args, $url ): array {
		unset( $preempt, $args );
		$this->requested[] = (string) $url;
		return array(
			'headers'  => array(),
			'body'     => "# Synced\n\nBody text.\n",
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Create a handbook with the given visibility holding one GitHub page.
	 *
	 * @param string $visibility Visibility value.
	 * @param string $slug       Slug used in the source URL, to tell pages apart.
	 * @return string The source URL of the created page.
	 */
	private function make_github_page( string $visibility, string $slug ): string {
		$term = wp_insert_term( 'Handbook ' . $slug, Handbooks::TAXONOMY );
		$this->assertIsArray( $term );
		update_term_meta( (int) $term['term_id'], Handbooks::META_VISIBILITY, $visibility );

		$url     = 'https://raw.githubusercontent.com/example/repo/main/' . $slug . '.md';
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Page ' . $slug,
			)
		);
		wp_set_object_terms( $page_id, array( (int) $term['term_id'] ), Handbooks::TAXONOMY );
		update_post_meta( $page_id, GitSync::META_SOURCE, GitSync::SOURCE_GITHUB );
		update_post_meta( $page_id, GitSync::META_URL, $url );
		return $url;
	}

	/**
	 * A scheduled run pulls pages from public, members and restricted handbooks.
	 *
	 * @return void
	 */
	public function test_scheduled_sync_covers_every_visibility(): void {
		$public     = $this->make_github_page( Handbooks::VISIBILITY_PUBLIC, 'public-one' );
		$members    = $this->make_github_page( Handbooks::VISIBILITY_MEMBERS, 'members-one' );
		$restricted = $this->make_github_page( Handbooks::VISIBILITY_RESTRICTED, 'restricted-one' );

		wp_set_current_user( 0 );
		( new GitSync() )->run_sync();

		$this->assertContains( $public, $this->requested, 'The sync skipped a page in a public handbook.' );
		$this->assertContains( $members, $this->requested, 'The sync skipped a page in a members handbook.' );
		$this->assertContains( $restricted, $this->requested, 'The sync skipped a page in a restricted handbook.' );
	}

	/**
	 * The opt-out is limited to the marked lookup: an ordinary front-end query
	 * for the same pages still only returns the public handbook.
	 *
	 * @return void
	 */
	public function test_front_end_query_stays_filtered(): void {
		$this->make_github_page( Handbooks::VISIBILITY_PUBLIC, 'public-two' );
		$this->make_github_page( Handbooks::VISIBILITY_MEMBERS, 'members-two' );

		wp_set_current_user( 0 );
		$titles = wp_list_pluck(
			(array) get_posts(
				array(
					'post_type'      => Handbook::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				)
			),
			'post_title'
		);

		$this->assertContains( 'Page public-two', $titles );
		$this->assertNotContains( 'Page members-two', $titles, 'A guest must not see a page in a members handbook.' );
	}
}
