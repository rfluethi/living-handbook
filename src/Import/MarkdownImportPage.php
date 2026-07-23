<?php
/**
 * The import page: paste a draft, upload a ZIP, or link a GitHub page or folder.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_Term;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page and REST routes for importing Markdown into handbook pages. Pasted
 * drafts and ZIPs become editable block pages; a ZIP that carries a mkdocs.yml is
 * imported along its nav so the folder structure, titles, and order are kept. A
 * GitHub file URL creates one locked, synced page; a GitHub folder (tree) URL
 * creates one per Markdown file. Postprocessor applies the transport metadata and
 * resolves parents and internal links.
 *
 * A re-import of the same structured source refreshes the matching pages instead
 * of creating duplicates: a page carrying a source path (folder and MkDocs
 * imports) is matched by that path, and a file-based import into a chosen
 * handbook is matched by slug within that handbook. A one-off pasted draft
 * always creates a new page.
 *
 * The endpoints need edit_posts, which a Contributor has, so every write is
 * additionally checked against the concrete post: a re-import only refreshes a
 * page the current user may edit, and the finalize pass only touches handbook
 * pages the user may edit. This stops a Contributor from overwriting another
 * author's published page through a re-import match.
 *
 * The page offers each source (paste, ZIP, GitHub) its own button, so a pasted
 * draft is never silently ignored because a URL is still in the field. Pages
 * that fail to import are listed with their reason, not just counted out. The
 * ZIP is read in bounded steps (entry count, per-file and total size), so a
 * prepared archive cannot exhaust the server's memory.
 */
final class MarkdownImportPage {

	public const MENU_SLUG = 'living-handbook-import';

	private const IMAGE_EXTENSIONS = array( 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg' );

	// Attachment meta that marks an image the plugin imported, so a re-import
	// reuses only its own uploads and re-uploads when the source image changed.
	private const META_IMPORT_FILE = '_lh_import_file';
	private const META_IMPORT_HASH = '_lh_import_hash';

	/**
	 * ZIP import limits, checked before the archive is read into memory.
	 */
	private const ZIP_MAX_ENTRIES     = 2000;
	private const ZIP_MAX_FILE_BYTES  = 5242880;  // 5 MB uncompressed per file.
	private const ZIP_MAX_TOTAL_BYTES = 104857600; // 100 MB uncompressed total.

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Add the submenu under the handbook menu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		$hook = add_submenu_page(
			'edit.php?post_type=' . Handbook::POST_TYPE,
			__( 'Markdown import', 'living-handbook' ),
			__( 'Import', 'living-handbook' ),
			'edit_posts',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
		if ( is_string( $hook ) && '' !== $hook ) {
			add_action( 'load-' . $hook, array( $this, 'register_help' ) );
		}
	}

	/**
	 * Add the explanation as a contextual Help tab (top right of the screen),
	 * the standard WordPress place for it, instead of an inline block.
	 *
	 * @return void
	 */
	public function register_help(): void {
		$screen = get_current_screen();
		if ( null === $screen ) {
			return;
		}
		$screen->add_help_tab(
			array(
				'id'      => 'living_handbook_import_help',
				'title'   => __( 'How the import works', 'living-handbook' ),
				'content' => '<p>' . esc_html__( 'Import Markdown into handbook pages. Paste a single draft or upload a ZIP of .md files: these become editable pages. If the ZIP contains a mkdocs.yml, its navigation defines the page structure, titles, and order (the ZIP must also hold the referenced files). Or give a GitHub URL: a file URL becomes one locked page, a folder (tree) URL imports every .md file in the folder as locked pages pulled from the repository. Front matter is dropped, transport metadata and the parent and order are applied, Mermaid diagrams and collapsible details become blocks, images in the ZIP are added to the media library, and internal .md links are pointed at the imported pages.', 'living-handbook' ) . '</p>',
			)
		);
	}

	/**
	 * A shared permission callback: the user may edit posts.
	 *
	 * @return bool
	 */
	public static function can_import(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register the REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'living-handbook/v1',
			'/convert',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'convert_callback' ),
				'permission_callback' => array( __CLASS__, 'can_import' ),
				'args'                => array(
					'markdown' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			'living-handbook/v1',
			'/import-zip',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_zip_callback' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'upload_files' );
				},
			)
		);
		register_rest_route(
			'living-handbook/v1',
			'/import-github',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_github_callback' ),
				'permission_callback' => array( __CLASS__, 'can_import' ),
			)
		);
		register_rest_route(
			'living-handbook/v1',
			'/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_callback' ),
				'permission_callback' => array( __CLASS__, 'can_import' ),
			)
		);
		register_rest_route(
			'living-handbook/v1',
			'/finalize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'finalize_callback' ),
				'permission_callback' => array( __CLASS__, 'can_import' ),
			)
		);
	}

	/**
	 * An operation-level import error as a WP_Error, which the REST server turns
	 * into a 4xx/5xx response and apiFetch turns into a rejected promise. Used for
	 * failures that abort a whole import (not per-page failures, which stay a data
	 * field so a batch can list them and continue).
	 *
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error
	 */
	private static function import_error( string $message, int $status = 400 ): WP_Error {
		return new WP_Error( 'living_handbook_import', $message, array( 'status' => $status ) );
	}

	/**
	 * The "CommonMark is missing" operation error, shared by the callbacks.
	 *
	 * @return WP_Error
	 */
	private static function no_commonmark(): WP_Error {
		return self::import_error( __( 'CommonMark is not installed. Run: composer require league/commonmark', 'living-handbook' ), 501 );
	}

	/**
	 * REST callback: convert one pasted Markdown draft.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function convert_callback( WP_REST_Request $request ) {
		if ( ! MarkdownConverter::available() ) {
			return self::no_commonmark();
		}
		return ( new MarkdownConverter() )->convert( (string) $request->get_param( 'markdown' ) );
	}

	/**
	 * REST callback: unpack a ZIP. With a mkdocs.yml it returns the nav-ordered
	 * page specs; otherwise it returns each Markdown file as a flat page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function import_zip_callback( WP_REST_Request $request ) {
		if ( ! MarkdownConverter::available() ) {
			return self::no_commonmark();
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			return self::import_error( __( 'ZipArchive is not available on the server.', 'living-handbook' ), 501 );
		}

		$params = $request->get_file_params();
		$tmp    = ( isset( $params['zip']['tmp_name'] ) && is_string( $params['zip']['tmp_name'] ) ) ? $params['zip']['tmp_name'] : '';
		if ( '' === $tmp ) {
			return self::import_error( __( 'No ZIP file received.', 'living-handbook' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp ) ) {
			return self::import_error( __( 'Could not open the ZIP file.', 'living-handbook' ) );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive::$numFiles is a PHP core property.
		$file_count = $zip->numFiles;
		if ( $file_count > self::ZIP_MAX_ENTRIES ) {
			$zip->close();
			return self::import_error( __( 'The ZIP has too many entries (maximum is 2000).', 'living-handbook' ) );
		}

		$markdown_files = array();
		$image_files    = array();
		$mkdocs_yaml    = '';
		$total_bytes    = 0;

		/**
		 * Maximum uncompressed total of an imported ZIP, in bytes. This is a safety
		 * limit against memory exhaustion; the real ceiling is the server's PHP
		 * config (upload_max_filesize, post_max_size, memory_limit). Raise it in
		 * code only if the server can handle it.
		 *
		 * @param int $bytes Default limit in bytes.
		 */
		$max_total = (int) apply_filters( 'living_handbook_zip_max_bytes', self::ZIP_MAX_TOTAL_BYTES );

		for ( $i = 0; $i < $file_count; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			$base = basename( $name );
			if ( '' === $base || 0 === strpos( $base, '.' ) || false !== strpos( $name, '__MACOSX' ) ) {
				continue;
			}

			// Guard against a prepared archive: skip a single oversized entry,
			// and stop before the uncompressed total exhausts memory.
			$stat = $zip->statIndex( $i );
			$size = is_array( $stat ) ? (int) $stat['size'] : 0;
			if ( $size > self::ZIP_MAX_FILE_BYTES ) {
				continue;
			}
			$total_bytes += $size;
			if ( $total_bytes > $max_total ) {
				$zip->close();
				/* translators: %d: maximum uncompressed size in whole megabytes. */
				return self::import_error( sprintf( __( 'The ZIP is too large: the uncompressed contents exceed %d MB.', 'living-handbook' ), (int) round( $max_total / MB_IN_BYTES ) ) );
			}

			$content = $zip->getFromIndex( $i );
			if ( false === $content ) {
				continue;
			}
			$ext = strtolower( pathinfo( $base, PATHINFO_EXTENSION ) );
			if ( 'md' === $ext ) {
				$markdown_files[ $name ] = $content;
			} elseif ( in_array( $ext, self::IMAGE_EXTENSIONS, true ) ) {
				$image_files[ $base ] = $content;
			} elseif ( '' === $mkdocs_yaml && ( 'mkdocs.yml' === strtolower( $base ) || 'mkdocs.yaml' === strtolower( $base ) ) ) {
				$mkdocs_yaml = $content;
			}
		}
		$zip->close();

		if ( empty( $markdown_files ) ) {
			return self::import_error( __( 'No .md files found in the ZIP.', 'living-handbook' ) );
		}

		$image_map = array();
		foreach ( $image_files as $file_name => $data ) {
			$url = self::sideload_image( $file_name, $data );
			if ( '' !== $url ) {
				$image_map[ $file_name ] = $url;
			}
		}

		if ( '' !== $mkdocs_yaml && MkDocsImport::available() ) {
			$specs = MkDocsImport::build_specs( $mkdocs_yaml, $markdown_files, $image_map, new MarkdownConverter() );
			if ( ! empty( $specs ) ) {
				return array(
					'mode'   => 'mkdocs',
					'pages'  => $specs,
					'images' => count( $image_map ),
				);
			}
		}

		$converter = new MarkdownConverter();
		$out       = array();
		foreach ( $markdown_files as $path => $markdown ) {
			$file_name = basename( $path );
			$result    = $converter->convert( $markdown, $image_map );
			$transport = (array) $result['transport'];
			$slug      = ( isset( $transport['slug'] ) && '' !== (string) $transport['slug'] )
				? sanitize_title( (string) $transport['slug'] )
				: sanitize_title( pathinfo( $file_name, PATHINFO_FILENAME ) );
			$out[]     = array(
				'name'      => $file_name,
				'slug'      => $slug,
				'title'     => '' !== $result['title'] ? $result['title'] : $slug,
				'html'      => $result['html'],
				'transport' => $result['transport'],
			);
		}

		return array(
			'files'  => $out,
			'images' => count( $image_map ),
		);
	}

	/**
	 * REST callback: create locked GitHub pages from a file URL or a folder URL.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error
	 */
	public function import_github_callback( WP_REST_Request $request ) {
		if ( ! MarkdownConverter::available() ) {
			return self::no_commonmark();
		}
		$url = trim( (string) $request->get_param( 'url' ) );
		if ( '' === $url ) {
			return self::import_error( __( 'No GitHub URL given.', 'living-handbook' ) );
		}
		$title       = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$handbook_id = absint( $request->get_param( 'handbook' ) );
		$git         = new GitSync();

		// The app handbook is curated, editor-locked content from a repository the
		// site owner chose, so it is published straight away; its visibility is
		// governed by the handbook it lands in. Any other GitHub import stays a
		// draft for review.
		$publish = AppHandbook::is_source( $url );

		if ( false !== strpos( $url, '/tree/' ) ) {
			return $git->import_folder( $url, $handbook_id, $publish );
		}

		$post_id = $git->create_github_page( $url, $handbook_id, $title, $publish );
		if ( 0 === $post_id ) {
			return self::import_error( __( 'Could not create the page. Check the URL.', 'living-handbook' ) );
		}
		Postprocessor::finalize( array( $post_id ) );
		return array(
			'pages' => array(
				array(
					'id'      => $post_id,
					'title'   => get_the_title( $post_id ),
					'editUrl' => add_query_arg(
						array(
							'post'   => $post_id,
							'action' => 'edit',
						),
						admin_url( 'post.php' )
					),
				),
			),
		);
	}

	/**
	 * REST callback: create one handbook draft from converted block content, or
	 * refresh the matching page on a re-import.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function create_callback( WP_REST_Request $request ): array {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		// The browser converted the HTML to blocks and posts it back here, so this
		// is the write path and it sanitizes for itself: a caller can skip /convert
		// and post arbitrary markup straight to this endpoint.
		$content       = HtmlSanitizer::clean_blocks( (string) $request->get_param( 'content' ) );
		$handbook_id   = absint( $request->get_param( 'handbook' ) );
		$transport     = (array) $request->get_param( 'transport' );
		$parent        = absint( $request->get_param( 'parent' ) );
		$source_path   = sanitize_text_field( (string) $request->get_param( 'sourcePath' ) );
		$explicit_slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		$slug          = $explicit_slug;

		// A "Slug:" line in the transport block is authoritative, so an area
		// start page keeps its intended URL.
		if ( isset( $transport['slug'] ) && '' !== (string) $transport['slug'] ) {
			$slug = sanitize_title( (string) $transport['slug'] );
		}

		if ( '' === $slug && '' !== $source_path ) {
			$path = (string) preg_replace( '/\.md$/i', '', $source_path );
			// Section start pages are index.md or README.md; take the folder
			// name, not the file name, so the slug is not "...-index".
			$path = (string) preg_replace( '#(^|/)(index|readme)$#i', '', $path );
			$slug = sanitize_title( str_replace( '/', '-', $path ) );
		}
		if ( '' === $title ) {
			$title = '' !== $slug ? $slug : __( 'Imported page', 'living-handbook' );
		}
		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}

		// Drop a leading heading or paragraph that only repeats the page title,
		// so the page does not show its title twice.
		$content = self::strip_leading_title_repeat( $content, $title );

		// Re-import protection: refresh the matching page instead of creating a
		// duplicate. A stored source path (folder and MkDocs imports) is matched
		// by path; a file-based import (an explicit slug) into a chosen handbook
		// is matched by slug within that handbook. A pasted draft, which carries
		// neither, always creates a new page.
		$allow_slug_match = '' !== $explicit_slug && $handbook_id > 0;
		$existing_id      = self::find_existing_page( $slug, $source_path, $handbook_id, $allow_slug_match );

		// Never let a re-import overwrite a page the current user may not edit.
		// The endpoints only require edit_posts, which a Contributor has, so a
		// match on someone else's published page must not become an update.
		// Fall back to creating a fresh page for this user instead.
		if ( $existing_id > 0 && ! current_user_can( 'edit_post', $existing_id ) ) {
			$existing_id = 0;
		}

		// wp_insert_post expects slashed data and unslashes it; slash the block
		// markup so escape sequences like \n and > survive.
		$data = array(
			'post_type'      => Handbook::POST_TYPE,
			'post_title'     => $title,
			'post_parent'    => $parent,
			'post_content'   => (string) wp_slash( $content ),
			'comment_status' => 'closed',
		);

		if ( $existing_id > 0 ) {
			// Keep the existing slug and publication status so URLs and
			// visibility stay stable across re-imports.
			$data['ID'] = $existing_id;
			$result     = wp_update_post( $data, true );
		} else {
			$data['post_status'] = 'draft';
			$data['post_name']   = $slug;
			$result              = wp_insert_post( $data, true );
		}
		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}
		$post_id = (int) $result;

		Postprocessor::apply_transport( $post_id, $transport, $handbook_id );

		if ( '' !== $source_path ) {
			update_post_meta( $post_id, Postprocessor::META_SOURCE_PATH, $source_path );
		}
		if ( null !== $request->get_param( 'order' ) ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'menu_order' => absint( $request->get_param( 'order' ) ),
				)
			);
		}

		return array(
			'id'         => $post_id,
			'sourcePath' => $source_path,
			'updated'    => $existing_id > 0,
			'editUrl'    => add_query_arg(
				array(
					'post'   => $post_id,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			),
		);
	}

	/**
	 * Find an existing handbook page to refresh on a re-import.
	 *
	 * Matches first by the stored source path (the precise key for folder and
	 * MkDocs imports), then, if allowed, by slug within the chosen handbook.
	 * Returns 0 when nothing matches, so the caller creates a new page.
	 *
	 * @param string $slug             Intended slug.
	 * @param string $source_path      Source path from the import, if any.
	 * @param int    $handbook_id      Target handbook term ID (0 for none).
	 * @param bool   $allow_slug_match Whether a slug match is permitted.
	 * @return int Existing post ID, or 0.
	 */
	private static function find_existing_page( string $slug, string $source_path, int $handbook_id, bool $allow_slug_match ): int {
		$base = array(
			'post_type'      => Handbook::POST_TYPE,
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		if ( $handbook_id > 0 ) {
			$base['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Handbooks::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $handbook_id,
				),
			);
		}

		if ( '' !== $source_path ) {
			$by_path = get_posts(
				array_merge(
					$base,
					array(
						'meta_key'   => Postprocessor::META_SOURCE_PATH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value' => $source_path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				)
			);
			if ( ! empty( $by_path ) ) {
				return (int) $by_path[0];
			}
		}

		if ( $allow_slug_match && '' !== $slug ) {
			// post_name__in, not name: a "name" query is treated as a singular
			// lookup, and the handbook restriction in $base would then not apply,
			// so a page of the same slug in another handbook would match.
			$by_slug = get_posts( array_merge( $base, array( 'post_name__in' => array( $slug ) ) ) );
			if ( ! empty( $by_slug ) ) {
				return (int) $by_slug[0];
			}
		}

		return 0;
	}

	/**
	 * Strip a leading heading or paragraph whose text only repeats the page
	 * title, so the imported page does not show its title twice (once as the
	 * post title, once as the first line of the content). A leading numbering
	 * such as "01 " is ignored when comparing, so a nav title "01 Was ist ein
	 * Projekt?" matches a body line "Was ist ein Projekt?". Only the very first
	 * block is considered, and only when its whole text equals the title, so a
	 * real first heading or lead paragraph is left untouched.
	 *
	 * @param string $content Block markup content.
	 * @param string $title   Page title.
	 * @return string
	 */
	private static function strip_leading_title_repeat( string $content, string $title ): string {
		if ( '' === trim( $title ) ) {
			return $content;
		}
		if ( 1 !== preg_match( '/^\s*<!--\s*wp:(heading|paragraph)\b[^>]*-->\s*<(h[1-6]|p)[^>]*>(.*?)<\/\2>\s*<!--\s*\/wp:\1\s*-->\s*/is', $content, $found ) ) {
			return $content;
		}
		$first = self::normalize_heading( wp_strip_all_tags( $found[3] ) );
		if ( '' === $first || self::normalize_heading( $title ) !== $first ) {
			return $content;
		}
		return (string) substr( $content, strlen( $found[0] ) );
	}

	/**
	 * Normalise a heading or title for comparison: lower-case, drop a leading
	 * numbering prefix, strip punctuation, and collapse whitespace.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function normalize_heading( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = (string) mb_strtolower( trim( $text ) );
		$text = (string) preg_replace( '/^\d+[.\)]?\s+/', '', $text );
		$text = (string) preg_replace( '/[^\p{L}\p{N} ]+/u', '', $text );
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}

	/**
	 * REST callback: resolve parents and convert internal .md links.
	 *
	 * The ids are restricted to handbook pages the current user may edit, so a
	 * caller cannot use this to rewrite arbitrary posts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	public function finalize_callback( WP_REST_Request $request ): array {
		$ids = array();
		foreach ( array_map( 'absint', (array) $request->get_param( 'ids' ) ) as $id ) {
			if ( $id > 0 && Handbook::POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
				$ids[] = $id;
			}
		}
		return array( 'converted' => Postprocessor::finalize( $ids ) );
	}

	/**
	 * Sideload one image into the media library. A re-import reuses only a
	 * previously imported attachment for the same file whose contents are
	 * unchanged, so a foreign upload that happens to share the file name is never
	 * picked up, and a changed source image is re-imported rather than reused.
	 *
	 * @param string $file_name Image file name.
	 * @param string $data      Binary data.
	 * @return string Media URL, or an empty string on failure.
	 */
	public static function sideload_image( string $file_name, string $data ): string {
		$hash     = md5( $data );
		$existing = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => 1,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => self::META_IMPORT_FILE,
						'value' => $file_name,
					),
					array(
						'key'   => self::META_IMPORT_HASH,
						'value' => $hash,
					),
				),
			)
		);
		$found    = isset( $existing[0] ) ? (int) $existing[0] : 0;
		if ( $found > 0 ) {
			$url = wp_get_attachment_url( $found );
			return is_string( $url ) ? $url : '';
		}

		// An SVG can carry script, so a hostile SVG from an imported ZIP would be
		// stored XSS in the media library. Sanitize it before storing; if it
		// cannot be sanitized (library missing or invalid SVG), skip it rather
		// than store an unchecked file.
		if ( 'svg' === strtolower( (string) pathinfo( $file_name, PATHINFO_EXTENSION ) ) ) {
			$data = self::sanitize_svg( $data );
			if ( '' === $data ) {
				return '';
			}
		}

		$upload = wp_upload_bits( sanitize_file_name( $file_name ), null, $data );
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}

		$type          = wp_check_filetype( (string) $upload['file'] );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => is_string( $type['type'] ) ? $type['type'] : '',
				'post_title'     => pathinfo( $file_name, PATHINFO_FILENAME ),
				'post_status'    => 'inherit',
			),
			(string) $upload['file']
		);
		if ( 0 === $attachment_id ) {
			return '';
		}

		// Mark this as an imported file so a re-import can find and reuse it, and
		// store a content hash so a changed source image is re-uploaded, not reused.
		update_post_meta( $attachment_id, self::META_IMPORT_FILE, $file_name );
		update_post_meta( $attachment_id, self::META_IMPORT_HASH, $hash );

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, (string) $upload['file'] ) );

		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Sanitize raw SVG markup with enshrined/svg-sanitize before it is stored.
	 * The library is loaded from vendor/ on demand, like the Markdown converter.
	 *
	 * @param string $data Raw SVG file contents.
	 * @return string Sanitized SVG, or an empty string when it cannot be sanitized.
	 */
	private static function sanitize_svg( string $data ): string {
		if ( ! class_exists( \enshrined\svgSanitize\Sanitizer::class ) ) {
			$autoload = LIVING_HANDBOOK_DIR . 'vendor/autoload.php';
			if ( is_readable( $autoload ) ) {
				require_once $autoload;
			}
		}
		if ( ! class_exists( \enshrined\svgSanitize\Sanitizer::class ) ) {
			return '';
		}
		$sanitizer = new \enshrined\svgSanitize\Sanitizer();
		$clean     = $sanitizer->sanitize( $data );
		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * Enqueue the import app on this admin page only, and load its JavaScript
	 * translations with wp_set_script_translations().
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_register_script(
			'living-handbook-markdown-import',
			LIVING_HANDBOOK_URL . 'assets/js/markdown-import.js',
			array( 'wp-blocks', 'wp-block-library', 'wp-api-fetch', 'wp-dom-ready', 'wp-i18n' ),
			LIVING_HANDBOOK_VERSION,
			true
		);
		wp_enqueue_script( 'living-handbook-markdown-import' );
		wp_localize_script(
			'living-handbook-markdown-import',
			'lhImport',
			array(
				'convertPath'    => '/living-handbook/v1/convert',
				'zipPath'        => '/living-handbook/v1/import-zip',
				'githubPath'     => '/living-handbook/v1/import-github',
				'createPath'     => '/living-handbook/v1/create',
				'finalizePath'   => '/living-handbook/v1/finalize',
				'appHandbookUrl' => AppHandbook::url(),
			)
		);

		wp_set_script_translations( 'living-handbook-markdown-import', 'living-handbook', LIVING_HANDBOOK_DIR . 'languages' );
	}

	/**
	 * Render the options the three Markdown sources share, inside the panel of one
	 * of them. Each panel carries its own copy with its own ids; the script reads
	 * them from the panel that is open, so a source always shows the options that
	 * belong to it and nothing else.
	 *
	 * @param array<int, mixed> $handbooks  The handbook terms.
	 * @param string            $suffix     Panel suffix, to keep the ids unique.
	 * @param bool              $with_title Whether the page title field applies.
	 * @return void
	 */
	private function render_markdown_options( array $handbooks, string $suffix, bool $with_title ): void {
		$handbook_id = 'lh-import-handbook-' . $suffix;
		$title_id    = 'lh-import-title-' . $suffix;
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $handbook_id ); ?>"><?php esc_html_e( 'Target handbook', 'living-handbook' ); ?></label></th>
				<td>
					<select id="<?php echo esc_attr( $handbook_id ); ?>" class="lh-import-handbook">
						<option value="0"><?php esc_html_e( '— select a handbook —', 'living-handbook' ); ?></option>
						<?php foreach ( $handbooks as $term ) : ?>
							<?php if ( $term instanceof WP_Term ) : ?>
								<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Applied to the imported page(s). A "Handbuch" line in the transport block overrides it.', 'living-handbook' ); ?></p>
					<?php if ( empty( $handbooks ) ) : ?>
						<p class="description"><?php esc_html_e( 'No handbooks yet. Create one first under Handbook, Handbooks.', 'living-handbook' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $with_title ) : ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Page title (optional)', 'living-handbook' ); ?></label></th>
					<td>
						<input type="text" id="<?php echo esc_attr( $title_id ); ?>" class="regular-text lh-import-title">
						<p class="description"><?php esc_html_e( 'If empty, the first heading of the document is used.', 'living-handbook' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Render the import page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! MarkdownConverter::available() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Import', 'living-handbook' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The Markdown library is missing. Run in the plugin folder: composer require league/commonmark', 'living-handbook' ) . '</p></div></div>';
			return;
		}
		$handbooks = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
			)
		);
		$handbooks = is_array( $handbooks ) ? $handbooks : array();
		$bundle    = HandbookImport::can_import();
		$app       = AppHandbook::can_load();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import', 'living-handbook' ); ?></h1>
			<style>
				.living-handbook-import__step{margin:1.5rem 0 .25rem}
				.living-handbook-import__tablist{display:flex;flex-wrap:wrap;gap:.25rem;border-bottom:1px solid #c3c4c7;margin:.5rem 0 0}
				.living-handbook-import__tab{appearance:none;background:transparent;border:1px solid transparent;border-bottom:none;padding:.5rem .9rem;margin-bottom:-1px;cursor:pointer;font-size:13px;line-height:1.6;border-radius:4px 4px 0 0;color:#1d2327}
				.living-handbook-import__tab .dashicons{font-size:16px;width:16px;height:16px;vertical-align:text-bottom;margin-inline-end:.25rem}
				.living-handbook-import__tab[aria-selected="true"]{background:#fff;border-color:#c3c4c7;font-weight:600}
				.living-handbook-import__tab:focus-visible{outline:2px solid #2271b1;outline-offset:-2px}
				.living-handbook-import__panel{max-width:820px;border:1px solid #c3c4c7;border-top:none;padding:1rem;background:#fff}
			</style>
			<p class="description"><?php esc_html_e( 'Pick a source. Everything that source needs sits in its own tab.', 'living-handbook' ); ?></p>
			<div class="living-handbook-import__tabs">
				<div class="living-handbook-import__tablist" role="tablist" aria-label="<?php esc_attr_e( 'Import source', 'living-handbook' ); ?>">
					<button type="button" class="living-handbook-import__tab" role="tab" id="lh-tab-paste" aria-controls="lh-panel-paste" aria-selected="true"><span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Paste text', 'living-handbook' ); ?></button>
					<button type="button" class="living-handbook-import__tab" role="tab" id="lh-tab-zip" aria-controls="lh-panel-zip" aria-selected="false" tabindex="-1"><span class="dashicons dashicons-media-archive" aria-hidden="true"></span><?php esc_html_e( 'ZIP file', 'living-handbook' ); ?></button>
					<button type="button" class="living-handbook-import__tab" role="tab" id="lh-tab-github" aria-controls="lh-panel-github" aria-selected="false" tabindex="-1"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span><?php esc_html_e( 'GitHub', 'living-handbook' ); ?></button>
					<?php if ( $bundle ) : ?>
						<button type="button" class="living-handbook-import__tab" role="tab" id="lh-tab-bundle" aria-controls="lh-panel-bundle" aria-selected="false" tabindex="-1"><span class="dashicons dashicons-database-import" aria-hidden="true"></span><?php esc_html_e( 'Bundle', 'living-handbook' ); ?></button>
					<?php endif; ?>
					<?php if ( $app ) : ?>
						<button type="button" class="living-handbook-import__tab" role="tab" id="lh-tab-app" aria-controls="lh-panel-app" aria-selected="false" tabindex="-1"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span><?php esc_html_e( 'App handbook', 'living-handbook' ); ?></button>
					<?php endif; ?>
				</div>

				<div class="living-handbook-import__panel" id="lh-panel-paste" role="tabpanel" aria-labelledby="lh-tab-paste">
					<label class="screen-reader-text" for="lh-import-md"><?php esc_html_e( 'Paste Markdown', 'living-handbook' ); ?></label>
					<textarea id="lh-import-md" rows="14" class="large-text code" placeholder="<?php esc_attr_e( 'Paste a Markdown draft here', 'living-handbook' ); ?>"></textarea>
					<?php $this->render_markdown_options( $handbooks, 'paste', true ); ?>
					<p><button type="button" class="button button-primary lh-import-run" id="lh-import-run-paste"><?php esc_html_e( 'Import Markdown', 'living-handbook' ); ?></button></p>
				</div>

				<div class="living-handbook-import__panel" id="lh-panel-zip" role="tabpanel" aria-labelledby="lh-tab-zip" hidden>
					<label class="screen-reader-text" for="lh-import-zip"><?php esc_html_e( 'ZIP file', 'living-handbook' ); ?></label>
					<input type="file" id="lh-import-zip" accept=".zip">
					<p class="description"><?php esc_html_e( 'Flat set of .md files, or a repository export with a mkdocs.yml for a structured import.', 'living-handbook' ); ?></p>
					<?php $this->render_markdown_options( $handbooks, 'zip', false ); ?>
					<p><button type="button" class="button button-primary lh-import-run" id="lh-import-run-zip"><?php esc_html_e( 'Import ZIP', 'living-handbook' ); ?></button></p>
				</div>

				<div class="living-handbook-import__panel" id="lh-panel-github" role="tabpanel" aria-labelledby="lh-tab-github" hidden>
					<label class="screen-reader-text" for="lh-import-github"><?php esc_html_e( 'GitHub URL', 'living-handbook' ); ?></label>
					<input type="url" id="lh-import-github" class="large-text code" placeholder="https://github.com/.../file.md or .../tree/main/folder">
					<p class="description"><?php esc_html_e( 'Creates locked pages pulled from a public GitHub repository.', 'living-handbook' ); ?></p>
					<?php $this->render_markdown_options( $handbooks, 'github', true ); ?>
					<p><button type="button" class="button button-primary lh-import-run" id="lh-import-run-github"><?php esc_html_e( 'Import from GitHub', 'living-handbook' ); ?></button></p>
				</div>

				<?php if ( $bundle ) : ?>
					<div class="living-handbook-import__panel" id="lh-panel-bundle" role="tabpanel" aria-labelledby="lh-tab-bundle" hidden>
						<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="living_handbook_import_bundle">
							<?php wp_nonce_field( 'living_handbook_import_bundle' ); ?>
							<label class="screen-reader-text" for="lh-bundle-file"><?php esc_html_e( 'Bundle file', 'living-handbook' ); ?></label>
							<input type="file" id="lh-bundle-file" name="bundle" accept=".zip">
							<p class="description"><?php esc_html_e( 'A bundle exported from another site running the plugin. Nothing is ever deleted, and a page marked as protected is never overwritten.', 'living-handbook' ); ?></p>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="lh-bundle-handbook"><?php esc_html_e( 'Import into', 'living-handbook' ); ?></label></th>
									<td>
										<select id="lh-bundle-handbook" name="handbook">
											<option value="0"><?php esc_html_e( '— the handbook named in the bundle —', 'living-handbook' ); ?></option>
											<?php foreach ( $handbooks as $term ) : ?>
												<?php if ( $term instanceof WP_Term ) : ?>
													<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
												<?php endif; ?>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'By default the bundle goes into its own handbook, which is created here if it does not exist yet. Pick an existing handbook to put the pages there instead; its access configuration stays as it is.', 'living-handbook' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'If a page already exists', 'living-handbook' ); ?></th>
									<td>
										<fieldset>
											<legend class="screen-reader-text"><?php esc_html_e( 'If a page already exists', 'living-handbook' ); ?></legend>
											<?php $first = true; ?>
											<?php foreach ( HandbookImport::rules() as $value => $label ) : ?>
												<label style="display:block;margin-bottom:.25rem">
													<input type="radio" name="rule" value="<?php echo esc_attr( $value ); ?>" <?php checked( $first ); ?>>
													<?php echo esc_html( $label ); ?>
												</label>
												<?php $first = false; ?>
											<?php endforeach; ?>
										</fieldset>
									</td>
								</tr>
							</table>
							<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import bundle', 'living-handbook' ); ?></button></p>
						</form>
					</div>
				<?php endif; ?>
				<?php if ( $app ) : ?>
					<div class="living-handbook-import__panel" id="lh-panel-app" role="tabpanel" aria-labelledby="lh-tab-app" hidden>
						<p><?php esc_html_e( 'The plugin brings a handbook of its own: the documentation of the app, written as a Living Handbook. It is maintained on GitHub, so this loads the current state straight from there, with its pages, structure and images.', 'living-handbook' ); ?></p>
						<p class="description"><?php esc_html_e( 'The pages are pulled from the repository, published into the handbook you pick, and kept in sync, so later changes on GitHub reach your site on the next load. Whether they are visible on the front end depends on that handbook: set it to "members" and only logged-in people see them. Nothing is deleted.', 'living-handbook' ); ?></p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="lh-app-handbook"><?php esc_html_e( 'Load into', 'living-handbook' ); ?></label></th>
								<td>
									<select id="lh-app-handbook" class="lh-import-handbook" name="handbook">
										<option value="0"><?php esc_html_e( '— no handbook (assign later) —', 'living-handbook' ); ?></option>
										<?php foreach ( $handbooks as $term ) : ?>
											<?php if ( $term instanceof WP_Term ) : ?>
												<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
											<?php endif; ?>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Pick the handbook the pages should belong to. Create one first (for example "App handbook") if you want them in their own, and set who may read it there.', 'living-handbook' ); ?></p>
								</td>
							</tr>
						</table>
						<p><button type="button" id="lh-app-btn" class="button button-primary"><?php esc_html_e( 'Load app handbook', 'living-handbook' ); ?></button></p>
					</div>
				<?php endif; ?>
			</div>
			<p><span id="lh-import-status" aria-live="polite"></span></p>
			<ul id="lh-import-results" style="list-style:disc;margin-left:1.5em;"></ul>

		</div>
		<?php
	}
}
