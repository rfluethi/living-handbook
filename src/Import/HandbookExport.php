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

	private const MENU_SLUG = 'living-handbook-export';

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
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
	}

	/**
	 * Add the export screen under the handbook menu. Export is the opposite
	 * direction of import, so it gets its own page, the way WordPress separates
	 * its own import and export tools.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Handbook::POST_TYPE,
			__( 'Export handbooks', 'living-handbook' ),
			__( 'Export', 'living-handbook' ),
			'edit_others_posts',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the export screen: pick a handbook, optionally one of its areas.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! self::can_export() ) {
			return;
		}
		$handbooks = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
			)
		);
		$handbooks = is_array( $handbooks ) ? $handbooks : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export', 'living-handbook' ); ?></h1>
			<p class="description" style="max-width:820px"><?php esc_html_e( 'Download a handbook, or a single area within it, as a bundle: one ZIP with its pages, configuration and media. Import it on another site running the plugin.', 'living-handbook' ); ?></p>

			<?php if ( empty( $handbooks ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'There are no handbooks yet, so there is nothing to export.', 'living-handbook' ); ?></p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="living_handbook_export">
					<?php wp_nonce_field( 'living_handbook_export' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="lh-export-handbook"><?php esc_html_e( 'Handbook', 'living-handbook' ); ?></label></th>
							<td>
								<select id="lh-export-handbook" name="handbook">
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
							<th scope="row"><label for="lh-export-area"><?php esc_html_e( 'What to export', 'living-handbook' ); ?></label></th>
							<td>
								<select id="lh-export-area" name="area" data-whole="<?php esc_attr_e( '— the whole handbook —', 'living-handbook' ); ?>">
									<option value="0"><?php esc_html_e( '— the whole handbook —', 'living-handbook' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Lists the areas of the handbook chosen above. An area is a top-level page; it exports with its subpages.', 'living-handbook' ); ?></p>
							</td>
						</tr>
					</table>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Export bundle', 'living-handbook' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
		if ( ! empty( $handbooks ) ) {
			// JSON_HEX_TAG matters here: page titles reach this inline script, and
			// wp_get_inline_script_tag() does not strip a closing script tag from
			// the body, so a title holding one would break out of the element.
			$areas = wp_json_encode( $this->export_areas(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
			wp_print_inline_script_tag( 'var lhExportAreas = ' . ( is_string( $areas ) ? $areas : '{}' ) . ';' . self::area_script() );
		}
	}

	/**
	 * The top-level handbook pages offered as an export area, keyed by handbook
	 * term ID. An area is a top-level page (no parent) that belongs to a handbook;
	 * its subpages travel with it on export.
	 *
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private function export_areas(): array {
		$posts = get_posts(
			array(
				'post_type'      => Handbook::POST_TYPE,
				'post_parent'    => 0,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$groups = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$terms = wp_get_object_terms( $post->ID, Handbooks::TAXONOMY );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				if ( ! isset( $groups[ $term->term_id ] ) ) {
					$groups[ $term->term_id ] = array();
				}
				$groups[ $term->term_id ][] = array(
					'id'    => $post->ID,
					'title' => $post->post_title,
				);
			}
		}
		return $groups;
	}

	/**
	 * The small script that refills the area field whenever the handbook selection
	 * changes. Without JavaScript the field keeps its single "whole handbook"
	 * entry, so a whole-handbook export still works.
	 *
	 * @return string
	 */
	private static function area_script(): string {
		return <<<'JS'
( function () {
	var handbook = document.getElementById( 'lh-export-handbook' );
	var area = document.getElementById( 'lh-export-area' );
	if ( ! handbook || ! area || typeof lhExportAreas === 'undefined' ) {
		return;
	}
	var whole = area.getAttribute( 'data-whole' ) || '';
	function fill() {
		var list = lhExportAreas[ handbook.value ] || [];
		area.innerHTML = '';
		var first = document.createElement( 'option' );
		first.value = '0';
		first.textContent = whole;
		area.appendChild( first );
		for ( var i = 0; i < list.length; i++ ) {
			var option = document.createElement( 'option' );
			option.value = String( list[ i ].id );
			option.textContent = list[ i ].title;
			area.appendChild( option );
		}
		area.disabled = ( '0' === handbook.value );
	}
	handbook.addEventListener( 'change', fill );
	fill();
}() );
JS;
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

		// The handbook is chosen first; the area field is filled from it and, when
		// set, narrows the export to that page and its descendants.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$handbook_id = isset( $_POST['handbook'] ) ? absint( wp_unslash( $_POST['handbook'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$area_id = isset( $_POST['area'] ) ? absint( wp_unslash( $_POST['area'] ) ) : 0;

		$term      = null;
		$root_id   = 0;
		$root_slug = '';
		if ( $area_id > 0 ) {
			// An area export: the chosen top page and its descendants. The handbook
			// is taken from that page, so its configuration still travels.
			$post = get_post( $area_id );
			if ( $post instanceof WP_Post && Handbook::POST_TYPE === $post->post_type ) {
				$terms = wp_get_object_terms( $area_id, Handbooks::TAXONOMY );
				$found = ( is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof WP_Term ) ? $terms[0] : null;
				if ( ! $found instanceof WP_Term ) {
					wp_die( esc_html__( 'That page is not in a handbook.', 'living-handbook' ), '', array( 'response' => 400 ) );
				}
				$term      = $found;
				$root_id   = $area_id;
				$root_slug = '' !== $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
			}
		} elseif ( $handbook_id > 0 ) {
			$found = get_term( $handbook_id, Handbooks::TAXONOMY );
			$term  = $found instanceof WP_Term ? $found : null;
		}

		if ( ! $term instanceof WP_Term ) {
			wp_die( esc_html__( 'Choose what to export.', 'living-handbook' ), '', array( 'response' => 400 ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive is not available on the server.', 'living-handbook' ), '', array( 'response' => 501 ) );
		}

		$path = $this->build_zip( $term, $root_id );
		if ( '' === $path ) {
			wp_die( esc_html__( 'The export bundle could not be created.', 'living-handbook' ), '', array( 'response' => 500 ) );
		}

		$base     = '' !== $term->slug ? $term->slug : 'handbook';
		$suffix   = '' !== $root_slug ? '-' . $root_slug : '';
		$filename = 'living-handbook-' . $base . $suffix . '-' . gmdate( 'Y-m-d' ) . '.zip';

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
	 * @param WP_Term $term    Handbook term.
	 * @param int     $root_id Optional area root page ID; 0 exports the whole handbook.
	 * @return string
	 */
	public function build_zip( WP_Term $term, int $root_id = 0 ): string {
		$media    = array();
		$manifest = $this->build_manifest( $term, $media, $root_id );

		$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return '';
		}

		// Not wp_tempnam(): that writes into the uploads folder, which is served
		// over HTTP, so the bundle of a restricted handbook would be downloadable
		// by anyone guessing the name while the export runs. The system temp
		// directory is not web-reachable; fall back if it is not writable.
		$path = tempnam( sys_get_temp_dir(), 'lh-export' );
		if ( ! is_string( $path ) ) {
			$path = wp_tempnam( 'lh-export' );
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
	 * @param WP_Term                     $term    Handbook term.
	 * @param array<string, array<mixed>> $media   Collected media, keyed by attachment ID (out).
	 * @param int                         $root_id Optional area root page ID; 0 exports the whole handbook.
	 * @return array<string, mixed>
	 */
	public function build_manifest( WP_Term $term, array &$media, int $root_id = 0 ): array {
		$visibility = (string) get_term_meta( $term->term_id, Handbooks::META_VISIBILITY, true );
		if ( '' === $visibility ) {
			$visibility = Handbooks::VISIBILITY_MEMBERS;
		}
		$roles = array_values( array_filter( array_map( 'strval', (array) get_term_meta( $term->term_id, Handbooks::META_ROLES, true ) ) ) );

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

		$all = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$all[ $post->ID ] = $post;
			}
		}

		// For an area export, keep only the chosen top page and its descendants.
		// Keys and parents are then computed against that reduced set, so the area
		// root becomes top-level in the bundle.
		$exported = $all;
		if ( $root_id > 0 && isset( $all[ $root_id ] ) ) {
			$subtree  = $this->subtree_ids( $root_id, $all );
			$exported = array();
			foreach ( $all as $id => $post ) {
				if ( isset( $subtree[ $id ] ) ) {
					$exported[ $id ] = $post;
				}
			}
		} else {
			$root_id = 0;
		}

		$pages = array();
		foreach ( $exported as $post ) {
			$pages[] = $this->build_page( $post, $exported, $media );
		}

		// A root id that survived the block above is guaranteed to be in the set.
		$area = null;
		if ( $root_id > 0 ) {
			$area = array(
				'root_key' => $this->page_key( $all[ $root_id ], $exported ),
				'title'    => $all[ $root_id ]->post_title,
			);
		}

		return array(
			'format'   => self::FORMAT,
			'version'  => self::VERSION,
			'exported' => array(
				'site'           => home_url(),
				'plugin_version' => LIVING_HANDBOOK_VERSION,
				'date'           => gmdate( 'c' ),
			),
			'scope'    => $root_id > 0 ? 'area' : 'handbook',
			'area'     => $area,
			'handbook' => array(
				'slug'       => $term->slug,
				'name'       => $term->name,
				'visibility' => $visibility,
				'roles'      => $roles,
				// The per-user allowlist is deliberately not exported. It is personal
				// data (e-mail addresses) in a file that gets downloaded and passed
				// around, and it would not apply on the target anyway: a handbook
				// created by an import starts at "members" visibility, and the target
				// has a different set of users. Whoever imports sets the people.
			),
			'pages'    => $pages,
			'media'    => $this->media_manifest( $media ),
		);
	}

	/**
	 * The IDs of a page subtree: the root and every descendant, resolved against
	 * the full handbook set by walking each page's ancestors up to the root.
	 *
	 * @param int                 $root_id Root page ID.
	 * @param array<int, WP_Post> $all     All handbook pages, keyed by ID.
	 * @return array<int, bool> IDs in the subtree, as keys.
	 */
	private function subtree_ids( int $root_id, array $all ): array {
		$ids = array();
		foreach ( $all as $id => $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$current = $post;
			$guard   = 0;
			while ( $current instanceof WP_Post && $guard < 100 ) {
				if ( $current->ID === $root_id ) {
					$ids[ $id ] = true;
					break;
				}
				$parent = $current->post_parent;
				if ( $parent <= 0 || ! isset( $all[ $parent ] ) ) {
					break;
				}
				$current = $all[ $parent ];
				++$guard;
			}
		}
		return $ids;
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
			// get_the_terms(), not wp_get_object_terms(): it reads through the
			// object term cache, which the query in build_manifest() has already
			// filled for every page of the handbook. Going around that cache costs
			// four queries per page, one per vocabulary, which on a handbook of two
			// thousand pages was 8011 queries and 3.4 seconds instead of 11 and
			// 0.5, in the request that also has to build the ZIP.
			$terms = get_the_terms( $post_id, $taxonomy );
			$list  = array();
			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
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
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
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
