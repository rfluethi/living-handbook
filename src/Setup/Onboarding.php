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
use WP_Query;

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

	/**
	 * The block that makes a page the handbook overview.
	 */
	private const OVERVIEW_BLOCK = 'wp:living-handbook/overview';

	/**
	 * The HTML id of the setup notice, which its dismiss script looks for.
	 */
	private const NOTICE_ID = 'living-handbook-setup-notice';

	/**
	 * The admin-ajax action and nonce action that dismiss the setup notice.
	 */
	private const DISMISS_ACTION = 'living_handbook_dismiss_setup';

	/**
	 * How many affected pages a notice links to by name. Enough to start with,
	 * few enough that the notice stays a notice rather than a second page list.
	 */
	private const PAGE_LINKS = 10;

	/**
	 * Registers the hooks with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dismiss_script' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
		add_action( 'admin_notices', array( $this, 'unassigned_notice' ) );
		add_action( 'admin_notices', array( $this, 'classic_theme_notice' ) );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Points out on the handbook screens that the active theme is a classic
	 * theme.
	 *
	 * The entry and single page of a handbook are block templates, and only a
	 * block theme uses those. With a classic theme the front end falls back to
	 * the theme's own templates: no handbook navigation beside the page, no
	 * table of contents, no search bar, and nothing in the admin said why. A
	 * site trying the plugin on its existing classic theme concluded that the
	 * plugin was broken. Shown on the plugin's own screens only, to people who
	 * may change the theme, and not dismissible, because it stays true until
	 * the theme changes.
	 *
	 * @return void
	 */
	public function classic_theme_notice(): void {
		if ( wp_is_block_theme() || ! current_user_can( 'switch_themes' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || Handbook::POST_TYPE !== $screen->post_type ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s</p><p>%2$s</p></div>',
			esc_html__( 'The active theme is a classic theme. Handbook pages are laid out with block templates, which only a block theme uses, so on this site they appear in the theme\'s own layout, without the handbook navigation, the table of contents and the search bar.', 'living-handbook' ),
			esc_html__( 'Switch to a block theme such as Twenty Twenty-Five to get the full layout. If the theme has to stay, the shortcode [living_handbook_nav] places the page tree in a classic template.', 'living-handbook' )
		);
	}

	/**
	 * Creates the overview page once on activation and arms the setup notice.
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
				'post_name'    => self::overview_slug(),
				'post_content' => '<!-- ' . self::OVERVIEW_BLOCK . ' /-->',
			),
			true
		);

		update_option( self::OPTION_OVERVIEW_PAGE, is_wp_error( $page_id ) ? 0 : (int) $page_id );
	}

	/**
	 * The slug of a newly created overview page.
	 *
	 * Up to 0.75.1 it was "handbook", the same as the URL base of handbook
	 * pages. The page itself worked, because the post type has no archive, but
	 * the post type's rule matches every address below it first, so a subpage
	 * of the overview at /handbook/team/ was read as a handbook page called
	 * "team" and answered 404. Since 0.76.0 new installations get a slug of
	 * their own. An existing overview page keeps its address; changing
	 * a published URL is the site's call, not an update's.
	 *
	 * The handbook base can be changed by a filter, so the slug is checked
	 * against the base actually in use.
	 *
	 * @return string
	 */
	private static function overview_slug(): string {
		$object = get_post_type_object( Handbook::POST_TYPE );
		if ( null !== $object && is_array( $object->rewrite ) && isset( $object->rewrite['slug'] ) ) {
			$base = (string) $object->rewrite['slug'];
		} else {
			/** This filter is documented in src/PostType/Handbook.php */
			$base = (string) apply_filters( 'living_handbook_post_type_slug', 'handbook' );
		}

		return 'handbooks' === trim( $base, '/' ) ? 'handbook-overview' : 'handbooks';
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Runs once on activation; there is nothing to cache.
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
	 * Loads the small script that makes the dismiss button stick, on the screens
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
		// Configuration, not translations, so an inline script rather than
		// wp_localize_script().
		wp_add_inline_script(
			'living-handbook-setup-notice',
			'var livingHandbookSetup = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => self::DISMISS_ACTION,
					'nonce'   => wp_create_nonce( self::DISMISS_ACTION ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Prints the one-time notice after activation: the two steps a new install
	 * needs, in the order it needs them, and where the overview page is.
	 *
	 * Two steps as a numbered list and one line for the page, nothing else. The
	 * rule that a page without a handbook stays invisible is explained where it
	 * bites, in unassigned_notice(), not here before anybody has run into it.
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
	 * Warns on the page list about pages that are invisible because they have
	 * no handbook. This is the most common cause of "my pages do not show up".
	 *
	 * Only the first PAGE_LINKS pages are loaded, to link them; the number in
	 * the message is the full count. Up to 0.75.1 the lookup stopped at 50
	 * pages, and the message said 50 for any number above that.
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

		$query = new WP_Query(
			array(
				'post_type'           => Handbook::POST_TYPE,
				'post_status'         => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'      => self::PAGE_LINKS,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One count on the page list, not on a page view.
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);
		$count = (int) $query->found_posts;
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
			self::page_links( array_map( 'intval', $query->posts ), $count )
		);
	}

	/**
	 * Builds a list of edit links to the affected pages for an admin notice, so
	 * a warning points straight at the pages it is about. Shows at most
	 * PAGE_LINKS links, then a short "and N more" note.
	 *
	 * @param int[] $ids   Post IDs.
	 * @param int   $count Total number of affected pages.
	 * @return string HTML (a <ul>, possibly followed by a note), or '' when empty.
	 */
	private static function page_links( array $ids, int $count ): string {
		$items = '';
		foreach ( array_slice( $ids, 0, self::PAGE_LINKS ) as $id ) {
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
		if ( $count > self::PAGE_LINKS ) {
			$more  = $count - self::PAGE_LINKS;
			$html .= sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: number of further affected pages not listed. */
						_n( '… and %d more.', '… and %d more.', $more, 'living-handbook' ),
						$more
					)
				)
			);
		}
		return $html;
	}

	/**
	 * Remembers that the setup notice was dismissed.
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
