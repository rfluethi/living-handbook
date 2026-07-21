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
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces the freshness data: a dashboard widget with the overdue share, and
 * review and feedback columns in the handbook list.
 */
final class Maintenance {

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
		add_action( 'pre_get_posts', array( $this, 'sort_by_reviewed' ) );
		add_filter( 'posts_clauses', array( $this, 'order_by_feedback' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'handbook_filter_dropdown' ) );
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
		$columns['living_handbook_reviewed'] = __( 'Last reviewed', 'living-handbook' );
		$columns['living_handbook_feedback'] = __( 'Feedback', 'living-handbook' );
		return $columns;
	}

	/**
	 * Render a custom column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
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
	 * clicked. Pages without a review date are kept in the list (NOT EXISTS), so
	 * sorting never hides them.
	 *
	 * @param WP_Query $query The current query.
	 * @return void
	 */
	public function sort_by_reviewed( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'living_handbook_reviewed' !== $query->get( 'orderby' ) ) {
			return;
		}
		$order = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';
		$query->set( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query',
			array(
				'relation'    => 'OR',
				'lh_reviewed' => array(
					'key'     => Metadata::REVIEWED,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => Metadata::REVIEWED,
					'compare' => 'NOT EXISTS',
				),
			)
		);
		$query->set( 'orderby', array( 'lh_reviewed' => $order ) );
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
	 * Add a "Handbook" filter dropdown above the handbook list. Selecting a
	 * handbook narrows the list through the taxonomy query var, which is the
	 * robust way to group by handbook; the taxonomy column itself is deliberately
	 * not sortable, because a page may belong to several handbooks.
	 *
	 * @param string $post_type The post type of the list being shown.
	 * @return void
	 */
	public function handbook_filter_dropdown( string $post_type ): void {
		if ( Handbook::POST_TYPE !== $post_type ) {
			return;
		}
		$taxonomy = Handbooks::TAXONOMY;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $taxonomy ] ) ) : '';
		wp_dropdown_categories(
			array(
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'value_field'     => 'slug',
				'show_option_all' => __( 'All handbooks', 'living-handbook' ),
				'hide_empty'      => false,
				'hierarchical'    => true,
				'selected'        => $current,
			)
		);
	}
}
