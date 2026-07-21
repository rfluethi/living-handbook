<?php
/**
 * Export one handbook to a self-contained bundle (Etappe 1).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Import;

use LivingHandbook\Git\GitSync;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Post;
use WP_Term;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes a handbook to a bundle: a ZIP with a manifest.json and a media/ folder.
 *
 * The bundle is self-contained, so it can be imported on another site running the
 * plugin without reaching back to this one. It carries the handbook configuration,
 * every page as a block-markup snapshot with a stable hierarchy key, the four
 * vocabulary terms, the freshness metadata, the source per page (a GitHub page
 * keeps its source URL so the target resumes syncing), and the referenced media.
 * Local operational data (feedback counts, sync status) is deliberately left out.
 *
 * The bundle format is specified in the vault (entscheide-und-ideen, "Bündelformat").
 * This class is the export half (Etappe 1); the import half reads the same format.
 */
final class HandbookExport {

	private const FORMAT  = 'living-handbook-bundle';
	private const VERSION = 1;

	private const ACTION = 'living_handbook_export';

	/**
	 * The four vocabulary taxonomies carried per page.
	 */
	private const VOCABULARIES = array(
		Taxonomies::PAGE_TYPE,
		Taxonomies::TOPIC,
		Taxonomies::ROLE,
		Taxonomies::AUDIENCE,
	);

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
	}

	/**
	 * Whether the current user may export. Export can reveal restricted content, so
	 * it needs the content-manager capability, not just edit_posts.
	 *
	 * @return bool
	 */
	public static function can_export(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Handle the export download: validate, build the ZIP, stream it, and exit.
	 *
	 * @return void
	 */
	public function download(): void {
		if ( ! self::can_export() ) {
			wp_die( esc_html__( 'You are not allowed to export handbooks.', 'living-handbook' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$handbook_id = isset( $_POST['handbook'] ) ? absint( wp_unslash( $_POST['handbook'] ) ) : 0;
		$term        = $handbook_id > 0 ? get_term( $handbook_id, Handbooks::TAXONOMY ) : null;
		if ( ! $term instanceof WP_Term ) {
			wp_die( esc_html__( 'Choose a handbook to export.', 'living-handbook' ), '', array( 'response' => 400 ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive is not available on the server.', 'living-handbook' ), '', array( 'response' => 501 ) );
		}

		$path = $this->build_zip( $term );
		if ( '' === $path ) {
			wp_die( esc_html__( 'The export bundle could not be created.', 'living-handbook' ), '', array( 'response' => 500 ) );
		}

		$filename = 'living-handbook-' . ( '' !== $term->slug ? $term->slug : 'handbook' ) . '-' . gmdate( 'Y-m-d' ) . '.zip';

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a generated file to the browser.
		readfile( $path );
		wp_delete_file( $path );
		exit;
	}

	/**
	 * Build the export bundle for a handbook and return the temp ZIP path, or an
	 * empty string on failure.
	 *
	 * @param WP_Term $term Handbook term.
	 * @return string
	 */
	public function build_zip( WP_Term $term ): string {
		$media    = array();
		$manifest = $this->build_manifest( $term, $media );

		$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return '';
		}

		$path = wp_tempnam( 'lh-export' );
		if ( '' === $path ) {
			return '';
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $path );
			return '';
		}
		$zip->addFromString( 'manifest.json', $json );
		foreach ( $media as $item ) {
			if ( isset( $item['_data'] ) && is_string( $item['_data'] ) ) {
				$zip->addFromString( $item['file'], $item['_data'] );
			}
		}
		$zip->close();

		return $path;
	}

	/**
	 * Build the manifest array for a handbook and collect its media by reference.
	 *
	 * @param WP_Term                     $term  Handbook term.
	 * @param array<string, array<mixed>> $media Collected media, keyed by attachment ID (out).
	 * @return array<string, mixed>
	 */
	public function build_manifest( WP_Term $term, array &$media ): array {
		$visibility = (string) get_term_meta( $term->term_id, Handbooks::META_VISIBILITY, true );
		if ( '' === $visibility ) {
			$visibility = Handbooks::VISIBILITY_MEMBERS;
		}
		$roles    = array_values( array_filter( array_map( 'strval', (array) get_term_meta( $term->term_id, Handbooks::META_ROLES, true ) ) ) );
		$user_ids = array_map( 'intval', (array) get_term_meta( $term->term_id, Handbooks::META_USERS, true ) );

		$posts = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Handbooks::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			)
		);

		$in_set = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$in_set[ $post->ID ] = $post;
			}
		}

		$pages = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$pages[] = $this->build_page( $post, $in_set, $media );
			}
		}

		return array(
			'format'   => self::FORMAT,
			'version'  => self::VERSION,
			'exported' => array(
				'site'           => home_url(),
				'plugin_version' => LIVING_HANDBOOK_VERSION,
				'date'           => gmdate( 'c' ),
			),
			'scope'    => 'handbook',
			'handbook' => array(
				'slug'       => $term->slug,
				'name'       => $term->name,
				'visibility' => $visibility,
				'roles'      => $roles,
				'users'      => $this->user_identifiers( $user_ids ),
			),
			'pages'    => $pages,
			'media'    => $this->media_manifest( $media ),
		);
	}

	/**
	 * Build one page entry and collect its media.
	 *
	 * @param WP_Post                     $post   The page.
	 * @param array<int, WP_Post>         $in_set Pages of this handbook, keyed by ID.
	 * @param array<string, array<mixed>> $media  Collected media (out).
	 * @return array<string, mixed>
	 */
	private function build_page( WP_Post $post, array $in_set, array &$media ): array {
		$source = (string) get_post_meta( $post->ID, GitSync::META_SOURCE, true );
		if ( '' === $source ) {
			$source = GitSync::SOURCE_WORDPRESS;
		}
		$markdown_url = (string) get_post_meta( $post->ID, GitSync::META_URL, true );
		$reviewer_id  = (int) get_post_meta( $post->ID, Metadata::REVIEWER, true );

		$page_media = $this->collect_page_media( $post, $media );

		return array(
			'key'          => $this->page_key( $post, $in_set ),
			'origin_id'    => home_url() . '#' . $post->ID,
			'parent_key'   => ( $post->post_parent > 0 && isset( $in_set[ $post->post_parent ] ) )
				? $this->page_key( $in_set[ $post->post_parent ], $in_set )
				: null,
			'order'        => (int) $post->menu_order,
			'title'        => $post->post_title,
			'slug'         => $this->post_slug( $post ),
			'status'       => $post->post_status,
			'source'       => $source,
			'markdown_url' => '' !== $markdown_url ? $markdown_url : null,
			'content'      => $post->post_content,
			'terms'        => $this->page_terms( $post->ID ),
			'meta'         => array(
				'last_reviewed'   => (string) get_post_meta( $post->ID, Metadata::REVIEWED, true ),
				'review_interval' => (int) get_post_meta( $post->ID, Metadata::INTERVAL, true ),
				'reviewer'        => $this->user_identifier( $reviewer_id ),
				'toc_depth'       => (int) get_post_meta( $post->ID, Metadata::TOC_DEPTH, true ),
				'ai_exclude'      => (bool) get_post_meta( $post->ID, Metadata::AI_EXCLUDE, true ),
			),
			'media'        => $page_media,
		);
	}

	/**
	 * The stable hierarchy key of a page: the slugs from the top ancestor down to
	 * the page, joined by "/". Walks only ancestors that are in the exported set,
	 * so a page whose parent lives outside the handbook is treated as top-level.
	 *
	 * @param WP_Post             $post   The page.
	 * @param array<int, WP_Post> $in_set Pages of this handbook, keyed by ID.
	 * @return string
	 */
	private function page_key( WP_Post $post, array $in_set ): string {
		$parts   = array();
		$current = $post;
		$guard   = 0;
		while ( $current instanceof WP_Post && $guard < 100 ) {
			array_unshift( $parts, $this->post_slug( $current ) );
			$parent = $current->post_parent;
			if ( $parent <= 0 || ! isset( $in_set[ $parent ] ) ) {
				break;
			}
			$current = $in_set[ $parent ];
			++$guard;
		}
		return implode( '/', $parts );
	}

	/**
	 * A page's slug, falling back to a slug derived from the title.
	 *
	 * @param WP_Post $post The page.
	 * @return string
	 */
	private function post_slug( WP_Post $post ): string {
		return '' !== $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
	}

	/**
	 * The four vocabulary terms of a page, each as a list of slug/name pairs, so the
	 * importer can match by slug and create with the name if the term is missing.
	 *
	 * @param int $post_id Page ID.
	 * @return array<string, array<int, array<string, string>>>
	 */
	private function page_terms( int $post_id ): array {
		$out = array();
		foreach ( self::VOCABULARIES as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy );
			$list  = array();
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( $term instanceof WP_Term ) {
						$list[] = array(
							'slug' => $term->slug,
							'name' => $term->name,
						);
					}
				}
			}
			$out[ $taxonomy ] = $list;
		}
		return $out;
	}

	/**
	 * Collect the media a page references (embedded upload URLs and its featured
	 * image) into the shared media map, and return the bundle file names it uses.
	 *
	 * @param WP_Post                     $post  The page.
	 * @param array<string, array<mixed>> $media Collected media, keyed by attachment ID (out).
	 * @return array<int, string> Bundle file names referenced by this page.
	 */
	private function collect_page_media( WP_Post $post, array &$media ): array {
		$ids     = array();
		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';

		if ( '' !== $baseurl && preg_match_all( '#https?://[^\s"\'<>()]+#', $post->post_content, $matches ) ) {
			foreach ( $matches[0] as $url ) {
				$url = html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' );
				if ( 0 !== strpos( $url, $baseurl ) ) {
					continue;
				}
				$attachment_id = attachment_url_to_postid( $url );
				if ( $attachment_id > 0 ) {
					$ids[ $attachment_id ] = $attachment_id;
				}
			}
		}

		$thumb = get_post_thumbnail_id( $post );
		if ( $thumb ) {
			$ids[ (int) $thumb ] = (int) $thumb;
		}

		$files = array();
		foreach ( $ids as $attachment_id ) {
			$file = $this->add_media( (int) $attachment_id, $media );
			if ( '' !== $file ) {
				$files[] = $file;
			}
		}
		return $files;
	}

	/**
	 * Add one attachment to the shared media map (deduplicated by ID) and return its
	 * bundle file name, or an empty string when the file cannot be read.
	 *
	 * @param int                         $attachment_id Attachment ID.
	 * @param array<string, array<mixed>> $media         Collected media, keyed by attachment ID (out).
	 * @return string
	 */
	private function add_media( int $attachment_id, array &$media ): string {
		$key = (string) $attachment_id;
		if ( isset( $media[ $key ] ) ) {
			return (string) $media[ $key ]['file'];
		}

		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return '';
		}
		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local upload for the bundle.
		if ( false === $data ) {
			return '';
		}

		$file = 'media/' . $attachment_id . '-' . sanitize_file_name( basename( $path ) );
		$url  = wp_get_attachment_url( $attachment_id );
		$alt  = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		$media[ $key ] = array(
			'file'         => $file,
			'original_url' => is_string( $url ) ? $url : '',
			'alt'          => $alt,
			'_data'        => $data,
		);
		return $file;
	}

	/**
	 * The media manifest: the collected media without the binary payload.
	 *
	 * @param array<string, array<mixed>> $media Collected media.
	 * @return array<int, array<string, string>>
	 */
	private function media_manifest( array $media ): array {
		$out = array();
		foreach ( $media as $item ) {
			$out[] = array(
				'file'         => (string) $item['file'],
				'original_url' => (string) $item['original_url'],
				'alt'          => (string) $item['alt'],
			);
		}
		return $out;
	}

	/**
	 * Map user IDs to a stable identifier (e-mail, falling back to login), dropping
	 * users that no longer exist.
	 *
	 * @param int[] $user_ids User IDs.
	 * @return string[]
	 */
	private function user_identifiers( array $user_ids ): array {
		$out = array();
		foreach ( $user_ids as $user_id ) {
			$identifier = $this->user_identifier( (int) $user_id );
			if ( '' !== $identifier ) {
				$out[] = $identifier;
			}
		}
		return $out;
	}

	/**
	 * A single user's stable identifier: e-mail, or the login when no e-mail.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function user_identifier( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}
		$user = get_userdata( $user_id );
		if ( false === $user ) {
			return '';
		}
		return '' !== $user->user_email ? $user->user_email : $user->user_login;
	}
}
