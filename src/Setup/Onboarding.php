<?php
/**
 * First-run setup: give a fresh install something to look at.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Setup;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes the plugin do something visible after activation.
 *
 * Without this, a fresh install looks broken: the post type archive is switched
 * off on purpose, so there is no overview until someone creates a page with the
 * overview block, and access is fail-closed, so pages without a handbook stay
 * invisible. Both are deliberate, but neither is guessable. So activation
 * creates the overview page once, a dismissible notice says where it is and
 * what to do next, and the page list warns about pages that are invisible
 * because they have no handbook.
 *
 * No content is shipped or created beyond that one structural page. A handbook
 * with real pages is imported from Markdown or GitHub on the import screen.
 */
final class Onboarding {

	/**
	 * Option holding the id of the overview page created on activation. It is
	 * also the marker that the decision was made: once it exists, no page is
	 * created again, so a page the user deleted is not resurrected. The value
	 * is 0 when nothing was created (a suitable page already existed).
	 */
	public const OPTION_OVERVIEW_PAGE = 'living_handbook_overview_page';

	/**
	 * Option that shows the one-time setup notice until it is dismissed.
	 */
	private const OPTION_SETUP_NOTICE = 'living_handbook_setup_notice';

	private const OVERVIEW_BLOCK = 'wp:living-handbook/overview';

	private const NOTICE_ID = 'living-handbook-setup-notice';

	private const DISMISS_ACTION = 'living_handbook_dismiss_setup';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dismiss_script' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
		add_action( 'admin_notices', array( $this, 'unassigned_notice' ) );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Activation step: create the overview page once and arm the setup notice.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::maybe_create_overview_page();
		update_option( self::OPTION_SETUP_NOTICE, 1 );
	}

	/**
	 * Create the overview page, unless that was already decided once or a page
	 * with the overview block already exists.
	 *
	 * @return void
	 */
	private static function maybe_create_overview_page(): void {
		if ( false !== get_option( self::OPTION_OVERVIEW_PAGE, false ) ) {
			return;
		}
		if ( self::overview_page_exists() ) {
			update_option( self::OPTION_OVERVIEW_PAGE, 0 );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Handbook', 'living-handbook' ),
				'post_name'    => 'handbook',
				'post_content' => '<!-- ' . self::OVERVIEW_BLOCK . ' /-->',
			),
			true
		);

		update_option( self::OPTION_OVERVIEW_PAGE, is_wp_error( $page_id ) ? 0 : (int) $page_id );
	}

	/**
	 * Whether some page already holds the overview block.
	 *
	 * Runs once on activation, so a direct query is cheaper than loading every
	 * page to look at its content.
	 *
	 * @return bool
	 */
	private static function overview_page_exists(): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status != 'trash' AND post_content LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( self::OVERVIEW_BLOCK ) . '%'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return null !== $found;
	}

	/**
	 * Whether the setup notice belongs on the current screen.
	 *
	 * @return bool
	 */
	private function should_show_notice(): bool {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( self::OPTION_SETUP_NOTICE ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( null === $screen ) {
			return false;
		}
		return in_array( $screen->base, array( 'dashboard', 'plugins' ), true )
			|| Handbook::POST_TYPE === $screen->post_type;
	}

	/**
	 * Load the small script that makes the dismiss button stick, on the screens
	 * where the notice can appear.
	 *
	 * @return void
	 */
	public function enqueue_dismiss_script(): void {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		wp_enqueue_script(
			'living-handbook-setup-notice',
			LIVING_HANDBOOK_URL . 'assets/js/setup-notice.js',
			array(),
			LIVING_HANDBOOK_VERSION,
			true
		);
		wp_localize_script(
			'living-handbook-setup-notice',
			'livingHandbookSetup',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::DISMISS_ACTION,
				'nonce'   => wp_create_nonce( self::DISMISS_ACTION ),
			)
		);
	}

	/**
	 * The one-time notice after activation: where the overview is and what to
	 * do next. Dismissed with the standard notice close button, which the
	 * accompanying script makes permanent.
	 *
	 * @return void
	 */
	public function setup_notice(): void {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		$page_id = (int) get_option( self::OPTION_OVERVIEW_PAGE, 0 );
		$link    = $page_id > 0 ? get_permalink( $page_id ) : '';

		printf(
			'<div id="%1$s" class="notice notice-info is-dismissible"><p><strong>%2$s</strong></p>',
			esc_attr( self::NOTICE_ID ),
			esc_html__( 'Living Handbook is ready.', 'living-handbook' )
		);

		if ( is_string( $link ) && '' !== $link ) {
			printf(
				'<p>%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Your handbook overview was created as a normal page, so you can style and move it like any other:', 'living-handbook' ),
				esc_url( $link ),
				esc_html( get_the_title( $page_id ) )
			);
		}

		printf(
			'<p>%1$s</p><p>%2$s</p>',
			esc_html__( 'Next: create a handbook under Handbook, Handbook types, and set who may read it. Then assign your pages to that handbook.', 'living-handbook' ),
			esc_html__( 'A page without a handbook stays invisible on the front end. That is on purpose: access is granted per handbook, so a page that belongs to none belongs to nobody.', 'living-handbook' )
		);

		printf(
			'<p><a href="%1$s">%2$s</a></p></div>',
			esc_url( admin_url( 'edit-tags.php?taxonomy=' . Handbooks::TAXONOMY . '&post_type=' . Handbook::POST_TYPE ) ),
			esc_html__( 'Create a handbook', 'living-handbook' )
		);
	}

	/**
	 * Warn on the page list about pages that are invisible because they have no
	 * handbook. This is the most common cause of "my pages do not show up".
	 *
	 * @return void
	 */
	public function unassigned_notice(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || Handbook::POST_TYPE !== $screen->post_type || 'edit' !== $screen->base ) {
			return;
		}

		$ids   = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
		$count = count( $ids );
		if ( 0 === $count ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of handbook pages without a handbook. */
					_n(
						'%d handbook page is not assigned to a handbook, so it stays invisible on the front end. Open it and pick a handbook in the sidebar.',
						'%d handbook pages are not assigned to a handbook, so they stay invisible on the front end. Open them and pick a handbook in the sidebar.',
						$count,
						'living-handbook'
					),
					$count
				)
			)
		);
	}

	/**
	 * Remember that the setup notice was dismissed.
	 *
	 * @return void
	 */
	public function ajax_dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::DISMISS_ACTION, 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		delete_option( self::OPTION_SETUP_NOTICE );
		wp_send_json_success();
	}
}
