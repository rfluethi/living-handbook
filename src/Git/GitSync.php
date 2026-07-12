<?php
/**
 * GitHub sync: pages whose source is GitHub are pulled from a Markdown URL.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Git;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Import\MarkdownConverter;
use LivingHandbook\Import\Postprocessor;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use WP_Post;

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
 * The pulled HTML comes from an external repository, so it is run through
 * wp_kses with a fixed allowlist before it is stored, which strips scripts,
 * event handlers and unsafe URLs while keeping the Mermaid and details markup.
 */
final class GitSync {

	public const META_SOURCE = 'hb_quelle';
	public const META_URL    = 'hb_markdown_source';

	public const SOURCE_WORDPRESS = 'wordpress';
	public const SOURCE_GITHUB    = 'github';

	private const META_STATUS = '_lh_sync_status';

	private const CRON_HOOK = 'living_handbook_git_sync';

	private const OPTION_SCHEDULE = 'living_handbook_sync_schedule';

	private const SETTINGS_SLUG = 'living-handbook-sync';

	private const SCHEDULES = array( 'off', 'hourly', 'twicedaily', 'daily', 'weekly' );

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
		add_action( 'admin_post_living_handbook_git_sync_now', array( $this, 'sync_now' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::CRON_HOOK, array( $this, 'run_sync' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_filter( 'manage_' . Handbook::POST_TYPE . '_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_' . Handbook::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Add a weekly cron schedule (hourly, twicedaily, daily are built in).
	 *
	 * @param array<string, array{interval:int, display:string}> $schedules Schedules.
	 * @return array<string, array{interval:int, display:string}>
	 */
	public function add_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once weekly', 'living-handbook' ),
			);
		}
		return $schedules;
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
	 * Clear the scheduled sync.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
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
		$value = (string) get_option( self::OPTION_SCHEDULE, 'daily' );
		return in_array( $value, self::SCHEDULES, true ) ? $value : 'daily';
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
	 * Create a locked GitHub page from a source URL and pull it once.
	 *
	 * The slug is taken from the source file name so that internal .md links,
	 * which reference other pages by file name, resolve to these pages.
	 *
	 * @param string $url         Markdown source URL (raw or blob).
	 * @param int    $handbook_id Optional handbook term id.
	 * @param string $title       Optional fallback title (used until a heading is found).
	 * @return int Post id, or 0 on failure.
	 */
	public function create_github_page( string $url, int $handbook_id = 0, string $title = '' ): int {
		$url = self::normalize_url( $url );
		if ( '' === $url ) {
			return 0;
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$slug = sanitize_title( pathinfo( is_string( $path ) ? $path : $url, PATHINFO_FILENAME ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => '' !== $title ? $title : __( 'GitHub page', 'living-handbook' ),
				'post_name'   => $slug,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}
		$post_id = (int) $post_id;

		update_post_meta( $post_id, self::META_SOURCE, self::SOURCE_GITHUB );
		update_post_meta( $post_id, self::META_URL, $url );
		if ( 0 < $handbook_id ) {
			wp_set_object_terms( $post_id, array( $handbook_id ), Handbooks::TAXONOMY );
		}

		$this->sync_page( $post_id );
		return $post_id;
	}

	/**
	 * Import every Markdown file in a GitHub folder (a tree URL) as a locked page.
	 *
	 * Uses the GitHub contents API. Only the given folder is read (no recursion
	 * into subfolders); README.md is included.
	 *
	 * @param string $tree_url    A github.com tree URL to a folder.
	 * @param int    $handbook_id Optional handbook term id.
	 * @return array<string, mixed> Either { pages: [...] } or { error: string }.
	 */
	public function import_folder( string $tree_url, int $handbook_id = 0 ): array {
		$parsed = self::parse_tree_url( $tree_url );
		if ( null === $parsed ) {
			return array( 'error' => 'Not a GitHub folder URL.' );
		}

		$api = 'https://api.github.com/repos/' . $parsed['owner'] . '/' . $parsed['repo']
			. '/contents/' . $parsed['path'] . '?ref=' . rawurlencode( $parsed['branch'] );

		$response = wp_remote_get(
			$api,
			array(
				'timeout' => 20,
				'headers' => array(
					'User-Agent' => 'LivingHandbook',
					'Accept'     => 'application/vnd.github+json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array( 'error' => 'GitHub API HTTP ' . $code );
		}
		$items = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $items ) ) {
			return array( 'error' => 'Unexpected GitHub API response.' );
		}

		$pages = array();
		$ids   = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type     = isset( $item['type'] ) ? (string) $item['type'] : '';
			$name     = isset( $item['name'] ) ? (string) $item['name'] : '';
			$download = isset( $item['download_url'] ) ? (string) $item['download_url'] : '';
			if ( 'file' !== $type || '' === $download ) {
				continue;
			}
			if ( 'md' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				continue;
			}
			$post_id = $this->create_github_page( $download, $handbook_id );
			if ( 0 < $post_id ) {
				$ids[]   = $post_id;
				$pages[] = array(
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
		}

		// Resolve parents and internal links once every page in the folder exists.
		Postprocessor::finalize( $ids );

		return array( 'pages' => $pages );
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
		<p><label><input type="radio" name="hb_quelle" value="wordpress" <?php checked( self::SOURCE_WORDPRESS, $source ); ?>> <?php esc_html_e( 'Maintained in WordPress', 'living-handbook' ); ?></label></p>
		<p><label><input type="radio" name="hb_quelle" value="github" <?php checked( self::SOURCE_GITHUB, $source ); ?>> <?php esc_html_e( 'Synced from GitHub', 'living-handbook' ); ?></label></p>
		<p>
			<label for="hb_markdown_source"><?php esc_html_e( 'Markdown source (raw or blob URL)', 'living-handbook' ); ?></label>
			<input type="url" id="hb_markdown_source" name="hb_markdown_source" value="<?php echo esc_attr( $url ); ?>" class="widefat" placeholder="https://github.com/... or https://raw.githubusercontent.com/...">
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

		$raw    = isset( $_POST['hb_quelle'] ) ? sanitize_text_field( wp_unslash( $_POST['hb_quelle'] ) ) : '';
		$source = self::SOURCE_GITHUB === $raw ? self::SOURCE_GITHUB : self::SOURCE_WORDPRESS;
		update_post_meta( $post_id, self::META_SOURCE, $source );

		$raw_url = isset( $_POST['hb_markdown_source'] ) ? wp_unslash( $_POST['hb_markdown_source'] ) : '';
		$url     = esc_url_raw( self::normalize_url( is_string( $raw_url ) ? $raw_url : '' ) );
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
	 * Cron handler: sync every GitHub-sourced page.
	 *
	 * @return void
	 */
	public function run_sync(): void {
		$posts = get_posts(
			array(
				'post_type'   => Handbook::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'meta_key'    => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => self::SOURCE_GITHUB, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$ids   = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$this->sync_page( (int) $post->ID );
				$ids[] = (int) $post->ID;
			}
		}
		Postprocessor::finalize( $ids );
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
			$this->set_status( $post_id, __( 'Error: CommonMark is not installed.', 'living-handbook' ) );
			return;
		}
		$url = (string) get_post_meta( $post_id, self::META_URL, true );
		if ( '' === $url ) {
			return;
		}
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			$this->set_status( $post_id, __( 'Error: ', 'living-handbook' ) . $response->get_error_message() );
			return;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->set_status( $post_id, __( 'Error: HTTP ', 'living-handbook' ) . $code );
			return;
		}
		$markdown = (string) wp_remote_retrieve_body( $response );

		$result = ( new MarkdownConverter() )->convert( $markdown );
		$html   = $this->mermaid_to_html( (string) $result['html'] );
		// The HTML is from an external repo; strip anything unsafe before storing.
		$html = wp_kses( $html, self::allowed_html() );

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
		self::$is_syncing = false;

		update_post_meta( $post_id, Metadata::UPDATED, current_time( 'Y-m-d' ) );
		$this->set_status( $post_id, __( 'OK ', 'living-handbook' ) . current_time( 'Y-m-d H:i' ) );
	}

	/**
	 * Allowed HTML for stored GitHub content: the post allowlist plus the Mermaid
	 * container and the details/summary disclosure markup.
	 *
	 * @return array<string, mixed>
	 */
	private static function allowed_html(): array {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed['details'] = array(
			'open'  => true,
			'class' => true,
			'id'    => true,
		);
		$allowed['summary'] = array(
			'class' => true,
			'id'    => true,
		);
		if ( ! isset( $allowed['pre'] ) || ! is_array( $allowed['pre'] ) ) {
			$allowed['pre'] = array();
		}
		$allowed['pre']['class'] = true;

		return $allowed;
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
	 * Load mermaid.js on the frontend for a GitHub-synced handbook page.
	 *
	 * @return void
	 */
	public function enqueue_frontend(): void {
		if ( ! is_singular( Handbook::POST_TYPE ) ) {
			return;
		}
		if ( self::SOURCE_GITHUB !== get_post_meta( get_queried_object_id(), self::META_SOURCE, true ) ) {
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
	}

	/**
	 * Register the sync settings page.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Handbook::POST_TYPE,
			__( 'GitHub sync settings', 'living-handbook' ),
			__( 'Sync settings', 'living-handbook' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render the sync settings page and handle its form.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['living_handbook_sync_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['living_handbook_sync_nonce'] ) ), 'living_handbook_sync' ) ) {
			$value = isset( $_POST['living_handbook_sync_schedule'] ) ? sanitize_text_field( wp_unslash( $_POST['living_handbook_sync_schedule'] ) ) : 'daily';
			if ( ! in_array( $value, self::SCHEDULES, true ) ) {
				$value = 'daily';
			}
			update_option( self::OPTION_SCHEDULE, $value );
			self::reschedule();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'living-handbook' ) . '</p></div>';
		}

		$current = self::current_schedule();
		$labels  = array(
			'off'        => __( 'Off (only on save and Sync now)', 'living-handbook' ),
			'hourly'     => __( 'Hourly', 'living-handbook' ),
			'twicedaily' => __( 'Twice daily', 'living-handbook' ),
			'daily'      => __( 'Daily', 'living-handbook' ),
			'weekly'     => __( 'Weekly', 'living-handbook' ),
		);
		$next    = wp_next_scheduled( self::CRON_HOOK );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'GitHub sync settings', 'living-handbook' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'living_handbook_sync', 'living_handbook_sync_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="living_handbook_sync_schedule"><?php esc_html_e( 'Automatic sync', 'living-handbook' ); ?></label></th>
						<td>
							<select id="living_handbook_sync_schedule" name="living_handbook_sync_schedule">
								<?php foreach ( $labels as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $current ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'How often WordPress pulls GitHub pages in the background. GitHub pages are always synced when saved and via Sync now, regardless of this setting. The background sync runs on WordPress cron, which fires on site visits.', 'living-handbook' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php if ( false !== $next ) : ?>
				<p class="description"><?php esc_html_e( 'Next scheduled sync:', 'living-handbook' ); ?> <?php echo esc_html( wp_date( 'Y-m-d H:i', $next ) ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No background sync scheduled.', 'living-handbook' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
