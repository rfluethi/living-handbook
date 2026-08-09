<?php
// phpcs:ignoreFile
/**
 * Development seed: create many handbook pages for a rough performance test.
 *
 * Run it in the Local site shell (which ships wp-cli):
 *   wp eval-file wp-content/plugins/living-handbook/bin/seed-performance.php
 *
 * Options:
 *   LH_SEED_PAGES=500  how many pages to create (default 2000)
 *   LH_SEED_RESET=1    delete the pages of a previous run first
 *
 * It creates one handbook "Performance-Test" (public, so it renders without a
 * login) with top-level "Bereich" pages, each holding child pages, and assigns
 * random taxonomy terms, a last-updated date and a review date with an
 * interval, so that all three freshness states occur and the badges are part of
 * what gets measured.
 *
 * The default is 2000 pages. A handbook that large makes the cost of a render
 * visible and a query that runs once per page impossible to overlook; 300 hid
 * both. Measure what it costs with bin/measure-performance.php, and remove the
 * data again with LH_SEED_RESET=1 (or by deleting the handbook by hand).
 *
 * @package LivingHandbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lh_total = (int) ( getenv( 'LH_SEED_PAGES' ) ?: 2000 );
$lh_total = max( 1, $lh_total );
$lh_reset = '' !== (string) getenv( 'LH_SEED_RESET' );

$lh_post_type    = 'handbook';
$lh_handbook_tax = 'handbook_set';

/**
 * Report a line, through WP-CLI when it is there.
 *
 * @param string $line Line.
 * @return void
 */
function lh_seed_say( $line ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $line );
	} else {
		echo esc_html( $line ) . "\n";
	}
}

/**
 * Find or create terms, returning their ids.
 *
 * @param string   $taxonomy Taxonomy.
 * @param string[] $names    Term names.
 * @return int[]
 */
function lh_seed_terms( $taxonomy, $names ) {
	$ids = array();
	foreach ( $names as $name ) {
		$existing = term_exists( $name, $taxonomy );
		if ( ! $existing ) {
			$existing = wp_insert_term( $name, $taxonomy );
		}
		if ( ! is_wp_error( $existing ) ) {
			$ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
		}
	}
	return $ids;
}

// The handbook (grouping term), public so it renders without a login.
$lh_term = term_exists( 'Performance-Test', $lh_handbook_tax );
if ( ! $lh_term ) {
	$lh_term = wp_insert_term( 'Performance-Test', $lh_handbook_tax, array( 'slug' => 'performance-test' ) );
}
$lh_handbook_id = (int) ( is_array( $lh_term ) ? $lh_term['term_id'] : $lh_term );
update_term_meta( $lh_handbook_id, 'living_handbook_visibility', 'public' );

// What a previous run left behind. The lookup is an internal one: it must see
// every page regardless of who is running WP-CLI.
$lh_existing = get_posts(
	LivingHandbook\Access\AccessController::internal(
		array(
			'post_type'      => $lh_post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy' => $lh_handbook_tax,
					'field'    => 'term_id',
					'terms'    => array( $lh_handbook_id ),
				),
			),
		)
	)
);

if ( $lh_existing && ! $lh_reset ) {
	lh_seed_say( sprintf( 'The handbook "Performance-Test" already holds %d pages.', count( $lh_existing ) ) );
	lh_seed_say( 'Measure those with bin/measure-performance.php, or re-seed with LH_SEED_RESET=1.' );
	return;
}

if ( $lh_existing ) {
	lh_seed_say( sprintf( 'Deleting %d pages from the previous run.', count( $lh_existing ) ) );
	foreach ( $lh_existing as $lh_old_id ) {
		wp_delete_post( (int) $lh_old_id, true );
	}
}

// Seeding thousands of pages recounts every term on every insert otherwise, and
// that alone takes longer than the inserts.
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );

$lh_types  = lh_seed_terms( 'handbook_type', array( 'Guide', 'Process description', 'FAQ' ) );
$lh_topics = lh_seed_terms( 'handbook_topic', array( 'Alpha', 'Beta', 'Gamma', 'Delta' ) );
$lh_roles  = lh_seed_terms( 'handbook_role', array( 'Content creators', 'Coordination', 'Tech' ) );
$lh_aud    = lh_seed_terms( 'handbook_audience', array( 'All members', 'Tech' ) );

$lh_created = 0;
$lh_areas   = (int) max( 1, ceil( $lh_total / 15 ) );

for ( $a = 1; $a <= $lh_areas && $lh_created < $lh_total; $a++ ) {
	$parent_id = wp_insert_post(
		array(
			'post_type'    => $lh_post_type,
			'post_status'  => 'publish',
			'post_title'   => sprintf( 'Bereich %02d', $a ),
			'post_content' => '<!-- wp:paragraph --><p>Testinhalt für Bereich ' . $a . '.</p><!-- /wp:paragraph -->',
			'post_excerpt' => 'Testbereich ' . $a . ' für den Lasttest.',
			'menu_order'   => $a,
		)
	);
	if ( is_wp_error( $parent_id ) ) {
		continue;
	}
	wp_set_object_terms( $parent_id, array( $lh_handbook_id ), $lh_handbook_tax );
	if ( $lh_topics ) {
		wp_set_object_terms( $parent_id, array( $lh_topics[ array_rand( $lh_topics ) ] ), 'handbook_topic' );
	}
	update_post_meta( $parent_id, 'living_handbook_last_updated', gmdate( 'Y-m-d' ) );
	update_post_meta( $parent_id, 'living_handbook_last_reviewed', gmdate( 'Y-m-d' ) );
	update_post_meta( $parent_id, 'living_handbook_review_interval', 180 );
	$lh_created++;

	for ( $c = 1; $c <= 14 && $lh_created < $lh_total; $c++ ) {
		$child_id = wp_insert_post(
			array(
				'post_type'    => $lh_post_type,
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_title'   => sprintf( 'Seite %02d-%02d', $a, $c ),
				'post_content' => '<!-- wp:heading --><h2>Abschnitt</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Testinhalt für Seite ' . $a . '-' . $c . '.</p><!-- /wp:paragraph -->',
				'post_excerpt' => 'Kurzbeschreibung Seite ' . $a . '-' . $c . '.',
				'menu_order'   => $c,
			)
		);
		if ( is_wp_error( $child_id ) ) {
			continue;
		}
		wp_set_object_terms( $child_id, array( $lh_handbook_id ), $lh_handbook_tax );
		if ( $lh_types ) {
			wp_set_object_terms( $child_id, array( $lh_types[ array_rand( $lh_types ) ] ), 'handbook_type' );
		}
		if ( $lh_topics ) {
			wp_set_object_terms( $child_id, array( $lh_topics[ array_rand( $lh_topics ) ] ), 'handbook_topic' );
		}
		if ( $lh_roles ) {
			wp_set_object_terms( $child_id, array( $lh_roles[ array_rand( $lh_roles ) ] ), 'handbook_role' );
		}
		if ( $lh_aud ) {
			wp_set_object_terms( $child_id, array( $lh_aud[ array_rand( $lh_aud ) ] ), 'handbook_audience' );
		}
		// Spread over more than twice the review interval, so that reviewed, due
		// and overdue all occur and the freshness badge is really rendered.
		$lh_age = random_int( 0, 400 );
		update_post_meta( $child_id, 'living_handbook_last_updated', gmdate( 'Y-m-d', time() - $lh_age * DAY_IN_SECONDS ) );
		update_post_meta( $child_id, 'living_handbook_last_reviewed', gmdate( 'Y-m-d', time() - $lh_age * DAY_IN_SECONDS ) );
		update_post_meta( $child_id, 'living_handbook_review_interval', 180 );
		$lh_created++;

		if ( 0 === $lh_created % 250 ) {
			lh_seed_say( sprintf( '  %d of %d pages', $lh_created, $lh_total ) );
		}
	}
}

wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
clean_term_cache( array( $lh_handbook_id ), $lh_handbook_tax );
if ( class_exists( 'LivingHandbook\Frontend\Navigation' ) ) {
	LivingHandbook\Frontend\Navigation::invalidate();
}
wp_cache_flush();

$lh_message = sprintf( '%d pages created in the handbook "Performance-Test".', $lh_created );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::success( $lh_message );
} else {
	echo esc_html( $lh_message ) . "\n";
}
