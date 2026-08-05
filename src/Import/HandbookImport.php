<?php
/**
 * Import a handbook bundle produced by HandbookExport (Etappen 2 to 4).
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
use WP_Term;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads a bundle ZIP back into handbook pages on this site.
 *
 * Matching: a page is recognised by the origin id it was exported with, then by
 * its bundle key, then by slug within the target handbook. What happens on a
 * match is the run rule the importer picks: skip (the safe default, never
 * overwrite), update, or always create. A page carrying the protected flag is
 * never overwritten regardless of the rule, and nothing is ever deleted.
 *
 * On update the local operational data stays: the feedback counts and the review
 * date, interval and reviewer belong to this site, so only title, content,
 * structure and terms are refreshed. On a first import those review fields do
 * come from the bundle.
 *
 * Trust model: a bundle is a file from another site, so its content is treated as
 * external and sanitised, exactly like the Markdown import and the GitHub sync.
 * The content cannot be pushed through kses in one piece, because that would
 * destroy the block delimiters; HtmlSanitizer::clean_blocks() parses the blocks
 * first and cleans only the HTML inside them. Relying on the capability alone
 * would not do: on a single site, editors and administrators hold unfiltered_html,
 * so the core filter does not run for exactly the people who may import. Media is
 * sanitised on its own way in, through the existing sideload (which cleans SVG).
 */
final class HandbookImport {

	private const ACTION = 'living_handbook_import_bundle';

	private const NOTICE_TRANSIENT = 'living_handbook_bundle_report_';

	/**
	 * Meta that identifies an imported page across sites.
	 */
	public const META_ORIGIN = '_lh_origin_id';

	/**
	 * Meta holding the page's key inside the bundle it came from.
	 */
	public const META_BUNDLE_KEY = '_lh_bundle_key';

	/**
	 * Whether this run creates ordinary WordPress pages instead of handbook
	 * pages.
	 *
	 * A bundle carries finished block markup, a hierarchy and its images, and
	 * none of that is specific to the handbook post type: the same file can just
	 * as well become a tree of ordinary pages. What does not come along is
	 * everything the handbook adds around a page, and it is worth naming rather
	 * than discovering: no handbook, so no access rule and no navigation, no
	 * table of contents, no badges, no feedback and no source note, because those
	 * live in the handbook template and in blocks that check their context. The
	 * text, its images and its diagrams do come along.
	 *
	 * Pages created this way are always drafts, whatever the bundle says. A
	 * bundle from an internal handbook would otherwise be published by the act of
	 * importing it, which is the one mistake in this direction that cannot be
	 * taken back.
	 *
	 * @var bool
	 */
	private bool $as_pages = false;

	/**
	 * Meta that pins a page as never-overwrite, regardless of the run rule.
	 */
	public const META_PROTECTED = '_lh_import_protected';

	public const RULE_SKIP   = 'skip';
	public const RULE_UPDATE = 'update';
	public const RULE_CREATE = 'create';

	/**
	 * Post statuses a bundle may set.
	 */
	private const STATUSES = array( 'publish', 'future', 'draft', 'pending', 'private' );

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_notices', array( $this, 'result_notice' ) );
	}

	/**
	 * Whether the current user may import a bundle. Importing writes content and
	 * can touch pages of other authors, so it needs the content-manager capability.
	 *
	 * @return bool
	 */
	public static function can_import(): bool {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * The run rules, as value to label, for the form and for validation.
	 *
	 * @return array<string, string>
	 */
	public static function rules(): array {
		return array(
			self::RULE_SKIP   => __( 'Skip pages that already exist (never overwrite)', 'living-handbook' ),
			self::RULE_UPDATE => __( 'Update pages that already exist', 'living-handbook' ),
			self::RULE_CREATE => __( 'Always create new pages', 'living-handbook' ),
		);
	}

	/**
	 * Handle the upload: validate, import, store the report, and go back.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! self::can_import() ) {
			wp_die( esc_html__( 'You are not allowed to import bundles.', 'living-handbook' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->finish( array( 'error' => __( 'ZipArchive is not available on the server.', 'living-handbook' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$rule = isset( $_POST['rule'] ) ? sanitize_key( wp_unslash( $_POST['rule'] ) ) : self::RULE_SKIP;
		if ( ! array_key_exists( $rule, self::rules() ) ) {
			$rule = self::RULE_SKIP;
		}

		// An explicitly chosen target handbook overrides the one named in the
		// bundle, so a bundle can be imported into an existing handbook.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$chosen = isset( $_POST['handbook'] ) ? absint( wp_unslash( $_POST['handbook'] ) ) : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$as_pages = isset( $_POST['as_pages'] ) && '1' === sanitize_key( wp_unslash( $_POST['as_pages'] ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a file upload, validated below.
		$tmp = isset( $_FILES['bundle']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['bundle']['tmp_name'] ) ) : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			$this->finish( array( 'error' => __( 'No bundle file received.', 'living-handbook' ) ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp ) ) {
			$this->finish( array( 'error' => __( 'Could not open the bundle.', 'living-handbook' ) ) );
		}

		$raw = $zip->getFromName( 'manifest.json' );
		if ( false === $raw ) {
			$zip->close();
			$this->finish( array( 'error' => __( 'The bundle has no manifest.json.', 'living-handbook' ) ) );
		}

		$manifest = json_decode( (string) $raw, true );
		if ( ! is_array( $manifest ) || ! isset( $manifest['format'] ) || 'living-handbook-bundle' !== $manifest['format'] ) {
			$zip->close();
			$this->finish( array( 'error' => __( 'This file is not a Living Handbook bundle.', 'living-handbook' ) ) );
		}

		$report = $this->import_bundle( $manifest, $zip, $rule, $chosen, $as_pages );
		$zip->close();
		$this->finish( $report );
	}

	/**
	 * Store the report and return to the import screen.
	 *
	 * @param array<string, mixed> $report Result report.
	 * @return void
	 */
	private function finish( array $report ): void {
		set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), $report, 120 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => Handbook::POST_TYPE,
					'page'      => MarkdownImportPage::MENU_SLUG,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Import a decoded manifest with its ZIP, and return the report.
	 *
	 * @param array<string, mixed> $manifest Decoded manifest.
	 * @param ZipArchive           $zip      The open bundle.
	 * @param string               $rule     Run rule.
	 * @param int                  $chosen   Chosen target handbook term ID, or 0 for the bundle's own.
	 * @param bool                 $as_pages Create ordinary WordPress pages instead of handbook pages.
	 * @return array<string, mixed>
	 */
	private function import_bundle( array $manifest, ZipArchive $zip, string $rule, int $chosen = 0, bool $as_pages = false ): array {
		return $this->import_manifest(
			$manifest,
			static function ( string $file ) use ( $zip ) {
				return $zip->getFromName( $file );
			},
			$rule,
			$chosen,
			$as_pages
		);
	}

	/**
	 * Import a decoded manifest. The media reader hands back the bytes of a file
	 * inside the bundle, or false. Keeping that a callable means the import logic
	 * does not depend on ZipArchive and can be exercised directly in tests.
	 *
	 * @param array<string, mixed> $manifest     Decoded manifest.
	 * @param callable             $media_reader Returns the bytes of a bundle file, or false.
	 * @param string               $rule         Run rule.
	 * @param int                  $chosen       Chosen target handbook term ID, or 0 for the bundle's own.
	 * @param bool                 $as_pages     Create ordinary WordPress pages instead of handbook pages.
	 * @return array<string, mixed>
	 */
	public function import_manifest( array $manifest, callable $media_reader, string $rule, int $chosen = 0, bool $as_pages = false ): array {
		$this->as_pages = $as_pages;

		$report = array(
			'created'   => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'protected' => 0,
			'notes'     => array(),
		);

		$term_id = 0;
		if ( $as_pages ) {
			$report['notes'][] = __( 'The bundle was imported as ordinary WordPress pages, as drafts. They carry no handbook, so no access rule, no navigation, no table of contents, no badges and no review data; the text, the images and the diagrams are there.', 'living-handbook' );
		} else {
			$term_id = $this->resolve_handbook( $manifest, $report, $chosen );
			if ( 0 === $term_id ) {
				return array( 'error' => __( 'The bundle names no handbook.', 'living-handbook' ) );
			}
		}

		$media_map = $this->import_media( $manifest, $media_reader );

		$pages = isset( $manifest['pages'] ) && is_array( $manifest['pages'] ) ? $manifest['pages'] : array();
		// Shallow keys first, so a parent always exists before its children.
		usort(
			$pages,
			static function ( $a, $b ): int {
				$ka = isset( $a['key'] ) ? (string) $a['key'] : '';
				$kb = isset( $b['key'] ) ? (string) $b['key'] : '';
				return substr_count( $ka, '/' ) <=> substr_count( $kb, '/' );
			}
		);

		$key_to_id = array();
		$touched   = array();
		foreach ( $pages as $page ) {
			if ( is_array( $page ) ) {
				$this->import_page( $page, $term_id, $rule, $media_map, $key_to_id, $touched, $report );
			}
		}

		// Rewire internal links between the imported pages, reusing the importer's
		// existing finalize pass.
		if ( ! empty( $touched ) ) {
			Postprocessor::finalize( $touched );
		}

		return $report;
	}

	/**
	 * Find or create the target handbook. An existing handbook keeps its access
	 * configuration; a newly created one starts at "members" even when the bundle
	 * says public, so an import can never silently publish content.
	 *
	 * @param array<string, mixed> $manifest Decoded manifest.
	 * @param array<string, mixed> $report   Report (out).
	 * @param int                  $chosen   Chosen target handbook term ID, or 0 for the bundle's own.
	 * @return int Term ID, or 0.
	 */
	private function resolve_handbook( array $manifest, array &$report, int $chosen = 0 ): int {
		if ( $chosen > 0 ) {
			$target = get_term( $chosen, Handbooks::TAXONOMY );
			if ( $target instanceof WP_Term ) {
				$report['notes'][] = __( 'The pages went into the handbook you chose; its access configuration was left unchanged.', 'living-handbook' );
				return (int) $target->term_id;
			}
		}

		$data = isset( $manifest['handbook'] ) && is_array( $manifest['handbook'] ) ? $manifest['handbook'] : array();
		$slug = isset( $data['slug'] ) ? sanitize_title( (string) $data['slug'] ) : '';
		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : $slug;
		if ( '' === $slug ) {
			return 0;
		}

		$existing = get_term_by( 'slug', $slug, Handbooks::TAXONOMY );
		if ( $existing instanceof WP_Term ) {
			$report['notes'][] = __( 'An existing handbook was reused; its access configuration was left unchanged.', 'living-handbook' );
			return (int) $existing->term_id;
		}

		$created = wp_insert_term( '' !== $name ? $name : $slug, Handbooks::TAXONOMY, array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			return 0;
		}
		$term_id = (int) $created['term_id'];

		update_term_meta( $term_id, Handbooks::META_VISIBILITY, Handbooks::VISIBILITY_MEMBERS );
		$report['notes'][] = __( 'The handbook was created with visibility "members". Raise it by hand if the content really should be public.', 'living-handbook' );

		$roles = array();
		foreach ( ( isset( $data['roles'] ) && is_array( $data['roles'] ) ? $data['roles'] : array() ) as $role ) {
			$role = sanitize_key( (string) $role );
			if ( '' !== $role && null !== get_role( $role ) ) {
				$roles[] = $role;
			}
		}
		if ( ! empty( $roles ) ) {
			update_term_meta( $term_id, Handbooks::META_ROLES, $roles );
		}

		// A per-user allowlist is not taken from a bundle. Current exports do not
		// write one, and an older bundle may still carry e-mail addresses from
		// another site's user base; restoring those here would be neither correct
		// nor data-minimal. Whoever imports sets the people by hand.
		if ( ! empty( $data['users'] ) ) {
			$report['notes'][] = __( 'The bundle carried a list of individually allowed users. It was not applied; set the people by hand if the handbook should be restricted.', 'living-handbook' );
		}

		return $term_id;
	}

	/**
	 * Sideload the bundle's media and return a map of the original URL to the new
	 * one, so page content can be pointed at this site's copies.
	 *
	 * @param array<string, mixed> $manifest     Decoded manifest.
	 * @param callable             $media_reader Returns the bytes of a bundle file, or false.
	 * @return array<string, string>
	 */
	private function import_media( array $manifest, callable $media_reader ): array {
		$map   = array();
		$items = isset( $manifest['media'] ) && is_array( $manifest['media'] ) ? $manifest['media'] : array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$file     = isset( $item['file'] ) ? (string) $item['file'] : '';
			$original = isset( $item['original_url'] ) ? (string) $item['original_url'] : '';
			if ( '' === $file || '' === $original ) {
				continue;
			}
			$data = $media_reader( $file );
			if ( ! is_string( $data ) || '' === $data ) {
				continue;
			}
			$url = MarkdownImportPage::sideload_image( basename( $file ), $data );
			if ( '' !== $url ) {
				$map[ $original ] = $url;
			}
		}
		return $map;
	}

	/**
	 * Import one page from the manifest.
	 *
	 * @param array<string, mixed>  $page      Page entry.
	 * @param int                   $term_id   Target handbook term ID.
	 * @param string                $rule      Run rule.
	 * @param array<string, string> $media_map Original URL to new URL.
	 * @param array<string, int>    $key_to_id Bundle key to new post ID (in/out).
	 * @param array<int, int>       $touched   Post IDs written (out).
	 * @param array<string, mixed>  $report    Report (out).
	 * @return void
	 */
	private function import_page( array $page, int $term_id, string $rule, array $media_map, array &$key_to_id, array &$touched, array &$report ): void {
		$key = isset( $page['key'] ) ? (string) $page['key'] : '';
		if ( '' === $key ) {
			return;
		}
		$origin = isset( $page['origin_id'] ) ? (string) $page['origin_id'] : '';
		$slug   = isset( $page['slug'] ) ? sanitize_title( (string) $page['slug'] ) : '';

		$existing = ( self::RULE_CREATE === $rule ) ? 0 : $this->find_existing( $origin, $key, $slug, $term_id );

		if ( $existing > 0 && '' !== (string) get_post_meta( $existing, self::META_PROTECTED, true ) ) {
			++$report['protected'];
			$key_to_id[ $key ] = $existing;
			return;
		}
		if ( $existing > 0 && self::RULE_SKIP === $rule ) {
			++$report['skipped'];
			$key_to_id[ $key ] = $existing;
			return;
		}

		$content = isset( $page['content'] ) ? (string) $page['content'] : '';
		foreach ( $media_map as $from => $to ) {
			$content = str_replace( $from, $to, $content );
		}
		// A bundle is a file from another site, so its content is treated like any
		// other external source: cleaned block by block, which keeps the delimiters.
		$content = HtmlSanitizer::clean_blocks( $content );

		$parent_key = isset( $page['parent_key'] ) ? (string) $page['parent_key'] : '';
		$parent_id  = ( '' !== $parent_key && isset( $key_to_id[ $parent_key ] ) ) ? $key_to_id[ $parent_key ] : 0;

		$data = array(
			'post_type'    => $this->as_pages ? 'page' : Handbook::POST_TYPE,
			'post_title'   => isset( $page['title'] ) ? sanitize_text_field( (string) $page['title'] ) : $slug,
			'post_content' => (string) wp_slash( $content ),
			'post_parent'  => $parent_id,
			'menu_order'   => isset( $page['order'] ) ? (int) $page['order'] : 0,
		);

		$is_new = ( 0 === $existing );
		if ( $is_new ) {
			$status              = isset( $page['status'] ) ? (string) $page['status'] : 'draft';
			$data['post_status'] = in_array( $status, self::STATUSES, true ) ? $status : 'draft';
			if ( $this->as_pages ) {
				// Always a draft, whatever the bundle says. See $as_pages.
				$data['post_status'] = 'draft';
			}
			$data['post_name'] = $slug;
			$result            = wp_insert_post( $data, true );
		} else {
			$data['ID'] = $existing;
			$result     = wp_update_post( $data, true );
		}
		if ( is_wp_error( $result ) ) {
			$report['notes'][] = $result->get_error_message();
			return;
		}

		$post_id           = (int) $result;
		$key_to_id[ $key ] = $post_id;
		$touched[]         = $post_id;
		if ( $is_new ) {
			++$report['created'];
		} else {
			++$report['updated'];
		}

		update_post_meta( $post_id, self::META_ORIGIN, $origin );
		update_post_meta( $post_id, self::META_BUNDLE_KEY, $key );

		if ( $this->as_pages ) {
			// An ordinary page carries none of what follows: the vocabularies are
			// registered on the handbook post type, the review fields are read by
			// screens a page never reaches, and a sync source on a page would
			// promise an update path that does not exist for it.
			return;
		}

		wp_set_object_terms( $post_id, array( $term_id ), Handbooks::TAXONOMY, false );
		$this->apply_terms( $post_id, isset( $page['terms'] ) && is_array( $page['terms'] ) ? $page['terms'] : array() );

		$source = isset( $page['source'] ) ? (string) $page['source'] : GitSync::SOURCE_WORDPRESS;
		update_post_meta( $post_id, GitSync::META_SOURCE, GitSync::SOURCE_GITHUB === $source ? GitSync::SOURCE_GITHUB : GitSync::SOURCE_WORDPRESS );
		$markdown_url = isset( $page['markdown_url'] ) ? (string) $page['markdown_url'] : '';
		if ( '' !== $markdown_url ) {
			update_post_meta( $post_id, GitSync::META_URL, esc_url_raw( $markdown_url ) );
		}

		// The freshness fields are local upkeep, so they only come from the bundle
		// when the page is new; an update leaves this site's own values alone.
		if ( $is_new ) {
			$this->apply_review_meta( $post_id, isset( $page['meta'] ) && is_array( $page['meta'] ) ? $page['meta'] : array() );
		}
	}

	/**
	 * Find an existing page for a bundle entry within the target handbook: by
	 * origin id, then bundle key, then slug.
	 *
	 * @param string $origin  Origin id from the bundle.
	 * @param string $key     Bundle key.
	 * @param string $slug    Page slug.
	 * @param int    $term_id Target handbook term ID.
	 * @return int Post ID, or 0.
	 */
	private function find_existing( string $origin, string $key, string $slug, int $term_id ): int {
		$base = array(
			'post_type'      => Handbook::POST_TYPE,
			'post_status'    => self::STATUSES,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Handbooks::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		);

		foreach ( array(
			self::META_ORIGIN     => $origin,
			self::META_BUNDLE_KEY => $key,
		) as $meta_key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$found = get_posts(
				array_merge(
					$base,
					array(
						'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					)
				)
			);
			if ( ! empty( $found ) ) {
				return (int) $found[0];
			}
		}

		if ( '' !== $slug ) {
			// post_name__in, not name: a "name" query is treated as a singular
			// lookup, and the handbook restriction above would then not apply, so
			// a page of the same slug in another handbook would match.
			$found = get_posts( array_merge( $base, array( 'post_name__in' => array( $slug ) ) ) );
			if ( ! empty( $found ) ) {
				return (int) $found[0];
			}
		}

		return 0;
	}

	/**
	 * Apply the four vocabulary terms, creating a term by slug and name when the
	 * target site does not have it yet.
	 *
	 * @param int                  $post_id Page ID.
	 * @param array<string, mixed> $terms   Terms per taxonomy from the bundle.
	 * @return void
	 */
	private function apply_terms( int $post_id, array $terms ): void {
		$allowed = array( Taxonomies::PAGE_TYPE, Taxonomies::TOPIC, Taxonomies::ROLE, Taxonomies::AUDIENCE );
		foreach ( $allowed as $taxonomy ) {
			$list = isset( $terms[ $taxonomy ] ) && is_array( $terms[ $taxonomy ] ) ? $terms[ $taxonomy ] : array();
			$ids  = array();
			foreach ( $list as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$slug = isset( $entry['slug'] ) ? sanitize_title( (string) $entry['slug'] ) : '';
				$name = isset( $entry['name'] ) ? sanitize_text_field( (string) $entry['name'] ) : $slug;
				if ( '' === $slug ) {
					continue;
				}
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term instanceof WP_Term ) {
					$ids[] = (int) $term->term_id;
					continue;
				}
				$created = wp_insert_term( '' !== $name ? $name : $slug, $taxonomy, array( 'slug' => $slug ) );
				if ( ! is_wp_error( $created ) ) {
					$ids[] = (int) $created['term_id'];
				}
			}
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}
	}

	/**
	 * Write the freshness metadata of a newly created page.
	 *
	 * @param int                  $post_id Page ID.
	 * @param array<string, mixed> $meta    Meta from the bundle.
	 * @return void
	 */
	private function apply_review_meta( int $post_id, array $meta ): void {
		$reviewed = isset( $meta['last_reviewed'] ) ? sanitize_text_field( (string) $meta['last_reviewed'] ) : '';
		if ( '' !== $reviewed ) {
			update_post_meta( $post_id, Metadata::REVIEWED, $reviewed );
		}
		$interval = isset( $meta['review_interval'] ) ? absint( $meta['review_interval'] ) : 0;
		if ( $interval > 0 ) {
			update_post_meta( $post_id, Metadata::INTERVAL, $interval );
		}
		$reviewer = isset( $meta['reviewer'] ) ? $this->user_id_from_identifier( (string) $meta['reviewer'] ) : 0;
		if ( $reviewer > 0 ) {
			update_post_meta( $post_id, Metadata::REVIEWER, $reviewer );
		}
		$depth = isset( $meta['toc_depth'] ) ? absint( $meta['toc_depth'] ) : 0;
		if ( $depth > 0 ) {
			update_post_meta( $post_id, Metadata::TOC_DEPTH, $depth );
		}
		if ( ! empty( $meta['ai_exclude'] ) ) {
			update_post_meta( $post_id, Metadata::AI_EXCLUDE, 1 );
		}
	}

	/**
	 * Resolve a bundle user identifier (e-mail, or login) to a local user ID.
	 *
	 * @param string $identifier E-mail or login.
	 * @return int User ID, or 0.
	 */
	private function user_id_from_identifier( string $identifier ): int {
		$identifier = trim( $identifier );
		if ( '' === $identifier ) {
			return 0;
		}
		$user = is_email( $identifier ) ? get_user_by( 'email', $identifier ) : get_user_by( 'login', $identifier );
		return false !== $user ? (int) $user->ID : 0;
	}

	/**
	 * Show the report of the last import once, on the import screen.
	 *
	 * @return void
	 */
	public function result_notice(): void {
		if ( ! self::can_import() ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || false === strpos( (string) $screen->id, MarkdownImportPage::MENU_SLUG ) ) {
			return;
		}
		$key    = self::NOTICE_TRANSIENT . get_current_user_id();
		$report = get_transient( $key );
		if ( ! is_array( $report ) ) {
			return;
		}
		delete_transient( $key );

		if ( isset( $report['error'] ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( (string) $report['error'] ) );
			return;
		}

		$summary = sprintf(
			/* translators: 1: created count, 2: updated count, 3: skipped count, 4: protected count. */
			__( 'Bundle imported: %1$d created, %2$d updated, %3$d skipped, %4$d protected.', 'living-handbook' ),
			(int) $report['created'],
			(int) $report['updated'],
			(int) $report['skipped'],
			(int) $report['protected']
		);

		$notes = '';
		foreach ( array_unique( (array) $report['notes'] ) as $note ) {
			$notes .= '<li>' . esc_html( (string) $note ) . '</li>';
		}

		printf(
			'<div class="notice notice-success"><p>%1$s</p>%2$s</div>',
			esc_html( $summary ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html above.
			'' !== $notes ? '<ul style="list-style:disc;margin-left:1.5em;">' . $notes . '</ul>' : ''
		);
	}
}
