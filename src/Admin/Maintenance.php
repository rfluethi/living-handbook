<?php
/**
 * Maintenance dashboard widget and admin list columns.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Admin;

use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Frontend\FreshnessStatus;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces the freshness data: a dashboard widget with the overdue share, and
 * review and feedback columns in the handbook list.
 */
final class Maintenance {

	/**
	 * The admin-post action that resets a page's feedback counters.
	 */
	private const RESET_ACTION = 'living_handbook_reset_feedback';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ) );
		add_filter( 'manage_' . Handbook::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Handbook::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . Handbook::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_filter( 'posts_clauses', array( $this, 'order_by_reviewed' ), 10, 2 );
		add_filter( 'posts_clauses', array( $this, 'order_by_feedback' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'taxonomy_filter_dropdowns' ) );
		// Priority 15 so the status filter renders before the source dropdown (20),
		// matching the columns: "Last reviewed" comes before "Source".
		add_action( 'restrict_manage_posts', array( $this, 'status_filter_dropdown' ), 15 );
		add_action( 'pre_get_posts', array( $this, 'filter_by_status' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_filters' ) );
		add_filter( 'post_row_actions', array( $this, 'feedback_reset_action' ), 10, 2 );
		add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'handle_feedback_reset' ) );
		add_action( 'admin_notices', array( $this, 'feedback_reset_notice' ) );
	}

	/**
	 * Add a "Reset feedback" row action to a handbook page that carries votes, so
	 * a page reworked after poor feedback can start from zero. Shown only to a
	 * user who may edit the page, and only when there is something to reset.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param WP_Post               $post    The row's post.
	 * @return array<string, string>
	 */
	public function feedback_reset_action( array $actions, WP_Post $post ): array {
		if ( Handbook::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$votes = (int) get_post_meta( $post->ID, Feedback::YES, true ) + (int) get_post_meta( $post->ID, Feedback::NO, true );
		if ( 0 === $votes ) {
			return $actions;
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::RESET_ACTION . '&post=' . $post->ID ),
			self::RESET_ACTION . '_' . $post->ID
		);

		$actions['living_handbook_reset_feedback'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Reset feedback', 'living-handbook' )
		);
		return $actions;
	}

	/**
	 * Reset a page's feedback counters and voter list, then return to the list.
	 *
	 * @return void
	 */
	public function handle_feedback_reset(): void {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		check_admin_referer( self::RESET_ACTION . '_' . $post_id );
		if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to reset feedback for this page.', 'living-handbook' ) );
		}

		delete_post_meta( $post_id, Feedback::YES );
		delete_post_meta( $post_id, Feedback::NO );
		delete_post_meta( $post_id, Feedback::VOTERS );

		$back = add_query_arg(
			'living_handbook_feedback_reset',
			'1',
			admin_url( 'edit.php?post_type=' . Handbook::POST_TYPE )
		);
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Confirm a feedback reset on the handbook list.
	 *
	 * @return void
	 */
	public function feedback_reset_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a redirect flag to show a notice; no state change.
		if ( ! isset( $_GET['living_handbook_feedback_reset'] ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || 'edit-' . Handbook::POST_TYPE !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Feedback was reset.', 'living-handbook' ) . '</p></div>';
	}

	/**
	 * Register the dashboard widget for content managers.
	 *
	 * @return void
	 */
	public function add_widget(): void {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'living_handbook_freshness',
			__( 'Handbook: reviews overdue', 'living-handbook' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the dashboard widget.
	 *
	 * Loads only post IDs and reads the freshness meta per page; the title and
	 * edit link are fetched only for the overdue pages, not for all pages. The
	 * post meta of every page is primed in one query first, so the per-page
	 * freshness lookups do not each hit the database (no N+1).
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$ids = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		// Prime the meta cache for every page in one query, so FreshnessStatus,
		// which reads several meta keys per page, does not query per page.
		if ( ! empty( $ids ) ) {
			update_meta_cache( 'post', $ids );
		}

		$total   = 0;
		$overdue = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			++$total;
			$status = FreshnessStatus::for_post( $id );
			if ( FreshnessStatus::DUE === $status || FreshnessStatus::OVERDUE === $status ) {
				$overdue[] = array(
					'title'  => get_the_title( $id ),
					'link'   => (string) get_edit_post_link( $id ),
					'status' => $status,
				);
			}
		}

		$count = count( $overdue );
		$pct   = $total > 0 ? (int) round( 100 * $count / $total ) : 0;

		printf(
			'<p><strong>%s</strong></p>',
			esc_html(
				sprintf(
					/* translators: 1: overdue count, 2: total count, 3: percentage. */
					__( '%1$d of %2$d pages overdue (%3$d%%).', 'living-handbook' ),
					$count,
					$total,
					$pct
				)
			)
		);

		if ( empty( $overdue ) ) {
			echo '<p>' . esc_html__( 'All pages are currently reviewed.', 'living-handbook' ) . '</p>';
			return;
		}

		$limit = 10;
		echo '<ul>';
		foreach ( array_slice( $overdue, 0, $limit ) as $item ) {
			printf(
				'<li><a href="%1$s">%2$s</a> (%3$s)</li>',
				esc_url( $item['link'] ),
				esc_html( $item['title'] ),
				esc_html( FreshnessStatus::label( $item['status'] ) )
			);
		}
		echo '</ul>';

		if ( $count > $limit ) {
			printf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url(
					add_query_arg(
						array(
							'post_type' => Handbook::POST_TYPE,
							'orderby'   => 'living_handbook_reviewed',
							'order'     => 'asc',
						),
						admin_url( 'edit.php' )
					)
				),
				esc_html__( 'Show all handbook pages, oldest review first', 'living-handbook' )
			);
		}
	}

	/**
	 * Add the review and feedback columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		// The handbook goes directly after the title, because it is the first
		// thing a person needs to place a row: the list mixes every handbook,
		// and until now nothing in a row said which one it belonged to. You
		// could filter by handbook and still not read one off the list.
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['living_handbook_set'] = __( 'Handbook', 'living-handbook' );
			}
		}
		if ( ! isset( $out['living_handbook_set'] ) ) {
			$out['living_handbook_set'] = __( 'Handbook', 'living-handbook' );
		}

		$out['living_handbook_reviewed'] = __( 'Last reviewed', 'living-handbook' );
		$out['living_handbook_feedback'] = __( 'Feedback', 'living-handbook' );

		return $out;
	}

	/**
	 * Render a custom column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'living_handbook_set' === $column ) {
			$term_id = Handbooks::for_post( $post_id );
			$term    = $term_id > 0 ? get_term( $term_id, Handbooks::TAXONOMY ) : null;
			if ( ! $term instanceof WP_Term ) {
				// Not a decoration: a page without a handbook is invisible on the
				// front end, and this is the list where that is noticed.
				echo '<span aria-hidden="true">—</span><span class="screen-reader-text">'
					. esc_html__( 'No handbook, so this page stays invisible on the front end.', 'living-handbook' )
					. '</span>';
				return;
			}

			printf(
				'<a href="%1$s">%2$s</a>',
				esc_url(
					add_query_arg(
						array(
							'post_type'         => Handbook::POST_TYPE,
							Handbooks::TAXONOMY => $term->slug,
						),
						admin_url( 'edit.php' )
					)
				),
				esc_html( $term->name )
			);
			return;
		}

		if ( 'living_handbook_reviewed' === $column ) {
			$reviewed = (string) get_post_meta( $post_id, Metadata::REVIEWED, true );
			$label    = FreshnessStatus::label( FreshnessStatus::for_post( $post_id ) );
			$display  = '—';
			if ( '' !== $reviewed ) {
				$timestamp = strtotime( $reviewed );
				$display   = false !== $timestamp ? date_i18n( (string) get_option( 'date_format' ), $timestamp ) : $reviewed;
			}
			echo esc_html( $display );
			if ( '' !== $label ) {
				printf( ' <span>(%s)</span>', esc_html( $label ) );
			}
		} elseif ( 'living_handbook_feedback' === $column ) {
			$yes = (int) get_post_meta( $post_id, Feedback::YES, true );
			$no  = (int) get_post_meta( $post_id, Feedback::NO, true );
			echo esc_html(
				sprintf(
					/* translators: 1: number of positive votes, 2: number of negative votes. */
					__( '%1$d yes, %2$d no', 'living-handbook' ),
					$yes,
					$no
				)
			);
		}
	}

	/**
	 * Mark the review column as sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['living_handbook_reviewed'] = 'living_handbook_reviewed';
		$columns['living_handbook_feedback'] = 'living_handbook_feedback';
		return $columns;
	}

	/**
	 * Order the handbook list by the last review date when that column header is
	 * clicked. A single LEFT JOIN reads the review date; pages without one (no meta
	 * row or an empty value) are grouped together and always trail the dated pages,
	 * in both directions, so they never split to both ends of the list. The join
	 * key is a fixed plugin constant and the direction is validated, so the
	 * fragment carries no user input.
	 *
	 * @param array<string, string> $clauses The SQL clauses of the query.
	 * @param WP_Query              $query   The current query.
	 * @return array<string, string>
	 */
	public function order_by_reviewed( array $clauses, WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return $clauses;
		}
		if ( 'living_handbook_reviewed' !== $query->get( 'orderby' ) ) {
			return $clauses;
		}
		if ( Handbook::POST_TYPE !== $query->get( 'post_type' ) ) {
			return $clauses;
		}

		global $wpdb;
		$key   = esc_sql( Metadata::REVIEWED );
		$order = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lh_rev ON ( lh_rev.post_id = {$wpdb->posts}.ID AND lh_rev.meta_key = '{$key}' )";
		$clauses['orderby'] = "( CASE WHEN lh_rev.meta_value IS NULL OR lh_rev.meta_value = '' THEN 1 ELSE 0 END ) ASC, lh_rev.meta_value {$order}, {$wpdb->posts}.post_title ASC";
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $clauses;
	}

	/**
	 * Order the handbook list by net feedback (yes votes minus no votes) when that
	 * column header is clicked. Both counts are post meta, so the difference cannot
	 * be expressed as a meta_query orderby; this joins both meta rows and orders by
	 * their difference. Pages without votes count as zero (COALESCE) and are kept,
	 * so sorting never hides a page. The join keys are fixed plugin constants and
	 * the direction is validated, so the fragment carries no user input.
	 *
	 * @param array<string, string> $clauses The SQL clauses of the query.
	 * @param WP_Query              $query   The current query.
	 * @return array<string, string>
	 */
	public function order_by_feedback( array $clauses, WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return $clauses;
		}
		if ( 'living_handbook_feedback' !== $query->get( 'orderby' ) ) {
			return $clauses;
		}
		if ( Handbook::POST_TYPE !== $query->get( 'post_type' ) ) {
			return $clauses;
		}

		global $wpdb;
		$yes   = esc_sql( Feedback::YES );
		$no    = esc_sql( Feedback::NO );
		$order = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lh_fy ON ( lh_fy.post_id = {$wpdb->posts}.ID AND lh_fy.meta_key = '{$yes}' )";
		$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} lh_fn ON ( lh_fn.post_id = {$wpdb->posts}.ID AND lh_fn.meta_key = '{$no}' )";
		$clauses['orderby'] = "( COALESCE( lh_fy.meta_value + 0, 0 ) - COALESCE( lh_fn.meta_value + 0, 0 ) ) {$order}, {$wpdb->posts}.post_title ASC";
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $clauses;
	}

	/**
	 * Add a filter dropdown above the handbook list for the handbook and each
	 * vocabulary (page type, topic, responsibility, audience). Selecting a term
	 * narrows the list through that taxonomy's query var, the same mechanism the
	 * category filter uses for posts. This is filtering, not sorting: the taxonomy
	 * columns stay unsortable on purpose, because a page can belong to several
	 * terms and so has no single order to sort by.
	 *
	 * @param string $post_type The post type of the list being shown.
	 * @return void
	 */
	public function taxonomy_filter_dropdowns( string $post_type ): void {
		if ( Handbook::POST_TYPE !== $post_type ) {
			return;
		}

		// The filters run in the order of the columns they filter, so the eye can
		// go straight down from a filter to what it narrows. WordPress prints its
		// own date dropdown before this hook and cannot be reordered, so the date
		// stays first; everything after it follows the columns: handbook, then the
		// four vocabularies, then the review status, then the source (the last two
		// are added at later priorities, by this class and by GitSync).
		//
		// The value is the column each filter belongs to. The handbook has one of
		// this plugin's own, the vocabularies get theirs from WordPress, which
		// names a taxonomy column "taxonomy-" plus the taxonomy.
		$taxonomies = array(
			Handbooks::TAXONOMY   => 'living_handbook_set',
			Taxonomies::PAGE_TYPE => 'taxonomy-' . Taxonomies::PAGE_TYPE,
			Taxonomies::TOPIC     => 'taxonomy-' . Taxonomies::TOPIC,
			Taxonomies::ROLE      => 'taxonomy-' . Taxonomies::ROLE,
			Taxonomies::AUDIENCE  => 'taxonomy-' . Taxonomies::AUDIENCE,
		);

		foreach ( $taxonomies as $taxonomy => $column ) {
			$object = get_taxonomy( $taxonomy );
			if ( false === $object ) {
				continue;
			}
			// A vocabulary the site does not use has no filter, whatever the
			// columns say and whatever is in the URL. Unlike a hidden column,
			// this is a decision about the site, not about one person's view.
			if ( Handbooks::TAXONOMY !== $taxonomy && ! Taxonomies::is_enabled( $taxonomy ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current = isset( $_GET[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $taxonomy ] ) ) : '';
			$active  = '' !== $current;

			// A vocabulary with no term at all is left out entirely: its dropdown
			// could only offer "All topics", and unlike a hidden column that cannot
			// change while the page is open. A hidden column, by contrast, only
			// hides the control, so switching the column back on brings it back
			// without a reload.
			if ( ! $active && ! ListScreen::taxonomy_has_terms( $taxonomy ) ) {
				continue;
			}

			/* translators: %s: the plural taxonomy name, for example "Topics". */
			$all_label = sprintf( __( 'All %s', 'living-handbook' ), $object->labels->name );
			$dropdown  = wp_dropdown_categories(
				array(
					'taxonomy'        => $taxonomy,
					'name'            => $taxonomy,
					'value_field'     => 'slug',
					'show_option_all' => $all_label,
					'hide_empty'      => false,
					'hierarchical'    => is_taxonomy_hierarchical( $taxonomy ),
					'orderby'         => 'name',
					'selected'        => $current,
					'echo'            => false,
				)
			);

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_categories escapes its own markup; the wrapper escapes its attributes.
			echo ListScreen::wrap( $column, (string) $dropdown, $active );
		}
	}

	/**
	 * Load the small script that keeps the filter bar in step with the column
	 * checkboxes, on the handbook list only.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_list_filters( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || Handbook::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'living-handbook-list-filters',
			LIVING_HANDBOOK_URL . 'assets/js/list-filters.js',
			array(),
			LIVING_HANDBOOK_VERSION,
			true
		);
	}

	/**
	 * Add a "Review status" filter dropdown above the handbook list. The status
	 * (reviewed, due, overdue, never) is derived from the review date and the
	 * per-page interval, so it is not a stored value the taxonomy filters could
	 * cover; this filter complements the date sort on the "Last reviewed" column.
	 *
	 * @param string $post_type The post type of the list being shown.
	 * @return void
	 */
	public function status_filter_dropdown( string $post_type ): void {
		if ( Handbook::POST_TYPE !== $post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['lh_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['lh_status'] ) ) : '';

		$options = array(
			''                       => __( 'All review statuses', 'living-handbook' ),
			FreshnessStatus::OVERDUE => FreshnessStatus::label( FreshnessStatus::OVERDUE ),
			FreshnessStatus::DUE     => FreshnessStatus::label( FreshnessStatus::DUE ),
			FreshnessStatus::OK      => FreshnessStatus::label( FreshnessStatus::OK ),
			FreshnessStatus::NONE    => FreshnessStatus::label( FreshnessStatus::NONE ),
		);

		$select = '<select name="lh_status">';
		foreach ( $options as $value => $label ) {
			$select .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		$select .= '</select>';

		// Same rule as the taxonomy filters, and the same wrapper, so it follows
		// the "Last reviewed" column live.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above from escaped values.
		echo ListScreen::wrap( 'living_handbook_reviewed', $select, '' !== $current );
	}

	/**
	 * Narrow the handbook list to one review status. The status is computed, not
	 * stored, so this resolves the matching pages in PHP through FreshnessStatus
	 * (the single source of the rule) and constrains the query to those ids. The
	 * meta cache is primed first, so the per-page status reads do not each hit the
	 * database. The inner lookup is not the main query, so the other pre_get_posts
	 * hooks skip it and it does not recurse.
	 *
	 * @param WP_Query $query The current query.
	 * @return void
	 */
	public function filter_by_status( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Handbook::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['lh_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['lh_status'] ) ) : '';
		$valid  = FreshnessStatus::all();
		if ( ! in_array( $status, $valid, true ) ) {
			return;
		}

		// No suppress_filters here: this inner lookup is not the main query, so the
		// other pre_get_posts hooks skip it, and the_posts does not run for an
		// id-only query, so there is nothing to suppress and no recursion.
		$ids = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $ids ) ) {
			update_meta_cache( 'post', $ids );
		}

		$match = array();
		foreach ( $ids as $id ) {
			if ( FreshnessStatus::for_post( (int) $id ) === $status ) {
				$match[] = (int) $id;
			}
		}

		// An empty match must show nothing, not everything, so fall back to an id
		// no post carries.
		$query->set( 'post__in', empty( $match ) ? array( 0 ) : $match );
	}
}
