<?php
// phpcs:ignoreFile
/**
 * Development seed: create many handbook pages for a rough performance test.
 *
 * Run it in the Local site shell (which ships wp-cli):
 *   wp eval-file wp-content/plugins/living-handbook/bin/seed-performance.php
 *
 * Optional page count:
 *   LH_SEED_PAGES=500 wp eval-file wp-content/plugins/living-handbook/bin/seed-performance.php
 *
 * It creates one handbook "Performance-Test" (public, so it renders without a
 * login) with top-level "Bereich" pages, each holding child pages, and assigns
 * random vocabulary terms plus a last-updated date. To remove the test data,
 * delete the "Performance-Test" handbook and its pages again.
 *
 * @package LivingHandbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lh_total = (int) ( getenv( 'LH_SEED_PAGES' ) ?: 300 );
$lh_total = max( 1, $lh_total );

$lh_post_type    = 'handbook';
$lh_handbook_tax = 'handbook_set';

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
		update_post_meta( $child_id, 'living_handbook_last_updated', gmdate( 'Y-m-d', time() - random_int( 0, 200 ) * DAY_IN_SECONDS ) );
		$lh_created++;
	}
}

$lh_message = sprintf( '%d Handbuch-Seiten im Handbuch "Performance-Test" angelegt.', $lh_created );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::success( $lh_message );
} else {
	echo esc_html( $lh_message ) . "\n";
}
