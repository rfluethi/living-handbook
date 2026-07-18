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
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;

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

		echo '<ul>';
		foreach ( $overdue as $item ) {
			printf(
				'<li><a href="%1$s">%2$s</a> (%3$s)</li>',
				esc_url( $item['link'] ),
				esc_html( $item['title'] ),
				esc_html( FreshnessStatus::label( $item['status'] ) )
			);
		}
		echo '</ul>';
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
			echo esc_html( '' !== $reviewed ? $reviewed : '—' );
			if ( '' !== $label ) {
				printf( ' <span>(%s)</span>', esc_html( $label ) );
			}
		} elseif ( 'living_handbook_feedback' === $column ) {
			$yes = (int) get_post_meta( $post_id, Feedback::YES, true );
			$no  = (int) get_post_meta( $post_id, Feedback::NO, true );
			echo esc_html( sprintf( '%1$d / %2$d', $yes, $no ) );
		}
	}
}
