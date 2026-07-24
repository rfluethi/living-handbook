<?php
/**
 * GitHub sync: pages whose source is GitHub are pulled from a Markdown URL.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Git;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\HtmlSanitizer;
use LivingHandbook\Import\ImageRefs;
use LivingHandbook\Import\MarkdownConverter;
use LivingHandbook\Import\MarkdownImportPage;
use LivingHandbook\Import\Postprocessor;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_Error;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements the GitHub sync hybrid (concept 06, way 1). Each handbook page has
 * a source, WordPress or GitHub. A GitHub page carries a Markdown source URL and
 * is pulled and re-rendered when it is saved, on demand, and on a schedule whose
 * frequency is configurable. On each pull the transport metadata is applied and
 * parents and internal links are resolved, just like the import. Its content
 * editor is removed in the backend so it cannot be edited by hand; the page
 * overview shows the source, and a dedicated block marks the public page. GitHub
 * pages are stored as rendered HTML, not editable blocks, since a cron job has no
 * browser to convert HTML into blocks.
 *
 * The pulled HTML comes from an external repository, so it is run through the
 * shared HtmlSanitizer allowlist before it is stored, which strips scripts,
 * event handlers and unsafe URLs while keeping the Mermaid and details markup.
 * The source URL is restricted to an allowlist of hosts, so an editor cannot
 * point the server at an arbitrary internal address. The scheduled sync works in
 * bounded batches, so a large handbook does not fetch every page in one request.
 *
 * The sync frequency and the uninstall behaviour are configured on the plugin
 * settings page, which lives in the Settings class and uses the Settings API;
 * this class only owns the option names and the scheduling. Sync failures are
 * flagged per page and surfaced as an admin notice.
 */
final class GitSync {

	public const META_SOURCE = 'living_handbook_source';
	public const META_URL    = 'living_handbook_markdown_source';

	public const SOURCE_WORDPRESS = 'wordpress';
	public const SOURCE_GITHUB    = 'github';

	/**
	 * Option that decides whether uninstall removes all handbook content.
	 */
	public const OPTION_UNINSTALL = 'living_handbook_uninstall_content';

	/**
	 * Option holding the configured background sync frequency.
	 */
	public const OPTION_SCHEDULE = 'living_handbook_sync_schedule';

	private const META_STATUS = '_lh_sync_status';

	private const META_ERROR = '_lh_sync_error';

	/**
	 * Marks a page that stands for a repository folder with no Markdown file of
	 * its own, so a second import finds it again instead of creating another.
	 */
	private const META_FOLDER = '_lh_github_folder';

	/**
	 * How many files one folder import may create. A guard against pointing the
	 * import at a repository root and waiting for a thousand pages.
	 */
	private const MAX_FOLDER_FILES = 200;

	private const CRON_HOOK = 'living_handbook_git_sync';

	public const OPTION_CRON_OFFSET = 'living_handbook_sync_offset';

	private const SETTINGS_SLUG = 'living-handbook-sync';

	private const SCHEDULES = array( 'off', 'hourly', 'twicedaily', 'daily', 'weekly' );

	/**
	 * Hosts a Markdown source may be fetched from (guards against SSRF).
	 */
	private const ALLOWED_HOSTS = array( 'raw.githubusercontent.com' );

	/**
	 * Maximum number of GitHub pages the scheduled sync pulls per run.
	 */
	private const CRON_BATCH = 20;

	/**
	 * Guard against recursion while sync_page or a finalize pass writes the post.
	 *
	 * @var bool
	 */
	private static bool $is_syncing = false;

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Handbook::POST_TYPE, array( $this, 'save_meta' ) );
		add_action( 'load-post.php', array( $this, 'maybe_lock_editor' ) );
		add_action( 'admin_notices', array( $this, 'locked_notice' ) );
		add_action( 'admin_notices', array( $this, 'sync_error_notice' ) );
		add_action( 'admin_post_living_handbook_git_sync_now', array( $this, 'sync_now' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_sync' ) );
		// One-off follow-up runs of a large sync use their own hook, so the guard
		// in run_sync() can tell them apart from the recurring event.
		add_action( self::CRON_HOOK . '_continue', array( $this, 'run_sync' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_filter( 'manage_' . Handbook::POST_TYPE . '_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_' . Handbook::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		// Priority 20 so the source dropdown renders after the taxonomy filters
		// (Maintenance, priority 10), keeping the on-screen order in step with the
		// list columns: the vocabularies, the handbook, then the source.
		add_action( 'restrict_manage_posts', array( $this, 'source_filter_dropdown' ), 20 );
		// filter_by_source() merges with any existing meta_query instead of
		// overwriting it, so the source filter coexists with other list filters.
		add_action( 'pre_get_posts', array( $this, 'filter_by_source' ), 20 );
	}

	/**
	 * The selectable sync frequencies, as a value to label map. Used by the
	 * settings page and to validate the stored option.
	 *
	 * @return array<string, string>
	 */
	public static function schedule_choices(): array {
		return array(
			'off'        => __( 'Off (only on save and Sync now)', 'living-handbook' ),
			'hourly'     => __( 'Hourly', 'living-handbook' ),
			'twicedaily' => __( 'Twice daily', 'living-handbook' ),
			'daily'      => __( 'Daily', 'living-handbook' ),
			'weekly'     => __( 'Weekly', 'living-handbook' ),
		);
	}

	/**
	 * The timestamp of the next scheduled background sync, or 0 when none is
	 * scheduled. Exposed so the settings page can show it without knowing the
	 * cron hook name.
	 *
	 * @return int
	 */
	public static function next_scheduled(): int {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		return false !== $timestamp ? (int) $timestamp : 0;
	}

	/**
	 * Schedule the recurring sync from the configured frequency.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		$recurrence = self::current_schedule();
		if ( 'off' === $recurrence ) {
			return;
		}
		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, $recurrence, self::CRON_HOOK );
		}
	}

	/**
	 * Clear the scheduled sync. Clears every event for the hook, including any
	 * one-off follow-up runs, so no straggler is left behind.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::CRON_HOOK . '_continue' );
	}

	/**
	 * Re-apply the schedule after the frequency changes.
	 *
	 * @return void
	 */
	public static function reschedule(): void {
		self::unschedule();
		self::schedule();
	}

	/**
	 * The configured sync frequency, validated.
	 *
	 * @return string
	 */
	private static function current_schedule(): string {
		$value = (string) get_option( self::OPTION_SCHEDULE, 'weekly' );
		return in_array( $value, self::SCHEDULES, true ) ? $value : 'weekly';
	}

	/**
	 * Turn a github.com blob URL into a raw.githubusercontent.com URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 1 === preg_match( '#^https?://github\.com/([^/]+)/([^/]+)/blob/(.+)$#', $url, $matches ) ) {
			return 'https://raw.githubusercontent.com/' . $matches[1] . '/' . $matches[2] . '/' . $matches[3];
		}
		return $url;
	}

	/**
	 * Whether a (normalized) source URL is safe to fetch: https, and a host on
	 * the allowlist. The scheme is checked so a plain-text http URL cannot be
	 * fetched and tampered with in transit.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_allowed_source( string $url ): bool {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( 'https' !== $scheme ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return false;
		}

		/**
		 * Filter the hosts a Markdown source may be fetched from.
		 *
		 * @param string[] $hosts Allowed host names.
		 */
		$allowed = apply_filters( 'living_handbook_sync_allowed_hosts', self::ALLOWED_HOSTS );

		return in_array( strtolower( $host ), array_map( 'strtolower', (array) $allowed ), true );
	}

	/**
	 * Create a locked GitHub page from a source URL and pull it once, or refresh
	 * the existing page for that URL on a re-import.
	 *
	 * The slug is taken from the source file name so that internal .md links,
	 * which reference other pages by file name, resolve to these pages. A folder
	 * import overrides it for an index or README file, where the file name is not
	 * a useful slug, with the folder name.
	 *
	 * @param string $url           Markdown source URL (raw or blob).
	 * @param int    $handbook_id   Optional handbook term id.
	 * @param string $title         Optional fallback title (used until a heading is found).
	 * @param bool   $publish       Whether a newly created page is published rather than drafted.
	 * @param string $slug_override Slug to use instead of the one from the file name.
	 * @return int Post id, or 0 on failure.
	 */
	public function create_github_page( string $url, int $handbook_id = 0, string $title = '', bool $publish = false, string $slug_override = '' ): int {
		$url = self::normalize_url( $url );
		if ( '' === $url || ! self::is_allowed_source( $url ) ) {
			return 0;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$slug = '' !== $slug_override
			? sanitize_title( $slug_override )
			: sanitize_title( pathinfo( is_string( $path ) ? $path : $url, PATHINFO_FILENAME ) );

		// Re-import protection: if a page already tracks this source URL, refresh
		// it instead of creating a duplicate. But never refresh a page the
		// current user may not edit (the import needs only edit_posts); create a
		// fresh page for this user instead.
		$post_id = self::find_by_url( $url, $handbook_id );
		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			$post_id = 0;
		}
		$created_new = ( 0 === $post_id );
		if ( $created_new ) {
			$inserted = wp_insert_post(
				array(
					'post_type'   => Handbook::POST_TYPE,
					'post_status' => $publish ? 'publish' : 'draft',
					'post_title'  => '' !== $title ? $title : __( 'GitHub page', 'living-handbook' ),
					'post_name'   => $slug,
				),
				true
			);
			if ( is_wp_error( $inserted ) ) {
				return 0;
			}
			$post_id = (int) $inserted;
		}

		update_post_meta( $post_id, self::META_SOURCE, self::SOURCE_GITHUB );
		update_post_meta( $post_id, self::META_URL, $url );
		if ( 0 < $handbook_id ) {
			wp_set_object_terms( $post_id, array( $handbook_id ), Handbooks::TAXONOMY );
		}

		$this->sync_page( $post_id );

		// A wrong URL (a valid host but a missing file) only fails when the page
		// is fetched. For a page we just created, do not leave an empty draft
		// behind: delete it and report the failure to the import screen. An
		// existing page keeps its previous content on a failed refresh.
		if ( $created_new && '' !== (string) get_post_meta( $post_id, self::META_ERROR, true ) ) {
			wp_delete_post( $post_id, true );
			return 0;
		}

		return $post_id;
	}

	/**
	 * Find an existing GitHub-sourced page that tracks a given source URL, so a
	 * re-import refreshes it instead of creating a duplicate.
	 *
	 * @param string $url         Normalized Markdown source URL.
	 * @param int    $handbook_id Target handbook term id (0 for none).
	 * @return int Existing post id, or 0.
	 */
	private static function find_by_url( string $url, int $handbook_id ): int {
		$args = array(
			'post_type'      => Handbook::POST_TYPE,
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => self::META_URL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		);
		if ( $handbook_id > 0 ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Handbooks::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $handbook_id,
				),
			);
		}
		$found = get_posts( $args );
		return ! empty( $found ) ? (int) $found[0] : 0;
	}

	/**
	 * Import every Markdown file under a GitHub folder (a tree URL) as a locked
	 * page, keeping the folder structure as page hierarchy.
	 *
	 * Subfolders are descended into. The whole repository tree is read in a
	 * single request to the Git trees API, not one contents request per folder:
	 * unauthenticated GitHub allows 60 requests an hour, so a walk that spends
	 * one on every folder runs out on a documentation repository of any size.
	 *
	 * A folder becomes a page, so the structure survives the import. Which page
	 * depends on what is in it: an index.md or README.md becomes the folder's own
	 * page and its siblings hang under it; a folder with neither gets a page made
	 * from its name, holding the area entries block, because a level that exists
	 * in the repository but not in the handbook would break the navigation.
	 *
	 * @param string $tree_url    A github.com tree URL to a folder.
	 * @param int    $handbook_id Optional handbook term id.
	 * @param bool   $publish     Whether newly created pages are published rather than drafted.
	 * @return array<string, mixed>|WP_Error The pages on success, a WP_Error on failure.
	 */
	public function import_folder( string $tree_url, int $handbook_id = 0, bool $publish = false ) {
		$parsed = self::parse_tree_url( $tree_url );
		if ( null === $parsed ) {
			return new WP_Error( 'living_handbook_import', __( 'Not a GitHub folder URL.', 'living-handbook' ), array( 'status' => 400 ) );
		}

		$tree = $this->fetch_tree( $parsed );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$files = self::markdown_under( $tree['entries'], $parsed['path'] );
		if ( array() === $files ) {
			return new WP_Error( 'living_handbook_import', __( 'No Markdown files found in that folder.', 'living-handbook' ), array( 'status' => 404 ) );
		}

		$notes = array();
		if ( true === $tree['truncated'] ) {
			$notes[] = __( 'The repository is too large for GitHub to return its tree in one piece, so this import may be incomplete.', 'living-handbook' );
		}
		if ( count( $files ) > self::MAX_FOLDER_FILES ) {
			$files   = array_slice( $files, 0, self::MAX_FOLDER_FILES );
			$notes[] = sprintf(
				/* translators: %d: maximum number of files imported from one folder. */
				__( 'Only the first %d files were imported. Import the remaining subfolders separately.', 'living-handbook' ),
				self::MAX_FOLDER_FILES
			);
		}

		$base = rtrim( $parsed['path'], '/' );
		$plan = self::plan_folder_import( $files, $base );

		// Folders first, shallow before deep, so a parent page always exists
		// before the pages that hang under it.
		$pages     = array();
		$ids       = array();
		$folder_id = array( $base => 0 );

		// One rising counter for the whole import. It is only the fallback order:
		// a page that carries a transport "Reihenfolge" keeps that, see place().
		$auto = 0;

		foreach ( $plan['folders'] as $folder ) {
			if ( '' !== $folder['index'] ) {
				// The index file's own name (index/README) is a poor slug, so the
				// folder name is used instead.
				$post_id = $this->create_github_page( self::raw_url( $parsed, $folder['index'] ), $handbook_id, '', $publish, basename( $folder['path'] ) );
			} else {
				$post_id = $this->create_folder_page( $parsed, $folder['path'], $handbook_id, $publish );
			}
			if ( 0 === $post_id ) {
				continue;
			}
			$folder_id[ $folder['path'] ] = $post_id;
			$auto                        += 10;
			$this->place( $post_id, self::parent_id_for( $folder_id, self::dirname_of( $folder['path'] ) ), $auto );
			// The repository path lets internal links resolve exactly by path,
			// regardless of the page's slug (a README's page takes the folder's).
			if ( '' !== $folder['index'] ) {
				update_post_meta( $post_id, Postprocessor::META_SOURCE_PATH, $folder['index'] );
			}
			$ids[]   = $post_id;
			$pages[] = self::page_result( $post_id );
		}

		foreach ( $plan['files'] as $file ) {
			$post_id = $this->create_github_page( self::raw_url( $parsed, $file['path'] ), $handbook_id, '', $publish );
			if ( 0 === $post_id ) {
				continue;
			}
			$auto += 10;
			$this->place( $post_id, self::parent_id_for( $folder_id, $file['folder'] ), $auto );
			update_post_meta( $post_id, Postprocessor::META_SOURCE_PATH, $file['path'] );
			$ids[]   = $post_id;
			$pages[] = self::page_result( $post_id );
		}

		// Resolve internal links once every page of the import exists. Parents are
		// set here from the folder structure, which is more reliable than the
		// transport block for a repository that carries none. A link with no page
		// is turned into text, so nothing is left to 404; the leftovers are
		// reported so a typo or a missing page is a line here, not a click away.
		$report = Postprocessor::finalize_report( $ids );
		foreach ( $report['unresolved'] as $link ) {
			$notes[] = sprintf(
				/* translators: 1: page title the link is on, 2: link target file name. */
				__( 'On "%1$s": the link to %2$s points at no page, so it was shown as plain text. Add that page, or fix the link.', 'living-handbook' ),
				$link['source'],
				$link['target']
			);
		}

		$result = array( 'pages' => $pages );
		if ( array() !== $notes ) {
			$result['notes'] = $notes;
		}
		return $result;
	}

	/**
	 * Import a local folder of Markdown as handbook pages, the same way the GitHub
	 * folder import does, but reading the files from disk. This is how the app
	 * handbook that ships inside the plugin is loaded, so it needs no network and
	 * always matches the installed version. Images referenced by a relative path
	 * are sideloaded from the folder, and internal links are resolved once every
	 * page of the import exists. The pages are ordinary WordPress pages, editable
	 * and not synced, distinguished by their stored source path so a re-import
	 * refreshes them instead of duplicating.
	 *
	 * @param string $dir         Absolute path to the folder.
	 * @param int    $handbook_id Optional handbook term id.
	 * @param bool   $publish     Whether new pages are published rather than drafted.
	 * @return array<string, mixed>|WP_Error The pages on success, a WP_Error on failure.
	 */
	public function import_local_folder( string $dir, int $handbook_id = 0, bool $publish = false ) {
		$dir = rtrim( $dir, '/' );
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return new WP_Error( 'living_handbook_import', __( 'The app handbook folder was not found in the plugin.', 'living-handbook' ), array( 'status' => 404 ) );
		}

		$files = self::local_markdown_paths( $dir );
		if ( array() === $files ) {
			return new WP_Error( 'living_handbook_import', __( 'No Markdown files were found in the app handbook folder.', 'living-handbook' ), array( 'status' => 404 ) );
		}

		$notes = array();
		if ( count( $files ) > self::MAX_FOLDER_FILES ) {
			$files   = array_slice( $files, 0, self::MAX_FOLDER_FILES );
			$notes[] = sprintf(
				/* translators: %d: maximum number of files imported from one folder. */
				__( 'Only the first %d files were imported.', 'living-handbook' ),
				self::MAX_FOLDER_FILES
			);
		}

		$plan      = self::plan_folder_import( $files, '' );
		$pages     = array();
		$ids       = array();
		$folder_id = array( '' => 0 );
		$auto      = 0;

		foreach ( $plan['folders'] as $folder ) {
			if ( '' !== $folder['index'] ) {
				$post_id = $this->create_local_page( $dir, $folder['index'], $handbook_id, $publish, basename( $folder['path'] ) );
			} else {
				$post_id = $this->create_local_folder_page( $folder['path'], $handbook_id, $publish );
			}
			if ( 0 === $post_id ) {
				continue;
			}
			$folder_id[ $folder['path'] ] = $post_id;
			$auto                        += 10;
			$this->place( $post_id, self::parent_id_for( $folder_id, self::dirname_of( $folder['path'] ) ), $auto );
			if ( '' !== $folder['index'] ) {
				update_post_meta( $post_id, Postprocessor::META_SOURCE_PATH, $folder['index'] );
			}
			$ids[]   = $post_id;
			$pages[] = self::page_result( $post_id );
		}

		foreach ( $plan['files'] as $file ) {
			$post_id = $this->create_local_page( $dir, $file['path'], $handbook_id, $publish );
			if ( 0 === $post_id ) {
				continue;
			}
			$auto += 10;
			$this->place( $post_id, self::parent_id_for( $folder_id, $file['folder'] ), $auto );
			update_post_meta( $post_id, Postprocessor::META_SOURCE_PATH, $file['path'] );
			$ids[]   = $post_id;
			$pages[] = self::page_result( $post_id );
		}

		$report = Postprocessor::finalize_report( $ids );
		foreach ( $report['unresolved'] as $link ) {
			$notes[] = sprintf(
				/* translators: 1: page title the link is on, 2: link target file name. */
				__( 'On "%1$s": the link to %2$s points at no page, so it was shown as plain text. Add that page, or fix the link.', 'living-handbook' ),
				$link['source'],
				$link['target']
			);
		}

		$result = array( 'pages' => $pages );
		if ( array() !== $notes ) {
			$result['notes'] = $notes;
		}
		return $result;
	}

	/**
	 * Read the repository tree in one request.
	 *
	 * @param array{owner:string, repo:string, branch:string, path:string} $parsed Parsed tree URL.
	 * @return array{entries: array<int, array<string, mixed>>, truncated: bool}|WP_Error
	 */
	private function fetch_tree( array $parsed ) {
		$api = 'https://api.github.com/repos/' . rawurlencode( $parsed['owner'] ) . '/' . rawurlencode( $parsed['repo'] )
			. '/git/trees/' . rawurlencode( $parsed['branch'] ) . '?recursive=1';

		$response = wp_safe_remote_get(
			$api,
			array(
				'timeout'             => 20,
				'redirection'         => 0,
				'limit_response_size' => 5 * MB_IN_BYTES,
				'headers'             => array(
					'User-Agent' => 'LivingHandbook',
					'Accept'     => 'application/vnd.github+json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'living_handbook_import', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			if ( 403 === $code && '0' === (string) wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ) ) {
				return new WP_Error( 'living_handbook_import', __( 'GitHub API rate limit reached (unauthenticated, 60 requests per hour). Try again later.', 'living-handbook' ), array( 'status' => 429 ) );
			}
			/* translators: %d: HTTP status code returned by the GitHub API. */
			return new WP_Error( 'living_handbook_import', sprintf( __( 'GitHub API HTTP %d', 'living-handbook' ), $code ), array( 'status' => 502 ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['tree'] ) || ! is_array( $body['tree'] ) ) {
			return new WP_Error( 'living_handbook_import', __( 'Unexpected GitHub API response.', 'living-handbook' ), array( 'status' => 502 ) );
		}

		return array(
			'entries'   => $body['tree'],
			'truncated' => ! empty( $body['truncated'] ),
		);
	}

	/**
	 * Work out which page each repository path becomes, and what hangs under what.
	 *
	 * Separated from the import because this is the part that is easy to get
	 * wrong and impossible to check by looking at it: it decides whether a folder
	 * gets its own page or an invented one, which file is consumed as that page,
	 * and which folder every remaining file belongs to. It touches nothing and
	 * fetches nothing, so a test can hand it a tree and read the answer.
	 *
	 * @param array<int, string> $files Markdown paths, shallow first.
	 * @param string             $base  The folder the import points at.
	 * @return array{folders: array<int, array{path: string, index: string}>, files: array<int, array{path: string, folder: string}>}
	 */
	public static function plan_folder_import( array $files, string $base ): array {
		$base     = rtrim( $base, '/' );
		$folders  = array();
		$consumed = array();

		foreach ( self::folders_of( $files, $base ) as $folder ) {
			$index = self::index_file_of( $files, $folder );
			if ( '' !== $index ) {
				$consumed[ $index ] = true;
			}
			$folders[] = array(
				'path'  => $folder,
				'index' => $index,
			);
		}

		$rest = array();
		foreach ( $files as $file ) {
			// Only index files that actually became a folder's page are dropped
			// here. The README of the folder the import points at has no folder
			// page above it, so it stays an ordinary top-level page instead of
			// vanishing.
			if ( isset( $consumed[ $file ] ) ) {
				continue;
			}
			$rest[] = array(
				'path'   => $file,
				'folder' => self::dirname_of( $file ),
			);
		}

		return array(
			'folders' => $folders,
			'files'   => $rest,
		);
	}

	/**
	 * The Markdown files under a base path, shallow first and alphabetical.
	 *
	 * @param array<int, array<string, mixed>> $entries Tree entries from the API.
	 * @param string                           $base    Base path.
	 * @return array<int, string> Repository paths.
	 */
	public static function markdown_under( array $entries, string $base ): array {
		$base   = rtrim( $base, '/' );
		$prefix = '' === $base ? '' : $base . '/';
		$files  = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || 'blob' !== ( isset( $entry['type'] ) ? $entry['type'] : '' ) ) {
				continue;
			}
			$path = isset( $entry['path'] ) ? (string) $entry['path'] : '';
			if ( '' === $path || 'md' !== strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				continue;
			}
			if ( '' !== $prefix && 0 !== strpos( $path, $prefix ) ) {
				continue;
			}
			$files[] = $path;
		}

		usort(
			$files,
			static function ( string $a, string $b ): int {
				$depth = substr_count( $a, '/' ) <=> substr_count( $b, '/' );
				return 0 !== $depth ? $depth : strcmp( $a, $b );
			}
		);

		return $files;
	}

	/**
	 * Every folder that holds at least one of the files, shallow first, base
	 * folder excluded (its page is the handbook, not a page).
	 *
	 * @param array<int, string> $files Repository paths.
	 * @param string             $base  Base path.
	 * @return array<int, string>
	 */
	private static function folders_of( array $files, string $base ): array {
		$folders = array();
		foreach ( $files as $file ) {
			$folder = self::dirname_of( $file );
			while ( '' !== $folder && $folder !== $base && ! isset( $folders[ $folder ] ) ) {
				$folders[ $folder ] = substr_count( $folder, '/' );
				$folder             = self::dirname_of( $folder );
			}
		}
		asort( $folders );
		return array_keys( $folders );
	}

	/**
	 * The folder's own file, if it has one: index.md wins over README.md.
	 *
	 * @param array<int, string> $files  Repository paths.
	 * @param string             $folder Folder path.
	 * @return string Path, or '' when the folder has neither.
	 */
	private static function index_file_of( array $files, string $folder ): string {
		$found = '';
		foreach ( $files as $file ) {
			if ( self::dirname_of( $file ) !== $folder || ! self::is_index_file( $file ) ) {
				continue;
			}
			if ( 'index.md' === strtolower( basename( $file ) ) ) {
				return $file;
			}
			$found = $file;
		}
		return $found;
	}

	/**
	 * Whether a file is a folder's own page.
	 *
	 * @param string $file Repository path.
	 * @return bool
	 */
	private static function is_index_file( string $file ): bool {
		return in_array( strtolower( basename( $file ) ), array( 'index.md', 'readme.md' ), true );
	}

	/**
	 * The folder part of a repository path.
	 *
	 * @param string $path Repository path.
	 * @return string
	 */
	private static function dirname_of( string $path ): string {
		$cut = strrpos( $path, '/' );
		return false === $cut ? '' : substr( $path, 0, $cut );
	}

	/**
	 * The raw URL of a file in the imported repository.
	 *
	 * @param array{owner:string, repo:string, branch:string, path:string} $parsed Parsed tree URL.
	 * @param string                                                       $file   Repository path.
	 * @return string
	 */
	private static function raw_url( array $parsed, string $file ): string {
		$segments = array_map( 'rawurlencode', explode( '/', $file ) );
		return 'https://raw.githubusercontent.com/' . rawurlencode( $parsed['owner'] ) . '/'
			. rawurlencode( $parsed['repo'] ) . '/' . rawurlencode( $parsed['branch'] ) . '/'
			. implode( '/', $segments );
	}

	/**
	 * The page a folder's contents hang under, falling back to the nearest
	 * ancestor that has one.
	 *
	 * @param array<string, int> $folder_id Folder path to post ID.
	 * @param string             $folder    Folder path.
	 * @return int Post ID, or 0 for a top-level page.
	 */
	private static function parent_id_for( array $folder_id, string $folder ): int {
		while ( '' !== $folder ) {
			if ( isset( $folder_id[ $folder ] ) ) {
				return $folder_id[ $folder ];
			}
			$folder = self::dirname_of( $folder );
		}
		return isset( $folder_id[''] ) ? $folder_id[''] : 0;
	}

	/**
	 * Create, or find again, the page that stands for a folder without its own
	 * Markdown file.
	 *
	 * It is not synced, because there is no file behind it, and it carries the
	 * area entries block so it lists what is inside it instead of being blank.
	 *
	 * @param array{owner:string, repo:string, branch:string, path:string} $parsed      Parsed tree URL.
	 * @param string                                                       $folder      Folder path.
	 * @param int                                                          $handbook_id Optional handbook term id.
	 * @param bool                                                         $publish     Whether the page is published rather than drafted.
	 * @return int Post ID, or 0.
	 */
	private function create_folder_page( array $parsed, string $folder, int $handbook_id = 0, bool $publish = false ): int {
		$marker = $parsed['owner'] . '/' . $parsed['repo'] . '@' . $parsed['branch'] . ':' . $folder;

		$existing = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::META_FOLDER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $marker, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$name  = basename( $folder );
		$title = ucfirst( trim( (string) preg_replace( '/[-_]+/', ' ', $name ) ) );

		$inserted = wp_insert_post(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => $publish ? 'publish' : 'draft',
				'post_title'   => '' !== $title ? $title : $name,
				'post_name'    => sanitize_title( $name ),
				'post_content' => '<!-- wp:living-handbook/entry {"display":"cards"} /-->',
			),
			true
		);
		if ( is_wp_error( $inserted ) ) {
			return 0;
		}

		$post_id = (int) $inserted;
		update_post_meta( $post_id, self::META_FOLDER, $marker );
		update_post_meta( $post_id, self::META_SOURCE, self::SOURCE_WORDPRESS );
		if ( 0 < $handbook_id ) {
			wp_set_object_terms( $post_id, array( $handbook_id ), Handbooks::TAXONOMY );
		}
		return $post_id;
	}

	/**
	 * The Markdown files under a local folder, as paths relative to it, shallow
	 * first and alphabetical, so it matches the order the GitHub tree walk uses.
	 *
	 * @param string $dir Absolute folder path.
	 * @return array<int, string> Relative paths.
	 */
	private static function local_markdown_paths( string $dir ): array {
		$files    = array();
		$prefix   = rtrim( $dir, '/' ) . '/';
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( ! $file instanceof \SplFileInfo || ! $file->isFile() || 'md' !== strtolower( $file->getExtension() ) ) {
				continue;
			}
			$path = $file->getPathname();
			if ( 0 === strpos( $path, $prefix ) ) {
				$files[] = substr( $path, strlen( $prefix ) );
			}
		}

		usort(
			$files,
			static function ( string $a, string $b ): int {
				$depth = substr_count( $a, '/' ) <=> substr_count( $b, '/' );
				return 0 !== $depth ? $depth : strcmp( $a, $b );
			}
		);

		return $files;
	}

	/**
	 * Create, or refresh on a re-import, a handbook page from a local Markdown
	 * file. The page is an ordinary WordPress page (editable, not synced); its
	 * source path is stored so a re-import finds it again instead of duplicating.
	 *
	 * @param string $base_dir      Absolute folder the import points at.
	 * @param string $rel_path      File path relative to that folder.
	 * @param int    $handbook_id   Optional handbook term id.
	 * @param bool   $publish       Whether a new page is published rather than drafted.
	 * @param string $slug_override Slug to use instead of the one from the file name.
	 * @return int Post id, or 0 on failure.
	 */
	private function create_local_page( string $base_dir, string $rel_path, int $handbook_id, bool $publish, string $slug_override = '' ): int {
		$abs = rtrim( $base_dir, '/' ) . '/' . ltrim( $rel_path, '/' );
		if ( ! is_file( $abs ) || ! is_readable( $abs ) ) {
			return 0;
		}
		$slug = '' !== $slug_override
			? sanitize_title( $slug_override )
			: sanitize_title( pathinfo( $rel_path, PATHINFO_FILENAME ) );

		$post_id = self::find_local_by_path( $rel_path, $handbook_id );
		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			$post_id = 0;
		}
		if ( 0 === $post_id ) {
			$inserted = wp_insert_post(
				array(
					'post_type'   => Handbook::POST_TYPE,
					'post_status' => $publish ? 'publish' : 'draft',
					'post_title'  => __( 'App handbook page', 'living-handbook' ),
					'post_name'   => $slug,
				),
				true
			);
			if ( is_wp_error( $inserted ) ) {
				return 0;
			}
			$post_id = (int) $inserted;
		}

		update_post_meta( $post_id, self::META_SOURCE, self::SOURCE_WORDPRESS );
		if ( 0 < $handbook_id ) {
			wp_set_object_terms( $post_id, array( $handbook_id ), Handbooks::TAXONOMY );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a bundled plugin file from disk, not a remote resource.
		$markdown  = (string) file_get_contents( $abs );
		$image_map = $this->local_image_map( $markdown, $abs, $base_dir );
		$this->render_markdown_into_post( $post_id, $markdown, $image_map );

		return $post_id;
	}

	/**
	 * Create, or find again, the page that stands for a local folder without its
	 * own Markdown file. Mirrors create_folder_page for the bundled import.
	 *
	 * @param string $folder      Folder path relative to the import base.
	 * @param int    $handbook_id Optional handbook term id.
	 * @param bool   $publish     Whether the page is published rather than drafted.
	 * @return int Post id, or 0.
	 */
	private function create_local_folder_page( string $folder, int $handbook_id = 0, bool $publish = false ): int {
		$marker = 'local:' . $folder;

		$existing = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::META_FOLDER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $marker, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$name  = basename( $folder );
		$title = ucfirst( trim( (string) preg_replace( '/[-_]+/', ' ', $name ) ) );

		$inserted = wp_insert_post(
			array(
				'post_type'    => Handbook::POST_TYPE,
				'post_status'  => $publish ? 'publish' : 'draft',
				'post_title'   => '' !== $title ? $title : $name,
				'post_name'    => sanitize_title( $name ),
				'post_content' => '<!-- wp:living-handbook/entry {"display":"cards"} /-->',
			),
			true
		);
		if ( is_wp_error( $inserted ) ) {
			return 0;
		}

		$post_id = (int) $inserted;
		update_post_meta( $post_id, self::META_FOLDER, $marker );
		update_post_meta( $post_id, self::META_SOURCE, self::SOURCE_WORDPRESS );
		if ( 0 < $handbook_id ) {
			wp_set_object_terms( $post_id, array( $handbook_id ), Handbooks::TAXONOMY );
		}
		return $post_id;
	}

	/**
	 * Find an existing local-import page by its stored source path, so a re-import
	 * refreshes it instead of creating a duplicate. Scoped to the target handbook
	 * when one is given.
	 *
	 * @param string $rel_path    File path relative to the import base.
	 * @param int    $handbook_id Target handbook term id (0 for none).
	 * @return int Post id, or 0.
	 */
	private static function find_local_by_path( string $rel_path, int $handbook_id ): int {
		$args = array(
			'post_type'      => Handbook::POST_TYPE,
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => Postprocessor::META_SOURCE_PATH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $rel_path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		);
		if ( $handbook_id > 0 ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Handbooks::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $handbook_id,
				),
			);
		}
		$found = get_posts( $args );
		return ! empty( $found ) ? (int) $found[0] : 0;
	}

	/**
	 * Build the image map for a local page: for each image the Markdown references
	 * by a relative path, resolve it against the file's folder, read it from disk
	 * and sideload it. Kept inside the import base, so a crafted "../" reference
	 * cannot read a file outside the bundled folder.
	 *
	 * @param string $markdown Markdown source.
	 * @param string $abs_file Absolute path of the Markdown file.
	 * @param string $base_dir Absolute import base folder.
	 * @return array<string, string> File name to media URL.
	 */
	private function local_image_map( string $markdown, string $abs_file, string $base_dir ): array {
		$map      = array();
		$base_dir = rtrim( $base_dir, '/' );
		$file_dir = dirname( $abs_file );
		foreach ( ImageRefs::extract( $markdown ) as $ref ) {
			$clean = (string) preg_replace( '/[?#].*$/', '', $ref );
			$name  = basename( $clean );
			if ( '' === $name || isset( $map[ $name ] ) ) {
				continue;
			}
			$path = self::normalize_path( $file_dir . '/' . ltrim( $clean, '/' ) );
			if ( 0 !== strpos( $path . '/', $base_dir . '/' ) || ! is_file( $path ) || ! is_readable( $path ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a bundled plugin file from disk, not a remote resource.
			$data = (string) file_get_contents( $path );
			if ( '' === $data ) {
				continue;
			}
			$url = MarkdownImportPage::sideload_image( $name, $data );
			if ( '' !== $url ) {
				$map[ $name ] = $url;
			}
		}
		return $map;
	}

	/**
	 * Place a page under its parent, and give it a menu order only when its own
	 * content did not already carry one.
	 *
	 * A page with a transport "Reihenfolge" block has had its menu order set by
	 * the sync, and that wins: the repository states the order explicitly.
	 * Everything else falls back to the import position, so a folder without
	 * transport metadata is still ordered sensibly rather than all at zero.
	 *
	 * @param int $post_id   Page ID.
	 * @param int $parent_id Parent page ID, or 0.
	 * @param int $fallback  Menu order to use when the page carries none.
	 * @return void
	 */
	private function place( int $post_id, int $parent_id, int $fallback ): void {
		$current = (int) get_post_field( 'menu_order', $post_id );
		$this->set_parent( $post_id, $parent_id, $current > 0 ? 0 : $fallback );
	}

	/**
	 * Put a page under its parent.
	 *
	 * On a re-import this overwrites a parent set by hand, deliberately: for a
	 * folder import the repository is the source of truth for the structure, the
	 * same way it is for the content of a synced page.
	 *
	 * @param int $post_id   Page ID.
	 * @param int $parent_id Parent page ID, or 0.
	 * @param int $order     Menu order, or 0 to leave it alone.
	 * @return void
	 */
	private function set_parent( int $post_id, int $parent_id, int $order = 0 ): void {
		if ( $post_id === $parent_id ) {
			return;
		}
		$data = array(
			'ID'          => $post_id,
			'post_parent' => $parent_id,
		);
		if ( $order > 0 ) {
			$data['menu_order'] = $order;
		}
		wp_update_post( $data );
	}

	/**
	 * One entry of the import result.
	 *
	 * @param int $post_id Page ID.
	 * @return array<string, mixed>
	 */
	private static function page_result( int $post_id ): array {
		return array(
			'id'      => $post_id,
			'title'   => get_the_title( $post_id ),
			'editUrl' => add_query_arg(
				array(
					'post'   => $post_id,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			),
		);
	}

	/**
	 * Parse a github.com tree URL into owner, repo, branch, and path.
	 *
	 * @param string $url Tree URL.
	 * @return array{owner:string, repo:string, branch:string, path:string}|null
	 */
	private static function parse_tree_url( string $url ): ?array {
		$url = trim( $url );
		if ( 1 !== preg_match( '#^https?://github\.com/([^/]+)/([^/]+)/tree/([^/]+)/(.+)$#', $url, $matches ) ) {
			return null;
		}
		$path = (string) preg_replace( '/[?#].*$/', '', $matches[4] );
		return array(
			'owner'  => $matches[1],
			'repo'   => $matches[2],
			'branch' => $matches[3],
			'path'   => $path,
		);
	}

	/**
	 * Register the source and Markdown URL meta, REST-readable.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$auth = static function (): bool {
			return current_user_can( 'edit_posts' );
		};
		register_post_meta(
			Handbook::POST_TYPE,
			self::META_SOURCE,
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => self::SOURCE_WORDPRESS,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		register_post_meta(
			Handbook::POST_TYPE,
			self::META_URL,
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
	}

	/**
	 * Register the editor meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'living_handbook_git',
			__( 'Source', 'living-handbook' ),
			array( $this, 'render_meta_box' ),
			Handbook::POST_TYPE,
			'side'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'living_handbook_git', 'living_handbook_git_nonce' );
		$source = (string) get_post_meta( $post->ID, self::META_SOURCE, true );
		if ( '' === $source ) {
			$source = self::SOURCE_WORDPRESS;
		}
		$url    = (string) get_post_meta( $post->ID, self::META_URL, true );
		$status = (string) get_post_meta( $post->ID, self::META_STATUS, true );
		?>
		<p><label><input type="radio" name="living_handbook_source" value="wordpress" <?php checked( self::SOURCE_WORDPRESS, $source ); ?>> <?php esc_html_e( 'Maintained in WordPress', 'living-handbook' ); ?></label></p>
		<p><label><input type="radio" name="living_handbook_source" value="github" <?php checked( self::SOURCE_GITHUB, $source ); ?>> <?php esc_html_e( 'Synced from GitHub', 'living-handbook' ); ?></label></p>
		<p>
			<label for="living_handbook_markdown_source"><?php esc_html_e( 'Markdown source (raw or blob URL)', 'living-handbook' ); ?></label>
			<input type="url" id="living_handbook_markdown_source" name="living_handbook_markdown_source" value="<?php echo esc_attr( $url ); ?>" class="widefat" placeholder="https://github.com/... or https://raw.githubusercontent.com/...">
		</p>
		<?php if ( self::SOURCE_GITHUB === $source && '' !== $url ) : ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=living_handbook_git_sync_now&post=' . $post->ID ), 'living_handbook_git_sync_now_' . $post->ID ) ); ?>"><?php esc_html_e( 'Sync now', 'living-handbook' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $status ) : ?>
			<p class="description"><?php esc_html_e( 'Last sync:', 'living-handbook' ); ?> <?php echo esc_html( $status ); ?></p>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'A GitHub page is pulled when you save it, and its content editor is locked. Change the source here.', 'living-handbook' ); ?></p>
		<?php
	}

	/**
	 * Save the meta box fields, then pull the page if it is GitHub-sourced.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function save_meta( int $post_id ): void {
		if ( self::$is_syncing || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['living_handbook_git_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['living_handbook_git_nonce'] ) ), 'living_handbook_git' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw    = isset( $_POST['living_handbook_source'] ) ? sanitize_text_field( wp_unslash( $_POST['living_handbook_source'] ) ) : '';
		$source = self::SOURCE_GITHUB === $raw ? self::SOURCE_GITHUB : self::SOURCE_WORDPRESS;
		update_post_meta( $post_id, self::META_SOURCE, $source );

		// Sanitize on the way in, then normalize the blob URL and validate it as
		// a URL.
		$raw_url = isset( $_POST['living_handbook_markdown_source'] )
			? sanitize_text_field( wp_unslash( $_POST['living_handbook_markdown_source'] ) )
			: '';
		$url     = esc_url_raw( self::normalize_url( $raw_url ) );
		update_post_meta( $post_id, self::META_URL, $url );

		if ( self::SOURCE_GITHUB === $source && '' !== $url ) {
			$this->sync_page( $post_id );
			// The finalize pass writes the post again; guard against re-entering
			// this save handler (the nonce is still present in this request).
			self::$is_syncing = true;
			Postprocessor::finalize( array( $post_id ) );
			self::$is_syncing = false;
		}
	}

	/**
	 * Remove the content editor for a GitHub page so it cannot be edited by hand.
	 *
	 * @return void
	 */
	public function maybe_lock_editor(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the post id to decide the edit screen; no state change.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		if ( 0 === $post_id || Handbook::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		if ( self::SOURCE_GITHUB === get_post_meta( $post_id, self::META_SOURCE, true ) ) {
			remove_post_type_support( Handbook::POST_TYPE, 'editor' );
		}
	}

	/**
	 * Show a notice on the edit screen of a GitHub-synced page.
	 *
	 * @return void
	 */
	public function locked_notice(): void {
		$screen = get_current_screen();
		if ( null === $screen || Handbook::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( self::SOURCE_GITHUB !== get_post_meta( $post->ID, self::META_SOURCE, true ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'This page is synced from GitHub. Its content is managed in the repository and cannot be edited here.', 'living-handbook' ) . '</p></div>';
	}

	/**
	 * Show an admin notice listing GitHub pages whose last sync failed.
	 *
	 * @return void
	 */
	public function sync_error_notice(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen ) {
			return;
		}
		$relevant = Handbook::POST_TYPE === $screen->post_type || false !== strpos( (string) $screen->id, self::SETTINGS_SLUG );
		if ( ! $relevant ) {
			return;
		}

		$ids   = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::META_ERROR, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$count = count( $ids );
		if ( 0 === $count ) {
			return;
		}

		$message = sprintf(
			/* translators: %d: number of GitHub pages that failed to sync. */
			_n(
				'Living Handbook: %d GitHub page could not be synced. Open it to see the error.',
				'Living Handbook: %d GitHub pages could not be synced. Open them to see the error.',
				$count,
				'living-handbook'
			),
			$count
		);

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
		$more = '';
		if ( $count > $limit ) {
			$more = sprintf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: number of further failed pages not listed. */
						__( '… and %d more.', 'living-handbook' ),
						$count - $limit
					)
				)
			);
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s</p><ul>%2$s</ul>%3$s</div>',
			esc_html( $message ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_url and esc_html above.
			$items,
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html above.
			$more
		);
	}

	/**
	 * Handle the "Sync now" action.
	 *
	 * @return void
	 */
	public function sync_now(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is verified by check_admin_referer below, which needs the post id from the URL first.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) || ! check_admin_referer( 'living_handbook_git_sync_now_' . $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'living-handbook' ) );
		}
		$this->sync_page( $post_id );
		Postprocessor::finalize( array( $post_id ) );
		$link = get_edit_post_link( $post_id, 'url' );
		wp_safe_redirect( is_string( $link ) ? $link : admin_url() );
		exit;
	}

	/**
	 * Cron handler: sync GitHub-sourced pages in bounded batches.
	 *
	 * Each run pulls at most CRON_BATCH pages, advancing a stored offset so that,
	 * over several runs, every page is covered without one run fetching them all.
	 * When a batch leaves pages unsynced, a one-off follow-up event is scheduled
	 * a minute later, so a full pass does not wait for the next recurring tick.
	 *
	 * @return void
	 */
	public function run_sync(): void {
		$ids = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => self::SOURCE_GITHUB, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$total = count( $ids );
		if ( 0 === $total ) {
			delete_option( self::OPTION_CRON_OFFSET );
			return;
		}

		$offset = (int) get_option( self::OPTION_CRON_OFFSET, 0 );
		if ( $offset >= $total ) {
			$offset = 0;
		}

		$batch = array_slice( $ids, $offset, self::CRON_BATCH );
		$done  = array();
		foreach ( $batch as $id ) {
			$this->sync_page( (int) $id );
			$done[] = (int) $id;
		}
		Postprocessor::finalize( $done );

		$next = $offset + self::CRON_BATCH;
		if ( $next < $total ) {
			update_option( self::OPTION_CRON_OFFSET, $next );
			if ( false === wp_next_scheduled( self::CRON_HOOK . '_continue' ) ) {
				wp_schedule_single_event( time() + 60, self::CRON_HOOK . '_continue' );
			}
		} else {
			update_option( self::OPTION_CRON_OFFSET, 0 );
		}
	}

	/**
	 * Pull one page from its Markdown source, store the HTML, and apply transport.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	private function sync_page( int $post_id ): void {
		if ( self::$is_syncing ) {
			return;
		}
		if ( ! MarkdownConverter::available() ) {
			$this->set_sync_error( $post_id, __( 'Error: CommonMark is not installed.', 'living-handbook' ) );
			return;
		}
		$url = (string) get_post_meta( $post_id, self::META_URL, true );
		if ( '' === $url ) {
			return;
		}
		if ( ! self::is_allowed_source( $url ) ) {
			$this->set_sync_error( $post_id, __( 'Error: source host not allowed.', 'living-handbook' ) );
			return;
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'redirection'         => 0,
				'limit_response_size' => 5 * MB_IN_BYTES,
			)
		);
		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message from the HTTP request. */
			$this->set_sync_error( $post_id, sprintf( __( 'Error: %s', 'living-handbook' ), $response->get_error_message() ) );
			return;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code. */
			$this->set_sync_error( $post_id, sprintf( __( 'Error: HTTP %d', 'living-handbook' ), $code ) );
			return;
		}
		$markdown = (string) wp_remote_retrieve_body( $response );

		// Bring the images the page references along: fetch each one from the
		// repository and sideload it, so a relative link like ../assets/x.svg
		// points at the media library instead of a path that 404s on the site.
		$image_map = $this->github_image_map( $markdown, $url );

		$this->render_markdown_into_post( $post_id, $markdown, $image_map );

		update_post_meta( $post_id, Metadata::UPDATED, current_time( 'Y-m-d' ) );
		delete_post_meta( $post_id, self::META_ERROR );
		$this->set_status(
			$post_id,
			sprintf(
				/* translators: %s: date and time of the successful sync. */
				__( 'OK %s', 'living-handbook' ),
				current_time( 'Y-m-d H:i' )
			)
		);
	}

	/**
	 * Convert a Markdown string and store it as the page's content, title,
	 * transport metadata and resolved internal links. Shared by the GitHub sync
	 * (Markdown fetched over HTTP) and the bundled app-handbook import (Markdown
	 * read from disk), so both render a page the same way. The image map maps a
	 * file name to a media URL, so the converter can point relative image
	 * references at the sideloaded copies.
	 *
	 * @param int                   $post_id   Post id.
	 * @param string                $markdown  Markdown source.
	 * @param array<string, string> $image_map File name to media URL.
	 * @return void
	 */
	private function render_markdown_into_post( int $post_id, string $markdown, array $image_map = array() ): void {
		$result = ( new MarkdownConverter() )->convert( $markdown, $image_map );
		$html   = $this->mermaid_to_html( (string) $result['html'] );
		// The HTML came from outside the editor; strip anything unsafe before it
		// is stored.
		$html = HtmlSanitizer::clean( $html );

		$update = array(
			'ID'           => $post_id,
			'post_content' => (string) wp_slash( $html ),
		);
		$title  = (string) $result['title'];
		if ( '' !== $title ) {
			$update['post_title'] = $title;
		}

		self::$is_syncing = true;
		wp_update_post( $update );
		Postprocessor::apply_transport( $post_id, (array) $result['transport'] );
		// Re-rendering brings the internal .md links back raw, so resolve them to
		// their pages again. A folder import resolves once more at the end, when
		// every page of the import exists.
		Postprocessor::convert_md_links( $post_id );
		self::$is_syncing = false;
	}

	/**
	 * Build the image map for a GitHub page: for each image the Markdown
	 * references by a relative path, resolve it against the page's source URL,
	 * fetch it from the repository and sideload it into the media library. The map
	 * is keyed by file name, which is how the converter rewrites the image
	 * sources. Sideloading dedupes by file name and content, so a shared image is
	 * stored once and a later sync reuses it instead of piling up copies. An image
	 * that cannot be fetched is skipped, and its reference is left untouched.
	 *
	 * @param string $markdown   The page Markdown.
	 * @param string $source_url The page's raw Markdown source URL.
	 * @return array<string, string> File name to media URL.
	 */
	private function github_image_map( string $markdown, string $source_url ): array {
		$map = array();
		foreach ( ImageRefs::extract( $markdown ) as $ref ) {
			$name = basename( (string) preg_replace( '/[?#].*$/', '', $ref ) );
			if ( '' === $name || isset( $map[ $name ] ) ) {
				continue;
			}
			$image_url = self::resolve_relative_url( $source_url, $ref );
			if ( '' === $image_url || ! self::is_allowed_source( $image_url ) ) {
				continue;
			}
			$response = wp_safe_remote_get(
				$image_url,
				array(
					'timeout'             => 15,
					'redirection'         => 0,
					'limit_response_size' => 5 * MB_IN_BYTES,
				)
			);
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}
			$data = (string) wp_remote_retrieve_body( $response );
			if ( '' === $data ) {
				continue;
			}
			$url = MarkdownImportPage::sideload_image( $name, $data );
			if ( '' !== $url ) {
				$map[ $name ] = $url;
			}
		}
		return $map;
	}

	/**
	 * Resolve a relative reference against a base URL, the way a browser resolves
	 * a link on a page: the reference is applied to the base's folder, and "." and
	 * ".." segments are collapsed. Returns '' if the base cannot be parsed.
	 *
	 * @param string $base_url The absolute URL the reference sits on.
	 * @param string $relative The relative reference (e.g. "../assets/x.svg").
	 * @return string The resolved absolute URL, or ''.
	 */
	private static function resolve_relative_url( string $base_url, string $relative ): string {
		$parts = wp_parse_url( $base_url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return '';
		}
		$relative = (string) preg_replace( '/[?#].*$/', '', $relative );
		$dir      = self::dirname_of( (string) $parts['path'] );
		$path     = self::normalize_path( $dir . '/' . ltrim( $relative, '/' ) );
		return $parts['scheme'] . '://' . $parts['host'] . $path;
	}

	/**
	 * Collapse "." and ".." segments in a path. A ".." that would climb above the
	 * root is dropped, so the result never escapes the root.
	 *
	 * @param string $path A path, possibly with . and .. segments.
	 * @return string The normalised path, starting with "/".
	 */
	private static function normalize_path( string $path ): string {
		$out = array();
		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				array_pop( $out );
				continue;
			}
			$out[] = $segment;
		}
		return '/' . implode( '/', $out );
	}

	/**
	 * Record the last sync result for display in the meta box.
	 *
	 * @param int    $post_id Post id.
	 * @param string $message Message.
	 * @return void
	 */
	private function set_status( int $post_id, string $message ): void {
		update_post_meta( $post_id, self::META_STATUS, $message );
	}

	/**
	 * Record a sync error: store the message and flag the page so the admin
	 * notice can list it.
	 *
	 * @param int    $post_id Post id.
	 * @param string $message Error message.
	 * @return void
	 */
	private function set_sync_error( int $post_id, string $message ): void {
		$this->set_status( $post_id, $message );
		update_post_meta( $post_id, self::META_ERROR, '1' );
	}

	/**
	 * Turn Mermaid code fences into mermaid.js containers.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function mermaid_to_html( string $html ): string {
		return (string) preg_replace(
			'#<pre><code class="language-mermaid">(.*?)</code></pre>#s',
			'<pre class="mermaid">$1</pre>',
			$html
		);
	}

	/**
	 * Load mermaid.js on the frontend for a GitHub-synced handbook page that
	 * actually contains a Mermaid diagram.
	 *
	 * @return void
	 */
	public function enqueue_frontend(): void {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( self::SOURCE_GITHUB !== get_post_meta( $post->ID, self::META_SOURCE, true ) ) {
			return;
		}
		if ( false === strpos( (string) $post->post_content, 'class="mermaid"' ) ) {
			return;
		}
		wp_enqueue_script( 'living-handbook-mermaid-view' );
	}

	/**
	 * Add a source column to the handbook list table.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$columns['lh_source'] = __( 'Source', 'living-handbook' );
		return $columns;
	}

	/**
	 * Render the source column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'lh_source' !== $column ) {
			return;
		}
		$is_github = self::SOURCE_GITHUB === get_post_meta( $post_id, self::META_SOURCE, true );
		echo esc_html( $is_github ? __( 'GitHub', 'living-handbook' ) : __( 'WordPress', 'living-handbook' ) );

		// Flag a failed sync right in the column, not only in the admin notice.
		if ( $is_github && '1' === (string) get_post_meta( $post_id, self::META_ERROR, true ) ) {
			echo ' <span class="living-handbook-sync-failed" style="color:#b32d2e;font-weight:600;">' . esc_html__( '(sync failed)', 'living-handbook' ) . '</span>';
		}
	}

	/**
	 * Add a "Source" filter dropdown above the handbook list (all, GitHub,
	 * WordPress), matching the Source column.
	 *
	 * @param string $post_type The post type of the list being shown.
	 * @return void
	 */
	public function source_filter_dropdown( string $post_type ): void {
		if ( Handbook::POST_TYPE !== $post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['lh_source'] ) ? sanitize_key( wp_unslash( (string) $_GET['lh_source'] ) ) : '';
		$options = array(
			''                     => __( 'All sources', 'living-handbook' ),
			self::SOURCE_GITHUB    => __( 'GitHub', 'living-handbook' ),
			self::SOURCE_WORDPRESS => __( 'WordPress', 'living-handbook' ),
		);
		echo '<select name="lh_source">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Translate the source filter into a meta query on the handbook list.
	 * "WordPress" matches every page that is not GitHub, including pages that never
	 * stored a source row (the registered default is WordPress but writes no row),
	 * so the filter never drops manually created pages. Merges with any existing
	 * meta_query (from another list filter) under AND instead of replacing it.
	 *
	 * @param WP_Query $query The current query.
	 * @return void
	 */
	public function filter_by_source( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Handbook::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source = isset( $_GET['lh_source'] ) ? sanitize_key( wp_unslash( (string) $_GET['lh_source'] ) ) : '';
		if ( self::SOURCE_GITHUB !== $source && self::SOURCE_WORDPRESS !== $source ) {
			return;
		}

		if ( self::SOURCE_GITHUB === $source ) {
			$clause = array(
				'key'   => self::META_SOURCE,
				'value' => self::SOURCE_GITHUB,
			);
		} else {
			$clause = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_SOURCE,
					'value'   => self::SOURCE_GITHUB,
					'compare' => '!=',
				),
				array(
					'key'     => self::META_SOURCE,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$existing = $query->get( 'meta_query' );
		if ( empty( $existing ) || ! is_array( $existing ) ) {
			$query->set( 'meta_query', array( $clause ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			return;
		}
		$query->set( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query',
			array(
				'relation' => 'AND',
				$existing,
				$clause,
			)
		);
	}
}
