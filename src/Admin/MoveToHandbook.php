<?php
/**
 * Moving existing WordPress pages into a handbook.
 *
 * Turning a page into a handbook page is one field, `post_type`, and that is
 * exactly why doing it by hand goes wrong. Three things have to happen with it,
 * and none of them happens by itself:
 *
 * 1. The page needs a handbook. Access is fail-closed, so a handbook page
 *    without one is not moved, it is gone from the front end.
 * 2. Its address changes, from /about/ to /handbook/about/, and WordPress does
 *    not redirect on a type change. Every existing link would break, so the old
 *    path is remembered and answered with a 301.
 * 3. Its children have to come along. A child left behind keeps a parent that is
 *    no longer a page, and its own permalink is built from that chain.
 *
 * The bulk action therefore always takes the whole subtree. It is not offered as
 * a choice, because a bulk dropdown has no room for a question and the answer
 * "no" produces pages nobody can reach.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Admin;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A bulk action on the page list, and the redirect that keeps old links alive.
 */
final class MoveToHandbook {

	/**
	 * The path a page had before it was moved, relative to the site root and
	 * without the leading and trailing slash, for example "about/team".
	 */
	public const META_MOVED_FROM = '_lh_moved_from';

	/**
	 * The bulk action. One entry, not one per handbook: the handbook is chosen in
	 * a second control that appears beside the bulk dropdown once this is
	 * selected. A list of handbooks inside the bulk dropdown reads as a list of
	 * unrelated actions and grows with every handbook a site adds.
	 */
	private const ACTION = 'lh_move_to_handbook';

	/**
	 * Name of the field carrying the chosen handbook.
	 */
	private const FIELD = 'lh_handbook';

	/**
	 * Query argument carrying the result back to the list screen.
	 */
	private const RESULT_ARG = 'lh_moved';

	/**
	 * Set once a page has been moved, so a site that never used this feature
	 * never pays for it. Without it every 404 on every site would ask the
	 * database whether some page used to live at that address, and the answer on
	 * almost every site is no.
	 */
	private const OPTION_ANY_MOVED = 'living_handbook_moved_pages';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'bulk_actions-edit-page', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-page', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'restrict_manage_posts', array( $this, 'render_handbook_select' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'template_redirect', array( $this, 'redirect_moved' ) );
	}

	/**
	 * Whether the current user may move pages into a handbook.
	 *
	 * Moving content between post types is an editorial act on other people's
	 * pages as well as one's own, so it takes the capability that means exactly
	 * that. The per-page check follows for each page.
	 *
	 * @return bool
	 */
	public static function allowed(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * The handbooks of this site.
	 *
	 * @return array<int, WP_Term>
	 */
	private static function handbooks(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$out[] = $term;
			}
		}

		return $out;
	}

	/**
	 * One bulk action, offered only when there is a handbook to move into.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string>
	 */
	public function add_bulk_actions( array $actions ): array {
		if ( ! self::allowed() || array() === self::handbooks() ) {
			return $actions;
		}

		$actions[ self::ACTION ] = __( 'Move into a handbook…', 'living-handbook' );

		return $actions;
	}

	/**
	 * The second control: which handbook.
	 *
	 * Rendered into the filter row, which is inside the same form as the bulk
	 * dropdown, and moved next to that dropdown by the script. Rendering it here
	 * rather than only in the footer means it is submitted and usable even if the
	 * script never runs; the script's job is placement, not function.
	 *
	 * @param string $post_type Post type of the current list.
	 * @return void
	 */
	public function render_handbook_select( string $post_type ): void {
		if ( 'page' !== $post_type || ! self::allowed() ) {
			return;
		}

		$terms = self::handbooks();
		if ( array() === $terms ) {
			return;
		}

		echo '<span class="living-handbook-move-target">';
		printf(
			'<label class="screen-reader-text" for="%1$s">%2$s</label>',
			esc_attr( self::FIELD ),
			esc_html__( 'Handbook to move the pages into', 'living-handbook' )
		);
		printf( '<select name="%1$s" id="%1$s">', esc_attr( self::FIELD ) );
		// An empty value, not "0": that is what makes the browser's own required
		// validation refuse an empty choice, with no dialog of our own.
		printf( '<option value="">%s</option>', esc_html__( '— pick a handbook —', 'living-handbook' ) );
		foreach ( $terms as $term ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( (string) $term->term_id ), esc_html( $term->name ) );
		}
		echo '</select></span>';
	}

	/**
	 * The script that puts that control beside the bulk dropdown and shows it
	 * only while the move action is selected.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'edit.php' !== $hook || ! self::allowed() ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || 'page' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'living-handbook-bulk-move',
			LIVING_HANDBOOK_URL . 'assets/js/bulk-move.js',
			array(),
			LIVING_HANDBOOK_VERSION,
			true
		);
		wp_add_inline_script(
			'living-handbook-bulk-move',
			'window.livingHandbookBulkMove = ' . wp_json_encode(
				array(
					'action' => self::ACTION,
					'field'  => self::FIELD,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Move the selected pages, and everything below them.
	 *
	 * @param string     $redirect The redirect URL.
	 * @param string     $action   The chosen bulk action.
	 * @param array<int> $ids      Selected post ids.
	 * @return string
	 */
	public function handle_bulk_action( string $redirect, string $action, array $ids ): string {
		if ( self::ACTION !== $action ) {
			return $redirect;
		}
		if ( ! self::allowed() ) {
			return $redirect;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress verifies the bulk nonce before this filter runs.
		$term_id = isset( $_REQUEST[ self::FIELD ] ) ? absint( wp_unslash( $_REQUEST[ self::FIELD ] ) ) : 0;
		$term    = $term_id > 0 ? get_term( $term_id, Handbooks::TAXONOMY ) : null;
		if ( ! $term instanceof WP_Term ) {
			// No handbook chosen: nothing is moved, and the screen says so rather
			// than reporting a silent success over zero pages.
			return add_query_arg( array( 'lh_no_handbook' => 1 ), $redirect );
		}

		$moved   = 0;
		$skipped = 0;
		foreach ( self::with_descendants( array_map( 'intval', $ids ) ) as $id ) {
			if ( self::move( $id, $term_id ) ) {
				++$moved;
			} else {
				++$skipped;
			}
		}

		if ( $moved > 0 ) {
			flush_rewrite_rules( false );
		}

		return add_query_arg(
			array(
				self::RESULT_ARG => $moved,
				'lh_skipped'     => $skipped,
			),
			$redirect
		);
	}

	/**
	 * The given pages plus every page below them, parents before children.
	 *
	 * @param array<int, int> $ids Selected page ids.
	 * @return array<int, int>
	 */
	public static function with_descendants( array $ids ): array {
		$out = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 || in_array( $id, $out, true ) ) {
				continue;
			}
			$out[] = $id;

			$children = get_pages(
				array(
					'child_of'    => $id,
					'post_type'   => 'page',
					'post_status' => 'publish,draft,pending,private,future',
					'sort_column' => 'menu_order,post_title',
				)
			);
			if ( ! is_array( $children ) ) {
				continue;
			}
			foreach ( $children as $child ) {
				$child_id = (int) $child->ID;
				if ( ! in_array( $child_id, $out, true ) ) {
					$out[] = $child_id;
				}
			}
		}

		return $out;
	}

	/**
	 * Move one page into a handbook.
	 *
	 * The old path is written before the type changes, because afterwards the
	 * permalink is the new one and the old one is unrecoverable.
	 *
	 * @param int $post_id Page id.
	 * @param int $term_id Target handbook.
	 * @return bool Whether the page was moved.
	 */
	public static function move( int $post_id, int $term_id ): bool {
		$post = get_post( $post_id );
		if ( null === $post || 'page' !== $post->post_type ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		$was = self::path_of( $post_id );

		$result = wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => Handbook::POST_TYPE,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}

		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY, false );

		if ( '' !== $was ) {
			update_post_meta( $post_id, self::META_MOVED_FROM, $was );
			update_option( self::OPTION_ANY_MOVED, 1 );
		}

		return true;
	}

	/**
	 * The site-relative path of a post, without the leading and trailing slash.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function path_of( int $post_id ): string {
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) ) {
			return '';
		}

		$path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home && '/' !== $home && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		return trim( $path, '/' );
	}

	/**
	 * Answer a request for a moved page's old address with a 301.
	 *
	 * Only on a 404, so this costs nothing on any request that resolves. A moved
	 * page keeps its old address working for good: the alternative is that every
	 * link, bookmark and search result pointing at the documentation dies the day
	 * it becomes documentation.
	 *
	 * @return void
	 */
	public function redirect_moved(): void {
		if ( ! is_404() ) {
			return;
		}
		// Nothing has ever been moved on this site: no lookup, no query. This is
		// the common case, and it is the reason the query below is acceptable.
		if ( ! get_option( self::OPTION_ANY_MOVED ) ) {
			return;
		}

		$requested = isset( $_SERVER['REQUEST_URI'] )
			? (string) wp_parse_url( esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH )
			: '';
		$home      = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home && '/' !== $home && str_starts_with( $requested, $home ) ) {
			$requested = substr( $requested, strlen( $home ) );
		}
		$requested = trim( $requested, '/' );
		if ( '' === $requested ) {
			return;
		}

		/*
		 * A meta lookup, which PHPCS flags as a possible slow query because
		 * meta_value carries no index. Kept, with three things that make it
		 * cheap: it runs on a 404 only, never on a request that resolves; it
		 * runs only on a site that has actually moved a page, which the option
		 * above decides; and meta_key is indexed, so the scan is over the moved
		 * pages of this site rather than over wp_postmeta. The alternative, an
		 * autoloaded option holding every old path, would be read on every
		 * request instead of on a rare one.
		 */
		$found = get_posts(
			array(
				'post_type'        => Handbook::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- see above.
				'meta_key'         => self::META_MOVED_FROM,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				'meta_value'       => $requested,
			)
		);
		if ( empty( $found ) ) {
			return;
		}

		$target = get_permalink( (int) $found[0] );
		if ( ! is_string( $target ) ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * Report what the bulk action did.
	 *
	 * @return void
	 */
	public function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['lh_no_handbook'] ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'Nothing was moved: no handbook was chosen. Pick one in the dropdown beside the bulk action.', 'living-handbook' )
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST[ self::RESULT_ARG ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$moved = absint( wp_unslash( $_REQUEST[ self::RESULT_ARG ] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$skipped = isset( $_REQUEST['lh_skipped'] ) ? absint( wp_unslash( $_REQUEST['lh_skipped'] ) ) : 0;

		$text = sprintf(
			/* translators: %d: number of pages moved. */
			_n( '%d page moved into the handbook, subpages included. Its old address now redirects to the new one.', '%d pages moved into the handbook, subpages included. Their old addresses now redirect to the new ones.', $moved, 'living-handbook' ),
			$moved
		);
		if ( $skipped > 0 ) {
			$text .= ' ' . sprintf(
				/* translators: %d: number of pages skipped. */
				_n( '%d page was left alone, because it is not a page or you may not edit it.', '%d pages were left alone, because they are not pages or you may not edit them.', $skipped, 'living-handbook' ),
				$skipped
			);
		}
		$text .= ' ' . __( 'The moved pages have no review date yet, so they show as "Not reviewed" until you set one.', 'living-handbook' );

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}
}
