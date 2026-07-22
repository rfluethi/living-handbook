<?php
/**
 * The app's own handbook, shipped with the plugin.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads a small, complete handbook on request, so a fresh install has something
 * real to look at.
 *
 * A plugin that only offers empty structure is hard to judge: the page type, the
 * vocabularies and the freshness badges all make sense once they are filled, and
 * not before. So the plugin ships its own handbook, which documents how a Living
 * Handbook is built and is written as a working handbook, and loads it when
 * someone asks for it. Nothing is created on activation; content that appears by
 * itself is content the site owner did not ask for.
 *
 * It is an ordinary bundle, minus the ZIP and minus media, so it goes
 * through HandbookImport like any other bundle and needs no second import path.
 * Two details are handled here rather than in the file:
 *
 * - Vocabulary terms are referenced by token, not by slug. The seeded terms are
 *   translated on creation, so their slugs depend on the site language, and a
 *   file with fixed English slugs would create a second set of terms on a German
 *   site. A token is resolved against the term that actually exists.
 * - Review dates are stored as a number of days, not as a date. A fixed date
 *   would make every page overdue a year after release, which is exactly the
 *   wrong first impression of the freshness feature.
 */
final class AppHandbook {

	/**
	 * The admin-post action that loads the app handbook.
	 */
	private const ACTION = 'living_handbook_load_app_handbook';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Whether the current user may load the app handbook. Loading it writes content,
	 * so it needs the same capability as any other import.
	 *
	 * @return bool
	 */
	public static function can_load(): bool {
		return HandbookImport::can_import();
	}

	/**
	 * The action name, for the form on the import screen.
	 *
	 * @return string
	 */
	public static function action(): string {
		return self::ACTION;
	}

	/**
	 * The app handbook file for the current admin language, with English as fallback.
	 *
	 * @return string Absolute path, or '' when no file is readable.
	 */
	public static function file(): string {
		$base   = LIVING_HANDBOOK_DIR . 'assets/app-handbook/app-handbook-';
		$locale = determine_locale();
		if ( 0 === strpos( $locale, 'de' ) && is_readable( $base . 'de.json' ) ) {
			return $base . 'de.json';
		}
		return is_readable( $base . 'en.json' ) ? $base . 'en.json' : '';
	}

	/**
	 * The handbook the app handbook would go into, if it is already there.
	 *
	 * Used by the import screen to say "already loaded" instead of offering the
	 * same button again, and to link to the result.
	 *
	 * @return WP_Term|null
	 */
	public static function existing_handbook(): ?WP_Term {
		// The raw file is enough here: resolving terms and dates would mean a
		// couple of dozen term lookups just to decide whether to show a link.
		$manifest = self::decode();
		if ( null === $manifest ) {
			return null;
		}
		$slug = isset( $manifest['handbook']['slug'] ) ? (string) $manifest['handbook']['slug'] : '';
		if ( '' === $slug ) {
			return null;
		}
		$term = get_term_by( 'slug', $slug, Handbooks::TAXONOMY );
		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Read and validate the shipped file, without resolving anything.
	 *
	 * @return array<string, mixed>|null Null when the file is missing or unusable.
	 */
	private static function decode(): ?array {
		$file = self::file();
		if ( '' === $file ) {
			return null;
		}

		// A file shipped inside the plugin directory, not an upload, so a plain
		// read is right here; WP_Filesystem exists for writing and for remote
		// filesystems, neither of which applies.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $file );
		if ( false === $raw ) {
			return null;
		}

		$manifest = json_decode( $raw, true );
		if ( ! is_array( $manifest ) || ! isset( $manifest['format'] ) || 'living-handbook-bundle' !== $manifest['format'] ) {
			return null;
		}

		return $manifest;
	}

	/**
	 * Read the shipped file and turn it into a manifest the importer accepts.
	 *
	 * @return array<string, mixed>|null Null when the file is missing or unusable.
	 */
	public static function manifest(): ?array {
		$manifest = self::decode();
		if ( null === $manifest ) {
			return null;
		}

		$pages = isset( $manifest['pages'] ) && is_array( $manifest['pages'] ) ? $manifest['pages'] : array();
		foreach ( $pages as $index => $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$page['terms'] = self::resolve_terms( isset( $page['terms'] ) && is_array( $page['terms'] ) ? $page['terms'] : array() );
			$page['meta']  = self::resolve_meta( isset( $page['meta'] ) && is_array( $page['meta'] ) ? $page['meta'] : array() );

			$manifest['pages'][ $index ] = $page;
		}

		return $manifest;
	}

	/**
	 * The vocabulary tokens the app handbook uses, per taxonomy.
	 *
	 * The values are the same source strings the seeder uses, so a token resolves
	 * to the seeded term in whatever language the site runs. The two topics are
	 * not seeded and are created by the import; they exist so the topic vocabulary
	 * is shown filled rather than empty.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function tokens(): array {
		return array(
			Taxonomies::PAGE_TYPE => array(
				'guide'               => __( 'Guide', 'living-handbook' ),
				'process-description' => __( 'Process description', 'living-handbook' ),
				'tool-overview'       => __( 'Tool overview', 'living-handbook' ),
				'role-organisation'   => __( 'Role / Organisation', 'living-handbook' ),
				'background-concept'  => __( 'Background / Concept', 'living-handbook' ),
				'faq'                 => __( 'FAQ', 'living-handbook' ),
				'area-overview'       => __( 'Area overview', 'living-handbook' ),
			),
			Taxonomies::AUDIENCE  => array(
				'all-members'      => __( 'All members', 'living-handbook' ),
				'content-creators' => __( 'Content creators', 'living-handbook' ),
				'coordination'     => __( 'Coordination', 'living-handbook' ),
				'tech'             => __( 'Tech', 'living-handbook' ),
			),
			Taxonomies::ROLE      => array(
				'handbook-editors'  => __( 'Handbook editors', 'living-handbook' ),
				'github-specialist' => __( 'GitHub specialist', 'living-handbook' ),
			),
			Taxonomies::TOPIC     => array(
				'documentation' => __( 'Documentation', 'living-handbook' ),
				'import'        => __( 'Import', 'living-handbook' ),
				'quality'       => __( 'Quality', 'living-handbook' ),
			),
		);
	}

	/**
	 * Turn the token lists of one page into the slug and name pairs the importer
	 * expects, preferring the slug of a term that is already there.
	 *
	 * @param array<string, mixed> $terms Token lists per taxonomy.
	 * @return array<string, array<int, array<string, string>>>
	 */
	private static function resolve_terms( array $terms ): array {
		$resolved = array();
		foreach ( self::tokens() as $taxonomy => $map ) {
			$list    = isset( $terms[ $taxonomy ] ) && is_array( $terms[ $taxonomy ] ) ? $terms[ $taxonomy ] : array();
			$entries = array();
			foreach ( $list as $token ) {
				$token = (string) $token;
				if ( ! isset( $map[ $token ] ) ) {
					continue;
				}
				$name      = $map[ $token ];
				$existing  = get_term_by( 'name', $name, $taxonomy );
				$entries[] = array(
					'slug' => $existing instanceof WP_Term ? $existing->slug : sanitize_title( $name ),
					'name' => $name,
				);
			}
			$resolved[ $taxonomy ] = $entries;
		}
		return $resolved;
	}

	/**
	 * Turn the relative review age of one page into an absolute date, and name the
	 * person who loads it as the reviewer, so the pages show a filled field rather
	 * than an empty one.
	 *
	 * @param array<string, mixed> $meta Meta from the shipped file.
	 * @return array<string, mixed>
	 */
	private static function resolve_meta( array $meta ): array {
		if ( ! isset( $meta['review_days_ago'] ) ) {
			return $meta;
		}

		$days                  = absint( $meta['review_days_ago'] );
		$meta['last_reviewed'] = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
		unset( $meta['review_days_ago'] );

		$user = wp_get_current_user();
		if ( $user->exists() ) {
			$meta['reviewer'] = $user->user_login;
		}

		return $meta;
	}

	/**
	 * The folder the shipped files live in.
	 *
	 * @return string Absolute path with a trailing slash.
	 */
	public static function directory(): string {
		return LIVING_HANDBOOK_DIR . 'assets/app-handbook/';
	}

	/**
	 * Read one media file named in the manifest.
	 *
	 * The manifest is shipped inside the plugin, so the path is not user input.
	 * It is still resolved and checked against the folder: a reader that hands
	 * back whatever path it is given is the kind of thing that becomes a hole
	 * later, when someone reuses it for a file that did not come from here.
	 *
	 * @param string $file Path relative to the app handbook folder.
	 * @return string|false The bytes, or false.
	 */
	public static function read_media( string $file ) {
		if ( '' === $file ) {
			return false;
		}

		$base = realpath( self::directory() );
		$path = realpath( self::directory() . $file );
		if ( false === $base || false === $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) ) {
			return false;
		}
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return file_get_contents( $path );
	}

	/**
	 * Load the app handbook and go back to the import screen with the report.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! self::can_load() ) {
			wp_die( esc_html__( 'You are not allowed to import content.', 'living-handbook' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$import = new HandbookImport();

		$manifest = self::manifest();
		if ( null === $manifest ) {
			$import->finish( array( 'error' => __( 'The app handbook could not be read.', 'living-handbook' ) ) );
		}

		// An explicitly chosen handbook overrides the one named in the file, so
		// the pages can be put next to existing content instead of standing in a
		// handbook of their own.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$chosen = isset( $_POST['handbook'] ) ? absint( wp_unslash( $_POST['handbook'] ) ) : 0;

		// Skip is the only sensible rule here: loading it twice should
		// never overwrite the edits someone made while trying it out.
		$report = $import->import_manifest(
			(array) $manifest,
			static function ( string $file ) {
				return self::read_media( $file );
			},
			HandbookImport::RULE_SKIP,
			$chosen
		);

		$import->finish( $report );
	}
}
