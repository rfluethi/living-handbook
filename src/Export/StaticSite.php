<?php
/**
 * Export a handbook as a self-contained website in a ZIP.
 *
 * The bundle export next door moves a handbook to another WordPress. This one
 * is for the reader who has no WordPress at all: a folder of HTML files that
 * opens by double-clicking index.html, with the pages, the images, a page tree
 * and a search that works with no server behind it.
 *
 * Two decisions shape the whole class.
 *
 * **The pages are rendered here, not fetched over HTTP.** The obvious route,
 * asking the site for each page with wp_remote_get(), arrives as a logged-out
 * visitor: against a fail-closed handbook that exports thirty pages of "no
 * access" and reports success. So the render happens in process, and what may
 * be exported is decided per page against the person who pressed the button.
 *
 * **It runs in passes with a time budget.** A handbook of two thousand pages
 * cannot be rendered in one request, and set_time_limit( 0 ) only moves the
 * failure to the web server. A pass renders what it can, saves its place, and
 * the browser asks for the next one, the same way the folder import works.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Export;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Frontend\Headings;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The static website export: planning, rendering, packing and delivery.
 */
final class StaticSite {

	/**
	 * How many seconds one pass may spend rendering.
	 */
	private const BUDGET = 15;

	/**
	 * Transient prefix for a running export.
	 */
	private const JOB_PREFIX = 'living_handbook_static_';

	/**
	 * How long an unfinished export is kept.
	 */
	private const JOB_TTL = HOUR_IN_SECONDS;

	/**
	 * The admin-post action that hands the finished file to the browser.
	 */
	public const ACTION = 'living_handbook_static_download';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Who may export. The same rule as the bundle export: a static copy carries
	 * every page of a handbook out of the site's access rules, so it is not a
	 * thing an author of their own pages gets to do.
	 *
	 * @return bool
	 */
	public static function can_export(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * The REST route the screen drives the export with.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'living-handbook/v1',
			'/static-export',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_callback' ),
				'permission_callback' => array( __CLASS__, 'can_export' ),
			)
		);
	}

	/**
	 * Load the export screen's script on the export page only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'living-handbook-export' ) ) {
			return;
		}

		wp_register_script(
			'living-handbook-static-export',
			LIVING_HANDBOOK_URL . 'assets/js/static-export.js',
			array( 'wp-api-fetch', 'wp-dom-ready', 'wp-i18n' ),
			LIVING_HANDBOOK_VERSION,
			true
		);
		wp_enqueue_script( 'living-handbook-static-export' );
		wp_localize_script(
			'living-handbook-static-export',
			'lhStaticExport',
			array(
				'path' => '/living-handbook/v1/static-export',
			)
		);
		wp_set_script_translations( 'living-handbook-static-export', 'living-handbook', LIVING_HANDBOOK_DIR . 'languages' );
	}

	/**
	 * One pass of an export: start a new one, or continue the one named.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_callback( WP_REST_Request $request ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'living_handbook_no_zip',
				__( 'This server has no ZIP support (the PHP zip extension), so an export cannot be packed.', 'living-handbook' ),
				array( 'status' => 500 )
			);
		}

		$job = self::clean_job_id( (string) $request->get_param( 'job' ) );
		if ( '' !== $job ) {
			$state = self::load_job( $job );
			if ( null === $state ) {
				return new WP_Error(
					'living_handbook_export_lost',
					__( 'This export has expired or belongs to somebody else. Start it again.', 'living-handbook' ),
					array( 'status' => 404 )
				);
			}
		} else {
			$term_id = absint( $request->get_param( 'handbook' ) );
			$root_id = absint( $request->get_param( 'area' ) );
			$term    = get_term( $term_id, Handbooks::TAXONOMY );

			if ( ! $term instanceof WP_Term ) {
				return new WP_Error(
					'living_handbook_no_handbook',
					__( 'Choose a handbook to export.', 'living-handbook' ),
					array( 'status' => 400 )
				);
			}
			if ( ! AccessController::can_view_term( $term_id, get_current_user_id() ) ) {
				return new WP_Error(
					'living_handbook_no_access',
					__( 'You cannot read this handbook, so you cannot export it either.', 'living-handbook' ),
					array( 'status' => 403 )
				);
			}

			$state = self::plan( $term, $root_id, (string) $request->get_param( 'theme' ) );
			if ( array() === $state['queue'] ) {
				return new WP_Error(
					'living_handbook_export_empty',
					__( 'There is nothing to export: this handbook has no pages you can read.', 'living-handbook' ),
					array( 'status' => 400 )
				);
			}
		}

		return new WP_REST_Response( self::run( $state ) );
	}

	/**
	 * Work out what belongs in the export, and in which order.
	 *
	 * @param WP_Term $term    The handbook.
	 * @param int     $root_id Top-level page to export on its own, 0 for all.
	 * @param string  $theme   The look the export gets.
	 * @return array<string, mixed>
	 */
	private static function plan( WP_Term $term, int $root_id, string $theme ): array {
		$user_id = get_current_user_id();

		$query = new WP_Query(
			AccessController::internal(
				array(
					'post_type'      => Handbook::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'no_found_rows'  => true,
					// include_children off, like every handbook query: a handbook's
					// pages are its own, not those of the handbooks below it.
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy'         => Handbooks::TAXONOMY,
							'field'            => 'term_id',
							'terms'            => (int) $term->term_id,
							'include_children' => false,
						),
					),
				)
			)
		);

		// The query is marked internal so the access layer does not narrow it to
		// the current visitor's handbooks, and then every page is checked against
		// the person who pressed the button. That is the whole point of rendering
		// in process: the export sees exactly what its author may see, no more.
		$index = array();
		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( ! AccessController::can_view_post( (int) $post->ID, $user_id ) ) {
				continue;
			}
			$posts[ (int) $post->ID ] = $post;
		}

		if ( $root_id > 0 ) {
			$posts = self::subtree( $root_id, $posts );
		}

		foreach ( $posts as $id => $post ) {
			$parent             = (int) $post->post_parent;
			$index[ (int) $id ] = array(
				'title'  => (string) get_the_title( $post ),
				'slug'   => '' !== (string) $post->post_name ? (string) $post->post_name : 'page-' . (int) $id,
				'parent' => isset( $posts[ $parent ] ) ? $parent : 0,
				'order'  => (int) $post->menu_order,
			);
		}

		$name = $term->name;
		if ( $root_id > 0 && isset( $index[ $root_id ] ) ) {
			$name = (string) $index[ $root_id ]['title'];
		}

		return array(
			'job'      => wp_generate_password( 12, false ),
			'user'     => $user_id,
			'term'     => (int) $term->term_id,
			'root'     => $root_id,
			'index'    => $index,
			'queue'    => array_keys( $index ),
			'position' => 0,
			'entries'  => array(),
			'media'    => array(),
			'mermaid'  => false,
			'template' => TemplateRender::markup(),
			'supports' => array(),
			'zip'      => '',
			'theme'    => SiteThemes::normalize( $theme ),
			'site'     => array(
				'title'       => $name,
				'description' => (string) $term->description,
				'language'    => str_replace( '_', '-', (string) get_locale() ),
				'generated'   => date_i18n( (string) get_option( 'date_format' ) ),
				'source'      => (string) home_url( '/' ),
				'slug'        => (string) $term->slug,
			),
		);
	}

	/**
	 * Reduce the pages to one top-level page and everything below it.
	 *
	 * @param int                 $root_id Top-level page id.
	 * @param array<int, WP_Post> $posts   All readable pages, keyed by id.
	 * @return array<int, WP_Post>
	 */
	private static function subtree( int $root_id, array $posts ): array {
		if ( ! isset( $posts[ $root_id ] ) ) {
			return array();
		}

		$keep    = array( $root_id => $posts[ $root_id ] );
		$parents = array( $root_id );

		// Breadth first, and bounded by the number of pages, so a parent loop in
		// the data cannot turn into a loop here.
		$guard = count( $posts ) + 1;
		while ( array() !== $parents && $guard-- > 0 ) {
			$next = array();
			foreach ( $posts as $id => $post ) {
				if ( in_array( (int) $post->post_parent, $parents, true ) && ! isset( $keep[ $id ] ) ) {
					$keep[ $id ] = $post;
					$next[]      = (int) $id;
				}
			}
			$parents = $next;
		}

		return $keep;
	}

	/**
	 * Render pages until the budget is up, then either finish or pause.
	 *
	 * @param array<string, mixed> $state Job state.
	 * @return array<string, mixed> The answer for the browser.
	 */
	private static function run( array $state ): array {
		$deadline = microtime( true ) + self::time_budget();
		$queue    = (array) $state['queue'];
		$total    = count( $queue );

		$zip = self::open_zip( $state );
		if ( ! $zip instanceof ZipArchive ) {
			return array(
				'error' => __( 'The export file could not be written on this server.', 'living-handbook' ),
			);
		}

		while ( (int) $state['position'] < $total ) {
			$post_id = (int) $queue[ (int) $state['position'] ];
			$post    = get_post( $post_id );

			if ( $post instanceof WP_Post ) {
				self::add_page( $zip, $post, $state );
			}

			++$state['position'];

			if ( microtime( true ) >= $deadline && (int) $state['position'] < $total ) {
				$zip->close();
				set_transient( self::JOB_PREFIX . (string) $state['job'], $state, self::JOB_TTL );

				return array(
					'job'       => (string) $state['job'],
					'done'      => false,
					'total'     => $total,
					'remaining' => $total - (int) $state['position'],
				);
			}
		}

		self::add_site_files( $zip, $state );
		$zip->close();

		set_transient( self::JOB_PREFIX . (string) $state['job'], $state, self::JOB_TTL );

		return array(
			'job'   => (string) $state['job'],
			'done'  => true,
			'total' => $total,
			// The nonce is added by hand rather than with wp_nonce_url(), which
			// runs its result through esc_html(): right for an href written into
			// HTML, wrong for a URL that travels as JSON and is assigned to
			// link.href in the browser. The "&" would arrive as "&#038;", the
			// browser would read everything from the "#" on as the fragment, and
			// the job and the nonce would never reach admin-post.php. The screen
			// then shows "the link you followed has expired", which is true and
			// says nothing about the actual cause.
			'url'   => add_query_arg(
				array(
					'action'   => self::ACTION,
					'job'      => (string) $state['job'],
					'_wpnonce' => wp_create_nonce( self::ACTION ),
				),
				admin_url( 'admin-post.php' )
			),
			'name'  => self::file_name( $state ),
			'size'  => size_format( self::zip_size( $state ) ),
		);
	}

	/**
	 * Open the export's ZIP, creating it on the first pass.
	 *
	 * The file is written to the system temp directory rather than the uploads
	 * folder, for the same reason the bundle export does it: everything under
	 * uploads is reachable over HTTP, and this file holds every page of a
	 * handbook that may well be internal.
	 *
	 * @param array<string, mixed> $state Job state, updated in place.
	 * @return ZipArchive|null
	 */
	private static function open_zip( array &$state ): ?ZipArchive {
		$zip  = new ZipArchive();
		$path = (string) $state['zip'];

		if ( '' === $path ) {
			$path = (string) tempnam( sys_get_temp_dir(), 'lh-site' );
			if ( '' === $path ) {
				return null;
			}
			$state['zip'] = $path;
			$flags        = ZipArchive::CREATE | ZipArchive::OVERWRITE;
		} else {
			$flags = ZipArchive::CREATE;
		}

		return true === $zip->open( $path, $flags ) ? $zip : null;
	}

	/**
	 * Render one page into the archive.
	 *
	 * @param ZipArchive           $zip   The open archive.
	 * @param WP_Post              $post  The page.
	 * @param array<string, mixed> $state Job state, updated in place.
	 * @return void
	 */
	private static function add_page( ZipArchive $zip, WP_Post $post, array &$state ): void {
		$index = (array) $state['index'];
		$path  = SiteRenderer::path_for( (int) $post->ID, $index );

		// The template first: it puts the blocks where this site put them. The
		// hand-built page stays as the fallback for an installation whose
		// templates cannot be read, so an export is never empty for want of one.
		$content       = TemplateRender::render( $post, (string) $state['template'] );
		$from_template = '' !== trim( $content );
		if ( ! $from_template ) {
			$content = self::render_content( $post );
		}

		$content = self::rewrite( $zip, $content, $state, $path );

		$html = $from_template
			? SiteRenderer::page_from_template( $post, $content, $index, (array) $state['site'] )
			: SiteRenderer::page( $post, $content, $index, (array) $state['site'] );

		// What the style engine gathered while rendering: the flex rules behind a
		// columns block and the like, which live in no stylesheet and would
		// otherwise stack every column on top of the next.
		$supports = (array) $state['supports'];
		$rules    = TemplateRender::block_support_css();
		if ( '' !== $rules ) {
			$supports[ md5( $rules ) ] = $rules;
		}
		$state['supports'] = $supports;

		/**
		 * Filter the HTML of one page of a static export.
		 *
		 * @param string  $html The complete document.
		 * @param WP_Post $post The page it was built from.
		 * @param string  $path Its path inside the export.
		 */
		$html = (string) apply_filters( 'living_handbook_static_export_page', $html, $post, $path );

		if ( SiteRenderer::has_diagram( $content ) && empty( $state['mermaid'] ) ) {
			// 3.5 MB, so it travels only with an export that has something to
			// draw, and only once however many diagrams follow.
			self::add_plugin_file( $zip, 'assets/js/mermaid.min.js', 'assets/mermaid.js' );
			self::add_plugin_file( $zip, 'assets/js/mermaid-view.js', 'assets/mermaid-view.js' );
			$state['mermaid'] = true;
		}

		$zip->addFromString( $path, $html );

		$entries          = (array) $state['entries'];
		$entries[]        = SiteRenderer::index_entry( (string) get_the_title( $post ), $path, $content );
		$state['entries'] = $entries;
	}

	/**
	 * The rendered content of a page, as the front end would show it.
	 *
	 * The_content is applied with the page set up as the current post, so the
	 * filters that build the reading experience see what they expect: the heading
	 * anchors this export's table of contents is built from are added there.
	 *
	 * @param WP_Post $post The page.
	 * @return string
	 */
	private static function render_content( WP_Post $post ): string {
		global $wp_query;

		$previous_post  = $GLOBALS['post'] ?? null;
		$previous_query = $wp_query;

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below; the content filters read it.
		setup_postdata( $post );

		$content = (string) apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook, applied on purpose.

		// The anchors are added explicitly, because the filter that normally does
		// it asks whether it is inside the loop of a handbook page, and this is a
		// back-end request. Without them the table of contents would list headings
		// it cannot point at.

		$content = Headings::anchor( $content );

		wp_reset_postdata();
		$wp_query        = $previous_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring what was there.
		$GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring what was there.

		return $content;
	}

	/**
	 * Point every link and image at something that exists inside the ZIP.
	 *
	 * Three kinds of address are treated differently. A link to another exported
	 * page becomes a relative path to its file. An upload becomes a copy under
	 * assets/media, so the export works offline. Everything else is left exactly
	 * as it is: an outside link still belongs on the internet, and a link to a
	 * page that was not exported stays absolute rather than pointing at a file
	 * that is not there.
	 *
	 * @param ZipArchive           $zip     The open archive.
	 * @param string               $content Rendered content.
	 * @param array<string, mixed> $state   Job state, updated in place.
	 * @param string               $path    Path of the page being written.
	 * @return string
	 */
	private static function rewrite( ZipArchive $zip, string $content, array &$state, string $path ): string {
		$index   = (array) $state['index'];
		$uploads = wp_get_upload_dir();
		$baseurl = (string) $uploads['baseurl'];
		$basedir = (string) $uploads['basedir'];
		$prefix  = SiteRenderer::root_prefix( $path );

		$links = array();
		foreach ( array_keys( $index ) as $id ) {
			$permalink = get_permalink( (int) $id );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				$links[ untrailingslashit( $permalink ) ] = SiteRenderer::relative( $path, SiteRenderer::path_for( (int) $id, $index ) );
			}
		}

		// The handbook's own address, which the navigation links to as its top
		// entry. In the export that place is the start page, so it goes there
		// rather than back to a site the reader may not be able to open.
		$term_link = get_term_link( (int) $state['term'], Handbooks::TAXONOMY );
		if ( is_string( $term_link ) && '' !== $term_link ) {
			$links[ untrailingslashit( $term_link ) ] = $prefix . 'index.html';
		}

		$media = (array) $state['media'];

		$content = (string) preg_replace_callback(
			'#(href|src)="([^"]+)"#i',
			static function ( array $found ) use ( $links, $baseurl, $basedir, $prefix, $zip, &$media ): string {
				$url   = $found[2];
				$parts = explode( '#', $url, 2 );
				$bare  = untrailingslashit( $parts[0] );
				$hash  = isset( $parts[1] ) ? '#' . $parts[1] : '';

				if ( isset( $links[ $bare ] ) ) {
					return $found[1] . '="' . esc_attr( $links[ $bare ] . $hash ) . '"';
				}

				if ( '' !== $baseurl && str_starts_with( $url, $baseurl ) ) {
					$relative = ltrim( substr( $url, strlen( $baseurl ) ), '/' );
					$file     = $basedir . '/' . $relative;
					if ( ! isset( $media[ $relative ] ) && is_readable( $file ) ) {
						$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file, not a request.
						if ( is_string( $data ) ) {
							$zip->addFromString( 'assets/media/' . $relative, $data );
							$media[ $relative ] = true;
						}
					}
					if ( isset( $media[ $relative ] ) ) {
						return $found[1] . '="' . esc_attr( $prefix . 'assets/media/' . $relative ) . '"';
					}
				}

				return $found[0];
			},
			$content
		);

		// srcset carries the same uploads in a comma-separated list, and a browser
		// reading the export from a folder would go looking for them on the site
		// it came from. Every size referenced there is copied too, so a screen
		// that wants the sharper one gets it out of the ZIP.
		$content = (string) preg_replace_callback(
			'#srcset="([^"]+)"#i',
			static function ( array $found ) use ( $baseurl, $basedir, $prefix, $zip, &$media ): string {
				$parts = array_map( 'trim', explode( ',', $found[1] ) );
				$out   = array();
				foreach ( $parts as $part ) {
					$bits = preg_split( '/\s+/', $part );
					if ( ! is_array( $bits ) || array() === $bits ) {
						continue;
					}
					$url = (string) array_shift( $bits );
					if ( '' !== $baseurl && str_starts_with( $url, $baseurl ) ) {
						$relative = ltrim( substr( $url, strlen( $baseurl ) ), '/' );
						$file     = $basedir . '/' . $relative;
						if ( ! isset( $media[ $relative ] ) && is_readable( $file ) ) {
							$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file, not a request.
							if ( is_string( $data ) ) {
								$zip->addFromString( 'assets/media/' . $relative, $data );
								$media[ $relative ] = true;
							}
						}
						if ( isset( $media[ $relative ] ) ) {
							$url = $prefix . 'assets/media/' . $relative;
						}
					}
					$out[] = trim( $url . ' ' . implode( ' ', $bits ) );
				}

				return 'srcset="' . esc_attr( implode( ', ', $out ) ) . '"';
			},
			$content
		);

		$state['media'] = $media;

		return $content;
	}

	/**
	 * Everything the pages share: the start page, the stylesheet, the script,
	 * the search index and the note in the root of the ZIP.
	 *
	 * @param ZipArchive           $zip   The open archive.
	 * @param array<string, mixed> $state Job state.
	 * @return void
	 */
	private static function add_site_files( ZipArchive $zip, array $state ): void {
		$index = (array) $state['index'];
		$site  = (array) $state['site'];

		$zip->addFromString( 'index.html', SiteRenderer::start_page( $index, $site ) );

		$style = SiteRenderer::stylesheet( (string) $state['theme'], implode( "\n", (array) $state['supports'] ) );
		$zip->addFromString( 'assets/style.css', self::localise_css( $zip, $style ) );
		$zip->addFromString( 'assets/site.js', SiteRenderer::script() );
		// The plugin's own frontend script, for one thing it does that an export
		// wants: images and diagrams that enlarge on a click, with the focus
		// handling that goes with it. Its search, filter and feedback parts look
		// for elements this export does not have and quietly do nothing.
		self::add_plugin_file( $zip, 'assets/frontend.js', 'assets/frontend.js' );
		$zip->addFromString( 'assets/search-index.js', SiteRenderer::search_index( (array) $state['entries'] ) );
		$zip->addFromString( 'README.txt', SiteRenderer::readme( $site ) );
	}

	/**
	 * The size of the finished archive, in bytes.
	 *
	 * @param array<string, mixed> $state Job state.
	 * @return int Zero when the file is gone, which the caller shows as "0 B".
	 */
	private static function zip_size( array $state ): int {
		$path = (string) $state['zip'];
		if ( '' === $path || ! is_readable( $path ) ) {
			return 0;
		}
		$size = filesize( $path );

		return is_int( $size ) ? $size : 0;
	}

	/**
	 * Copy everything a stylesheet points at into the archive, and point at the
	 * copies instead.
	 *
	 * Fonts above all: a theme's typography is half its look, and a `@font-face`
	 * whose file stayed on the server turns into a fallback font on the reader's
	 * machine, silently. Background images from theme.json travel the same way.
	 * Anything that does not resolve to a readable file under this installation
	 * is left alone, so a font from a CDN keeps pointing at the CDN.
	 *
	 * @param ZipArchive $zip The open archive.
	 * @param string     $css The stylesheet.
	 * @return string
	 */
	private static function localise_css( ZipArchive $zip, string $css ): string {
		$copied = array();

		return (string) preg_replace_callback(
			'#url\(\s*([\'"]?)(https?://[^\'")]+)\1\s*\)#i',
			static function ( array $found ) use ( $zip, &$copied ): string {
				$url  = (string) $found[2];
				$file = self::local_path_for( $url );
				if ( '' === $file ) {
					return $found[0];
				}

				$name = 'assets/site/' . ltrim( self::asset_name( $url ), '/' );
				if ( ! isset( $copied[ $name ] ) ) {
					$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file, not a request.
					if ( ! is_string( $data ) ) {
						return $found[0];
					}
					$zip->addFromString( $name, $data );
					$copied[ $name ] = true;
				}

				// The stylesheet sits in assets/, so the path is relative to that.
				return 'url("' . str_replace( 'assets/', '', $name ) . '")';
			},
			$css
		);
	}

	/**
	 * The file on disk an absolute URL of this installation points at.
	 *
	 * @param string $url Absolute URL.
	 * @return string Empty when it is not one of ours, or not readable.
	 */
	private static function local_path_for( string $url ): string {
		$bare = (string) strtok( $url, '?#' );

		$roots = array(
			content_url()   => WP_CONTENT_DIR,
			includes_url()  => ABSPATH . WPINC,
			site_url( '/' ) => ABSPATH,
		);

		foreach ( $roots as $base_url => $base_dir ) {
			$base_url = untrailingslashit( (string) $base_url );
			if ( '' === $base_url || ! str_starts_with( $bare, $base_url ) ) {
				continue;
			}
			$path = untrailingslashit( (string) $base_dir ) . substr( $bare, strlen( $base_url ) );
			$path = str_replace( '..', '', $path );
			if ( is_readable( $path ) && ! is_dir( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * A path inside the export for an asset URL, keeping the folders it came in.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private static function asset_name( string $url ): string {
		$path  = (string) wp_parse_url( (string) strtok( $url, '?#' ), PHP_URL_PATH );
		$parts = array_filter( explode( '/', $path ) );
		$safe  = array();
		foreach ( $parts as $part ) {
			$safe[] = sanitize_file_name( $part );
		}

		return implode( '/', $safe );
	}

	/**
	 * Copy a file shipped with the plugin into the archive.
	 *
	 * @param ZipArchive $zip    The open archive.
	 * @param string     $source Path inside the plugin.
	 * @param string     $target Path inside the archive.
	 * @return bool Whether it was there to copy.
	 */
	private static function add_plugin_file( ZipArchive $zip, string $source, string $target ): bool {
		$path = LIVING_HANDBOOK_DIR . $source;
		if ( ! is_readable( $path ) ) {
			return false;
		}
		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file shipped with this plugin, not a request.
		if ( ! is_string( $data ) ) {
			return false;
		}

		return $zip->addFromString( $target, $data );
	}

	/**
	 * The name the browser saves the file under.
	 *
	 * @param array<string, mixed> $state Job state.
	 * @return string
	 */
	private static function file_name( array $state ): string {
		$site = (array) $state['site'];
		$slug = sanitize_title( (string) $site['title'] );
		if ( '' === $slug ) {
			$slug = 'handbook';
		}

		return 'living-handbook-' . $slug . '-site-' . gmdate( 'Y-m-d' ) . '.zip';
	}

	/**
	 * Hand the finished file to the browser and remove it.
	 *
	 * @return void
	 */
	public function download(): void {
		if ( ! self::can_export() ) {
			wp_die( esc_html__( 'You are not allowed to export handbooks.', 'living-handbook' ), 403 );
		}
		check_admin_referer( self::ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin_referer above.
		$raw   = isset( $_GET['job'] ) ? wp_unslash( $_GET['job'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- clean_job_id() below is the sanitisation, and it keeps case on purpose.
		$state = self::load_job( self::clean_job_id( is_string( $raw ) ? $raw : '' ) );
		if ( null === $state || '' === (string) $state['zip'] || ! is_readable( (string) $state['zip'] ) ) {
			// The likeliest reason by far is that it was already fetched: the file
			// is deleted as it is handed over. Saying so beats "no longer
			// available", which reads like something broke.
			wp_die( esc_html__( 'This file has already been downloaded, or the export has expired. Build the website again if you need another copy.', 'living-handbook' ), 404 );
		}

		$path = (string) $state['zip'];

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . self::file_name( $state ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a generated file to the browser.
		readfile( $path );

		wp_delete_file( $path );
		delete_transient( self::JOB_PREFIX . (string) $state['job'] );
		exit;
	}

	/**
	 * Reduce a job id from a request to the characters one can hold.
	 *
	 * Case is kept, and that is not cosmetic. The id goes into a transient name,
	 * and lower-casing it here would build a key that the database still finds,
	 * because its collation ignores case, while the object cache does not: one
	 * pass would read a stale copy of the job from the cache and the export would
	 * repeat a page forever. sanitize_key() lower-cases, so it is not used.
	 *
	 * @param string $job Raw job id.
	 * @return string
	 */
	private static function clean_job_id( string $job ): string {
		$clean = preg_replace( '/[^A-Za-z0-9]/', '', $job );

		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * Load a job, and only for the person it belongs to.
	 *
	 * @param string $job Job id.
	 * @return array<string, mixed>|null
	 */
	private static function load_job( string $job ): ?array {
		if ( '' === $job ) {
			return null;
		}

		$state = get_transient( self::JOB_PREFIX . $job );
		if ( ! is_array( $state ) || ! isset( $state['user'], $state['queue'] ) ) {
			return null;
		}
		if ( get_current_user_id() !== (int) $state['user'] ) {
			return null;
		}

		return $state;
	}

	/**
	 * How long one pass may take.
	 *
	 * @return float
	 */
	private static function time_budget(): float {
		$budget = (float) self::BUDGET;

		$limit = (float) ini_get( 'max_execution_time' );
		if ( $limit > 0.0 ) {
			$budget = min( $budget, max( 5.0, $limit * 0.6 ) );
		}

		/**
		 * Filter how long one pass of a static export may take, in seconds.
		 *
		 * @param float $budget Seconds.
		 */
		return (float) apply_filters( 'living_handbook_static_export_time_budget', $budget );
	}

	/**
	 * The second half of the export screen: the form for a static website.
	 *
	 * @param array<int, WP_Term> $handbooks The handbooks to offer.
	 * @return void
	 */
	public static function render_form( array $handbooks ): void {
		if ( ! self::can_export() || array() === $handbooks ) {
			return;
		}
		?>
		<hr style="margin:2.5rem 0">
		<h2><?php esc_html_e( 'Export as a website', 'living-handbook' ); ?></h2>
		<p class="description" style="max-width:820px"><?php esc_html_e( 'A ZIP of plain HTML files: the pages, their images, a page list and a search. It opens by double-clicking index.html, with no server and no internet connection. For readers who have no access to this site at all.', 'living-handbook' ); ?></p>
		<div class="notice notice-warning inline" style="max-width:820px"><p>
			<?php esc_html_e( 'A static copy has no access rules. It contains every page you can read in the handbook you choose, and whoever holds the file can read all of it. It also stops being current the moment it is made.', 'living-handbook' ); ?>
		</p></div>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="lh-site-handbook"><?php esc_html_e( 'Handbook', 'living-handbook' ); ?></label></th>
				<td>
					<select id="lh-site-handbook">
						<option value="0"><?php esc_html_e( '— select a handbook —', 'living-handbook' ); ?></option>
						<?php foreach ( $handbooks as $term ) : ?>
							<?php if ( $term instanceof WP_Term ) : ?>
								<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lh-site-area"><?php esc_html_e( 'What to export', 'living-handbook' ); ?></label></th>
				<td>
					<select id="lh-site-area" data-whole="<?php esc_attr_e( '— the whole handbook —', 'living-handbook' ); ?>">
						<option value="0"><?php esc_html_e( '— the whole handbook —', 'living-handbook' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lh-site-theme"><?php esc_html_e( 'Look', 'living-handbook' ); ?></label></th>
				<td>
					<select id="lh-site-theme">
						<?php foreach ( SiteThemes::all() as $key => $theme ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $theme['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( '"Like this site" takes your theme along: its palette, its fonts, its spacing, plus what you set under Appearance. The other three leave all of that behind and bring their own, which is often the better choice for a copy that goes outside the team.', 'living-handbook' ); ?></p>
				</td>
			</tr>
		</table>
		<p>
			<button type="button" class="button button-primary" id="lh-site-start"><?php esc_html_e( 'Build the website', 'living-handbook' ); ?></button>
			<span id="lh-site-status" role="status" style="margin-inline-start:0.75rem"></span>
		</p>
		<?php
	}
}
