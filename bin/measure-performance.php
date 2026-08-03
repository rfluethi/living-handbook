<?php
// phpcs:ignoreFile
/**
 * Development measurement: what a handbook view costs in queries and time.
 *
 * Seed a large handbook first, then measure it:
 *   wp eval-file wp-content/plugins/living-handbook/bin/seed-performance.php
 *   wp eval-file wp-content/plugins/living-handbook/bin/measure-performance.php
 *
 * Options:
 *   LH_MEASURE_HANDBOOK=performance-test  handbook slug to measure
 *   LH_MEASURE_TOP=5                      how many query patterns to list
 *
 * Every view is rendered twice: once after `wp_cache_flush()` ("cold", what a
 * site without a persistent object cache pays on every request) and once
 * straight after ("warm", what a site with one pays). The difference between
 * the two is what caching already saves; the cold number is what has to get
 * smaller.
 *
 * Views are rendered from the plugin's own block templates with the theme's
 * header and footer removed, so the numbers are the plugin's, not the theme's.
 * The current user is set to 0: a logged-out visitor is the cheapest case, and
 * anything slow there is slow for everyone.
 *
 * Query timing needs SAVEQUERIES. It is defined here, which is late: queries
 * WordPress ran while booting are not counted. That is deliberate, the boot is
 * not what this measures.
 *
 * @package LivingHandbook
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

/**
 * Report a line, through WP-CLI when it is there.
 *
 * @param string $line Line.
 * @return void
 */
function lh_measure_say( $line ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $line );
	} else {
		echo $line . "\n";
	}
}

/**
 * Reduce a query to its shape, so repetitions of the same query with different
 * ids collapse into one pattern. That is what an N+1 looks like in the output.
 *
 * @param string $sql Query.
 * @return string
 */
function lh_measure_pattern( $sql ) {
	$sql = preg_replace( '/\s+/', ' ', trim( (string) $sql ) );
	$sql = preg_replace( "/'[^']*'/", "'?'", $sql );
	$sql = preg_replace( '/\bIN \([^)]*\)/i', 'IN (...)', $sql );
	$sql = preg_replace( '/\b\d+\b/', 'N', $sql );
	return (string) $sql;
}

/**
 * Render once and report what it cost.
 *
 * @param string   $label  What is being rendered.
 * @param string   $state  'cold' or 'warm'.
 * @param callable $render Returns the rendered HTML.
 * @param int      $top    How many query patterns to list.
 * @return void
 */
function lh_measure_run( $label, $state, $render, $top ) {
	global $wpdb;

	$wpdb->queries = array();
	$mem_before    = memory_get_usage();
	$start         = microtime( true );
	$html          = (string) $render();
	$wall_ms       = ( microtime( true ) - $start ) * 1000;
	$queries       = is_array( $wpdb->queries ) ? $wpdb->queries : array();
	$wpdb->queries = array();

	$db_ms = 0.0;
	foreach ( $queries as $query ) {
		$db_ms += (float) $query[1] * 1000;
	}

	lh_measure_say(
		sprintf(
			'  %-5s %4d queries, %7.1f ms total, %6.1f ms in the database, %5.1f KB HTML, %5.1f MB peak',
			$state,
			count( $queries ),
			$wall_ms,
			$db_ms,
			strlen( $html ) / 1024,
			( memory_get_usage() - $mem_before ) / 1048576
		)
	);

	if ( $top < 1 || ! $queries ) {
		return;
	}

	$patterns = array();
	foreach ( $queries as $query ) {
		$pattern              = lh_measure_pattern( $query[0] );
		$patterns[ $pattern ] = ( $patterns[ $pattern ] ?? 0 ) + 1;
	}
	arsort( $patterns );

	$shown = 0;
	foreach ( $patterns as $pattern => $count ) {
		if ( $count < 2 || $shown >= $top ) {
			break;
		}
		lh_measure_say( sprintf( '        %3dx %s', $count, substr( $pattern, 0, 120 ) ) );
		$shown++;
	}
	if ( 0 === $shown ) {
		lh_measure_say( '        no query repeated' );
	}
}

/**
 * Render a view cold and warm.
 *
 * @param string   $label  What is being rendered.
 * @param callable $render Returns the rendered HTML.
 * @param int      $top    How many query patterns to list.
 * @return void
 */
function lh_measure_view( $label, $render, $top ) {
	lh_measure_say( '' );
	lh_measure_say( $label );
	wp_cache_flush();
	lh_measure_run( $label, 'cold', $render, $top );
	lh_measure_run( $label, 'warm', $render, 0 );
}

/**
 * The plugin's own block template, without the theme's header and footer.
 *
 * @param string $slug Template slug.
 * @return string Block markup, empty when the template is not registered.
 */
function lh_measure_template( $slug ) {
	if ( ! function_exists( 'get_block_template' ) ) {
		return '';
	}
	$template = get_block_template( 'living-handbook//' . $slug );
	if ( ! $template || ! is_string( $template->content ) ) {
		return '';
	}
	return (string) preg_replace( '#<!-- wp:template-part .*?/-->#', '', $template->content );
}

if ( ! class_exists( 'LivingHandbook\Frontend\Entry' ) ) {
	lh_measure_say( 'The Living Handbook plugin is not active in this site.' );
	return;
}

$lh_slug = (string) ( getenv( 'LH_MEASURE_HANDBOOK' ) ?: 'performance-test' );
$lh_top  = (int) ( getenv( 'LH_MEASURE_TOP' ) ?: 5 );
$lh_term = get_term_by( 'slug', $lh_slug, 'handbook_set' );

if ( ! $lh_term instanceof WP_Term ) {
	lh_measure_say( sprintf( 'No handbook with the slug "%s". Run bin/seed-performance.php first.', $lh_slug ) );
	return;
}

// Measure as a logged-out visitor, whatever user WP-CLI is running as.
wp_set_current_user( 0 );

$lh_pages = get_posts(
	array(
		'post_type'        => 'handbook',
		'handbook_set'     => $lh_slug,
		'numberposts'      => 1,
		'orderby'          => 'ID',
		'order'            => 'DESC',
		'suppress_filters' => false,
	)
);
$lh_page  = $lh_pages ? $lh_pages[0] : null;

lh_measure_say( sprintf( 'Handbook "%s": %d pages.', $lh_term->name, (int) $lh_term->count ) );

$lh_entry_template  = lh_measure_template( 'taxonomy-handbook_set' );
$lh_single_template = lh_measure_template( 'single-handbook' );

// The entry page: the handbook's landing view, areas plus recently updated.
if ( '' === $lh_entry_template ) {
	lh_measure_say( 'The entry template is not registered; needs WordPress 6.7 or newer.' );
} else {
	lh_measure_view(
		'Entry page',
		function () use ( $lh_slug, $lh_entry_template ) {
			$query                     = new WP_Query(
				array(
					'post_type'    => 'handbook',
					'handbook_set' => $lh_slug,
				)
			);
			$previous                  = $GLOBALS['wp_query'];
			$GLOBALS['wp_query']       = $query;
			$GLOBALS['wp_the_query']   = $query;
			$html                      = do_blocks( $lh_entry_template );
			$GLOBALS['wp_query']       = $previous;
			$GLOBALS['wp_the_query']   = $previous;
			return $html;
		},
		$lh_top
	);
}

// A single page: navigation, badges, content, on this page, metadata footer.
if ( ! $lh_page ) {
	lh_measure_say( 'The handbook has no pages to render.' );
} elseif ( '' === $lh_single_template ) {
	lh_measure_say( 'The single template is not registered; needs WordPress 6.7 or newer.' );
} else {
	lh_measure_view(
		sprintf( 'Single page "%s"', $lh_page->post_title ),
		function () use ( $lh_page, $lh_single_template ) {
			$query                   = new WP_Query(
				array(
					'p'         => $lh_page->ID,
					'post_type' => 'handbook',
				)
			);
			$previous                = $GLOBALS['wp_query'];
			$GLOBALS['wp_query']     = $query;
			$GLOBALS['wp_the_query'] = $query;
			$query->the_post();
			$html                    = do_blocks( $lh_single_template );
			wp_reset_postdata();
			$GLOBALS['wp_query']     = $previous;
			$GLOBALS['wp_the_query'] = $previous;
			return $html;
		},
		$lh_top
	);
}

// The navigation tree on its own: it is the one part that is built from the
// whole handbook, so it is the part that grows with the number of pages.
if ( $lh_page ) {
	lh_measure_view(
		'Navigation tree only',
		function () use ( $lh_page ) {
			return LivingHandbook\Frontend\Navigation::render_for_post( $lh_page->ID );
		},
		$lh_top
	);
}

lh_measure_say( '' );
lh_measure_say( 'Cold is an empty object cache, warm is the same render straight after.' );
