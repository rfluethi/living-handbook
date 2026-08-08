<?php
/**
 * First-run setup: give a fresh install something to look at.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Setup;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\AppHandbook;
use LivingHandbook\Import\MarkdownImportPage;
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
 * No content is created beyond that one structural page. The notice does point
 * at the app handbook, which is the documentation of the app kept on GitHub, but
 * loading it is a choice the site owner makes: content that appears by itself is
 * content nobody asked for. A handbook with real pages is imported from Markdown,
 * GitHub or a bundle.
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
	public const OPTION_SETUP_NOTICE = 'living_handbook_setup_notice';

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
	 * The one-time notice after activation: the two steps a new install needs,
	 * in the order it needs them, and where the overview page is.
	 *
	 * It used to be one box with five things in it: a heading, a "start here"
	 * with a button, the overview page, a paragraph on creating a handbook, and a
	 * paragraph explaining that a page without a handbook stays invisible. Rico
	 * called it confusing, and it was: two of those are actions, one is a fact,
	 * and two were an explanation of a rule nobody has run into yet. The rule now
	 * appears where it bites, in the warning below this one, and this notice is a
	 * numbered list of the two steps plus one line for the page.
	 *
	 * Dismissed with the standard notice close button, which the accompanying
	 * script makes permanent.
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
			esc_html__( 'Living Handbook is ready. Two steps, in this order:', 'living-handbook' )
		);

		echo '<ol>';

		// Looking at a filled handbook comes before building one. An empty install
		// cannot show what a page type, a filter or a freshness badge is for, so
		// the fastest honest answer to "what does this thing do" is the plugin's
		// own handbook, which ships with it.
		if ( AppHandbook::can_load() ) {
			printf(
				'<li>%1$s <a href="%2$s">%3$s</a></li>',
				esc_html__( 'Load one of the handbooks that ship with the plugin and read it as an example.', 'living-handbook' ),
				esc_url(
					add_query_arg(
						array(
							'post_type' => Handbook::POST_TYPE,
							'page'      => MarkdownImportPage::MENU_SLUG,
						),
						admin_url( 'edit.php' )
					)
				),
				esc_html__( 'Go to the import screen', 'living-handbook' )
			);
		}

		printf(
			'<li>%1$s <a href="%2$s">%3$s</a></li>',
			esc_html__( 'Create a handbook of your own and set who may read it. Your pages then go into it.', 'living-handbook' ),
			esc_url( admin_url( 'edit-tags.php?taxonomy=' . Handbooks::TAXONOMY . '&post_type=' . Handbook::POST_TYPE ) ),
			esc_html__( 'Create a handbook', 'living-handbook' )
		);

		echo '</ol>';

		// One line, not a paragraph: the page exists, it is a normal page, and
		// where it is. Everything else about it belongs in the handbook.
		if ( is_string( $link ) && '' !== $link ) {
			printf(
				'<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'The overview page was created for you, as a normal page:', 'living-handbook' ),
				esc_url( $link ),
				esc_html( get_the_title( $page_id ) )
			);
		}

		echo '</div>';
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

		$message = sprintf(
			/* translators: %d: number of handbook pages without a handbook. */
			_n(
				'%d handbook page is not assigned to a handbook, so it stays invisible on the front end. Open it and pick a handbook in the sidebar.',
				'%d handbook pages are not assigned to a handbook, so they stay invisible on the front end. Open them and pick a handbook in the sidebar.',
				$count,
				'living-handbook'
			),
			$count
		);

		printf(
			'<div class="notice notice-warning"><p>%1$s</p>%2$s</div>',
			esc_html( $message ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url and esc_html below.
			self::page_links( $ids, $count )
		);
	}

	/**
	 * Build a list of edit links to the affected pages for an admin notice, so a
	 * warning points straight at the pages it is about. Shows at most ten links,
	 * then a short "and N more" note.
	 *
	 * @param int[] $ids   Post IDs.
	 * @param int   $count Total number of affected pages.
	 * @return string HTML (a <ul>, possibly followed by a note), or '' when empty.
	 */
	private static function page_links( array $ids, int $count ): string {
		$limit = 10;
		$items = '';
		foreach ( array_slice( $ids, 0, $limit ) as $id ) {
			$id   = (int) $id;
			$edit = (string) get_edit_post_link( $id );
			if ( '' === $edit ) {
				continue;
			}
			$title  = get_the_title( $id );
			$items .= sprintf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( $edit ),
				esc_html( '' !== $title ? $title : __( '(no title)', 'living-handbook' ) )
			);
		}
		if ( '' === $items ) {
			return '';
		}
		$html = '<ul>' . $items . '</ul>';
		if ( $count > $limit ) {
			$html .= sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: number of further affected pages not listed. */
						__( '… and %d more.', 'living-handbook' ),
						$count - $limit
					)
				)
			);
		}
		return $html;
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
