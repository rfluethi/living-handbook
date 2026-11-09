<?php
/**
 * Markdown export: a handbook as files the Markdown import reads back.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Export;

use LivingHandbook\Git\GitSync;
use LivingHandbook\Import\HandbookExport;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\Support\TempFiles;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_Block_Type_Registry;
use WP_Post;
use WP_Term;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes a handbook, or one of its areas, as a ZIP of Markdown files (E-0083).
 *
 * The ZIP is laid out the way the Markdown import reads it: a mkdocs.yml with
 * the navigation, and under docs/ a folder for every page that has subpages,
 * its own text in the folder's README.md, a file for every other page, and
 * the images in docs/assets/. Each page ends with the transport block the
 * import applies. Plugin blocks without a Markdown form are left out and
 * named in EXPORT.txt, as are pages with a password, which are not exported.
 *
 * The pages are those of the bundle (HandbookExport::export_set()): every
 * page of the handbook the person may view, the area's subtree when one is
 * chosen.
 */
final class MarkdownExport {

	/**
	 * The admin-post action and nonce action of the download.
	 */
	public const ACTION = 'living_handbook_markdown_export';

	/**
	 * Prefix of the temporary ZIP.
	 */
	private const TEMP_PREFIX = 'lh-markdown';

	/**
	 * Folder names a page may not take, because the export uses them.
	 */
	private const RESERVED = array( 'assets', 'readme', 'index' );

	/**
	 * Hooks the download.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'download' ) );
	}

	/**
	 * The section on the export screen.
	 *
	 * @param array<int, WP_Term> $handbooks Handbooks the person may export.
	 * @return void
	 */
	public static function render_form( array $handbooks ): void {
		if ( ! HandbookExport::can_export() || array() === $handbooks ) {
			return;
		}
		?>
		<hr style="margin:2.5rem 0">
		<h2><?php esc_html_e( 'Export as Markdown', 'living-handbook' ); ?></h2>
		<p class="description" style="max-width:820px"><?php esc_html_e( 'A ZIP of Markdown files with their images and a mkdocs.yml, to keep, to put into a repository, or to import again under Handbook, Import. Each page carries its metadata in its transport block. Blocks of the plugin that have no Markdown form, learning paths and pages with a password are left out; EXPORT.txt in the ZIP names them.', 'living-handbook' ); ?></p>
		<div class="notice notice-warning inline" style="max-width:820px"><p>
			<?php esc_html_e( 'The files have no access rules. They contain every page you can read in the handbook you choose, protected files included, and whoever holds the ZIP can read all of it.', 'living-handbook' ); ?>
		</p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<?php wp_nonce_field( self::ACTION ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lh-md-handbook"><?php esc_html_e( 'Handbook', 'living-handbook' ); ?></label></th>
					<td>
						<select id="lh-md-handbook" name="handbook">
							<option value="0"><?php esc_html_e( '— select a handbook —', 'living-handbook' ); ?></option>
							<?php foreach ( $handbooks as $term ) : ?>
								<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lh-md-area"><?php esc_html_e( 'What to export', 'living-handbook' ); ?></label></th>
					<td>
						<select id="lh-md-area" name="area" data-whole="<?php esc_attr_e( '— the whole handbook —', 'living-handbook' ); ?>">
							<option value="0"><?php esc_html_e( '— the whole handbook —', 'living-handbook' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Export Markdown', 'living-handbook' ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Builds the ZIP, streams it and ends the request.
	 *
	 * @return void
	 */
	public function download(): void {
		if ( ! HandbookExport::can_export() ) {
			wp_die( esc_html__( 'You are not allowed to export handbooks.', 'living-handbook' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$handbook_id = isset( $_POST['handbook'] ) ? absint( wp_unslash( $_POST['handbook'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$area_id = isset( $_POST['area'] ) ? absint( wp_unslash( $_POST['area'] ) ) : 0;

		$target = HandbookExport::target( $handbook_id, $area_id );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive is not available on the server.', 'living-handbook' ), '', array( 'response' => 501 ) );
		}

		// A download that broke off ends the request before the file is deleted
		// below; the next export is the next moment that notices.
		TempFiles::sweep( self::TEMP_PREFIX, HOUR_IN_SECONDS );

		$path = self::build_zip( $target['term'], $target['root_id'] );
		if ( '' === $path ) {
			wp_die( esc_html__( 'The Markdown export could not be created.', 'living-handbook' ), '', array( 'response' => 500 ) );
		}

		$name     = self::top_folder( $target['term'], $target['root_slug'] );
		$filename = $name . '-markdown-' . gmdate( 'Y-m-d' ) . '.zip';

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
	 * Writes the ZIP and returns its path, or '' on failure.
	 *
	 * @param WP_Term $term    Handbook.
	 * @param int     $root_id Area page, or 0 for the whole handbook.
	 * @return string
	 */
	public static function build_zip( WP_Term $term, int $root_id = 0 ): string {
		$export = self::build( $term, $root_id );

		// The system temp directory first: wp_tempnam() may fall back to
		// wp-content/, which the web server hands out (see HandbookExport).
		$path = tempnam( sys_get_temp_dir(), self::TEMP_PREFIX );
		if ( ! is_string( $path ) ) {
			$path = wp_tempnam( self::TEMP_PREFIX );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $path );
			return '';
		}
		$top = self::top_folder( $term, $export['root_slug'] ) . '/';
		foreach ( $export['files'] as $name => $content ) {
			$zip->addFromString( $top . $name, $content );
		}
		foreach ( $export['assets'] as $name => $file ) {
			$zip->addFile( $file, $top . $name );
		}
		$zip->close();

		return $path;
	}

	/**
	 * The folder the ZIP unpacks into.
	 *
	 * @param WP_Term $term      Handbook.
	 * @param string  $root_slug Area slug, or ''.
	 * @return string
	 */
	private static function top_folder( WP_Term $term, string $root_slug ): string {
		$base = '' !== $term->slug ? $term->slug : 'handbook';
		$name = sanitize_file_name( rawurldecode( $base . ( '' !== $root_slug ? '-' . $root_slug : '' ) ) );
		return '' !== $name ? $name : 'handbook';
	}

	/**
	 * The export as data: the text files by path, the files to copy by path,
	 * and what the export left out.
	 *
	 * @param WP_Term $term    Handbook.
	 * @param int     $root_id Area page, or 0 for the whole handbook.
	 * @return array{files: array<string, string>, assets: array<string, string>, root_slug: string, left_out: array<string, array<int, string>>, password: array<int, string>, github: array<string, string>}
	 */
	public static function build( WP_Term $term, int $root_id = 0 ): array {
		$set      = ( new HandbookExport() )->export_set( $term, $root_id );
		$root_id  = $set['root_id'];
		$pages    = array();
		$password = array();
		foreach ( $set['exported'] as $id => $post ) {
			if ( '' !== $post->post_password ) {
				$password[] = $post->post_title;
				continue;
			}
			$pages[ $id ] = $post;
		}

		$links = array();
		foreach ( $pages as $id => $post ) {
			foreach ( array( (string) get_permalink( $post ), home_url( '?p=' . $id ), home_url( '?page_id=' . $id ) ) as $address ) {
				if ( '' !== $address ) {
					$links[ self::normal( $address ) ] = (int) $id;
				}
			}
		}

		$state = array(
			'paths'    => self::plan( $pages ),
			'pages'    => $pages,
			'links'    => $links,
			'assets'   => array(),
			'used'     => array(),
			'left_out' => array(),
			'github'   => array(),
		);

		$files = array();
		foreach ( $pages as $id => $post ) {
			$files[ 'docs/' . $state['paths'][ $id ] ] = self::page_file( $post, $state );
		}

		$files['mkdocs.yml'] = self::mkdocs( $term, $pages, $state['paths'] );
		$files['EXPORT.txt'] = self::report( $term, $root_id, count( $pages ), $state['left_out'], $password, $state['github'] );

		$assets = array();
		foreach ( $state['assets'] as $file => $name ) {
			$assets[ 'docs/assets/' . $name ] = $file;
		}

		$root_slug = '';
		if ( $root_id > 0 && isset( $set['all'][ $root_id ] ) ) {
			$root      = $set['all'][ $root_id ];
			$root_slug = '' !== $root->post_name ? $root->post_name : sanitize_title( $root->post_title );
		}

		return array(
			'files'     => $files,
			'assets'    => $assets,
			'root_slug' => $root_slug,
			'left_out'  => $state['left_out'],
			'password'  => $password,
			'github'    => $state['github'],
		);
	}

	/**
	 * The file of every page below docs/: a folder with README.md for a page
	 * with subpages, a .md file for the others.
	 *
	 * A page whose parent is not exported (an area's root, or a page under a
	 * page with a password) moves up to the nearest exported ancestor.
	 *
	 * @param array<int, WP_Post> $pages Exported pages.
	 * @return array<int, string> Page ID to path below docs/.
	 */
	private static function plan( array $pages ): array {
		$children = self::children( $pages );
		$paths    = array();
		self::plan_level( $children, 0, '', $pages, $paths );
		return $paths;
	}

	/**
	 * Exported pages by their exported parent, each level in order.
	 *
	 * @param array<int, WP_Post> $pages Exported pages.
	 * @return array<int, array<int, int>> Parent ID (0 for the top) to child IDs.
	 */
	private static function children( array $pages ): array {
		$children = array();
		foreach ( $pages as $id => $post ) {
			$parent = (int) $post->post_parent;
			$guard  = 0;
			while ( $parent > 0 && ! isset( $pages[ $parent ] ) && $guard++ < 100 ) {
				$parent = (int) wp_get_post_parent_id( $parent );
			}
			$children[ isset( $pages[ $parent ] ) ? $parent : 0 ][] = $id;
		}
		foreach ( $children as $parent => $ids ) {
			usort(
				$ids,
				static function ( int $a, int $b ) use ( $pages ): int {
					$by_order = (int) $pages[ $a ]->menu_order <=> (int) $pages[ $b ]->menu_order;
					return 0 !== $by_order ? $by_order : strcasecmp( $pages[ $a ]->post_title, $pages[ $b ]->post_title );
				}
			);
			$children[ $parent ] = $ids;
		}
		return $children;
	}

	/**
	 * Plans one level.
	 *
	 * @param array<int, array<int, int>> $children Parent to child IDs.
	 * @param int                         $above    Parent of this level.
	 * @param string                      $dir      Folder of this level, with a trailing slash or empty.
	 * @param array<int, WP_Post>         $pages    Exported pages.
	 * @param array<int, string>          $paths    Paths (out).
	 * @return void
	 */
	private static function plan_level( array $children, int $above, string $dir, array $pages, array &$paths ): void {
		$taken = '' === $dir ? self::RESERVED : array( 'readme', 'index' );
		foreach ( $children[ $above ] ?? array() as $id ) {
			$name = self::file_stem( $pages[ $id ] );
			$base = $name;
			$n    = 2;
			while ( in_array( $name, $taken, true ) ) {
				$name = $base . '-' . $n;
				++$n;
			}
			$taken[] = $name;

			if ( isset( $children[ $id ] ) ) {
				$paths[ $id ] = $dir . $name . '/README.md';
				self::plan_level( $children, $id, $dir . $name . '/', $pages, $paths );
			} else {
				$paths[ $id ] = $dir . $name . '.md';
			}
		}
	}

	/**
	 * A file name for a page, from its slug.
	 *
	 * @param WP_Post $post Page.
	 * @return string
	 */
	private static function file_stem( WP_Post $post ): string {
		$slug = '' !== $post->post_name ? rawurldecode( $post->post_name ) : sanitize_title( $post->post_title );
		$name = strtolower( (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', remove_accents( $slug ) ) );
		$name = trim( $name, '-' );
		return '' !== $name ? $name : 'page-' . $post->ID;
	}

	/**
	 * One page as Markdown: title, text and transport block.
	 *
	 * @param WP_Post             $post  Page.
	 * @param array<string,mixed> $state Export state (in and out).
	 * @phpstan-param array{paths: array<int, string>, pages: array<int, WP_Post>, links: array<string, int>, assets: array<string, string>, used: array<int, string>, left_out: array<string, array<int, string>>, github: array<string, string>} $state
	 * @return string
	 */
	private static function page_file( WP_Post $post, array &$state ): string {
		$path    = $state['paths'][ $post->ID ];
		$dropped = array();
		$html    = self::html( $post, $dropped );
		if ( array() !== $dropped ) {
			$state['left_out'][ $post->post_title ] = array_values( array_unique( $dropped ) );
		}
		if ( GitSync::SOURCE_GITHUB === get_post_meta( $post->ID, GitSync::META_SOURCE, true ) ) {
			$state['github'][ $post->post_title ] = (string) get_post_meta( $post->ID, GitSync::META_URL, true );
		}

		$from = dirname( $path );
		$body = MarkdownWriter::convert(
			$html,
			static function ( string $url, string $kind ) use ( &$state, $from ): string {
				return self::map_url( $url, $kind, $from, $state );
			}
		);

		$out = '# ' . str_replace( "\n", ' ', $post->post_title ) . "\n\n";
		if ( '' !== $body ) {
			$out .= $body . "\n\n";
		}
		return $out . self::transport( $post, $state['pages'] );
	}

	/**
	 * The HTML of a page's blocks, without the blocks of the plugin that have
	 * no Markdown form. A Mermaid diagram becomes a mermaid code block, which
	 * the import turns back into the diagram block.
	 *
	 * @param WP_Post            $post    Page.
	 * @param array<int, string> $dropped Titles of the blocks left out (out).
	 * @return string
	 */
	private static function html( WP_Post $post, array &$dropped ): string {
		$html = '';
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			$block = self::prepare( $block, $dropped );
			if ( null !== $block ) {
				$html .= render_block( $block );
			}
		}
		return $html;
	}

	/**
	 * A parsed block made ready for rendering, or null when it is left out.
	 *
	 * @param array<string, mixed> $block   Parsed block.
	 * @param array<int, string>   $dropped Titles of the blocks left out (out).
	 * @return array<string, mixed>|null
	 */
	private static function prepare( array $block, array &$dropped ): ?array {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( 'living-handbook/mermaid' === $name ) {
			$code = (string) ( $block['attrs']['code'] ?? '' );
			return array(
				'blockName'    => null,
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '<pre class="mermaid"><code>' . esc_html( $code ) . '</code></pre>',
				'innerContent' => array( '<pre class="mermaid"><code>' . esc_html( $code ) . '</code></pre>' ),
			);
		}
		if ( 0 === strpos( $name, 'living-handbook/' ) ) {
			$type      = WP_Block_Type_Registry::get_instance()->get_registered( $name );
			$dropped[] = null !== $type && '' !== (string) $type->title ? (string) $type->title : $name;
			return null;
		}

		$inner   = array();
		$content = array();
		$index   = 0;
		foreach ( (array) ( $block['innerContent'] ?? array() ) as $chunk ) {
			if ( null !== $chunk ) {
				$content[] = $chunk;
				continue;
			}
			$child = isset( $block['innerBlocks'][ $index ] ) && is_array( $block['innerBlocks'][ $index ] ) ? self::prepare( $block['innerBlocks'][ $index ], $dropped ) : null;
			++$index;
			if ( null !== $child ) {
				$inner[]   = $child;
				$content[] = null;
			}
		}
		$block['innerBlocks']  = $inner;
		$block['innerContent'] = $content;
		return $block;
	}

	/**
	 * The address to write for a link or an image of a page.
	 *
	 * A link to another exported page becomes the relative path of its file,
	 * an address in the uploads folder becomes a file in docs/assets/, and
	 * every other address stays as it is.
	 *
	 * @param string              $url   Address.
	 * @param string              $kind  "link" or "image".
	 * @param string              $from  Folder of the page's file below docs/, "." at the top.
	 * @param array<string,mixed> $state Export state (in and out).
	 * @phpstan-param array{paths: array<int, string>, pages: array<int, WP_Post>, links: array<string, int>, assets: array<string, string>, used: array<int, string>, left_out: array<string, array<int, string>>, github: array<string, string>} $state
	 * @return string
	 */
	private static function map_url( string $url, string $kind, string $from, array &$state ): string {
		$hash     = strpos( $url, '#' );
		$fragment = false === $hash ? '' : substr( $url, $hash );
		$address  = false === $hash ? $url : substr( $url, 0, $hash );
		if ( '' === $address ) {
			return $url;
		}

		$absolute = 0 === strpos( $address, '/' ) && 0 !== strpos( $address, '//' ) ? home_url( $address ) : $address;

		if ( 'link' === $kind ) {
			$target = $state['links'][ self::normal( $absolute ) ] ?? 0;
			if ( $target > 0 ) {
				return self::relative( $from, $state['paths'][ $target ] ) . $fragment;
			}
		}

		$file = self::upload_file( $absolute );
		if ( '' === $file ) {
			return $url;
		}
		if ( ! isset( $state['assets'][ $file ] ) ) {
			$name = sanitize_file_name( basename( $file ) );
			$base = pathinfo( $name, PATHINFO_FILENAME );
			$ext  = pathinfo( $name, PATHINFO_EXTENSION );
			$n    = 2;
			// The import matches images by file name, so every name is unique.
			while ( in_array( strtolower( $name ), $state['used'], true ) ) {
				$name = $base . '-' . $n . ( '' !== $ext ? '.' . $ext : '' );
				++$n;
			}
			$state['used'][]          = strtolower( $name );
			$state['assets'][ $file ] = $name;
		}
		return self::relative( $from, 'assets/' . $state['assets'][ $file ] );
	}

	/**
	 * An address in the form the link map is keyed by: http, no trailing
	 * slash.
	 *
	 * @param string $url Absolute address without fragment.
	 * @return string
	 */
	private static function normal( string $url ): string {
		return untrailingslashit( set_url_scheme( $url, 'http' ) );
	}

	/**
	 * The file in the uploads folder an address points at, or ''.
	 *
	 * Only a file that really lies inside the uploads folder, the protected
	 * folder included, and whose type WordPress allows: the same rule as the
	 * website export, so a crafted address cannot pull wp-config.php along.
	 *
	 * @param string $url Absolute address.
	 * @return string Resolved path.
	 */
	private static function upload_file( string $url ): string {
		$uploads = wp_get_upload_dir();
		$base    = untrailingslashit( set_url_scheme( (string) $uploads['baseurl'], 'http' ) ) . '/';
		$address = (string) preg_replace( '/[?#].*$/', '', set_url_scheme( $url, 'http' ) );
		if ( 0 !== strpos( $address, $base ) ) {
			return '';
		}
		$relative = rawurldecode( substr( $address, strlen( $base ) ) );
		if ( '' === $relative || str_contains( $relative, "\0" ) || str_contains( $relative, '\\' ) ) {
			return '';
		}
		foreach ( explode( '/', $relative ) as $segment ) {
			if ( '..' === $segment || '.' === $segment ) {
				return '';
			}
		}

		$root = realpath( (string) $uploads['basedir'] );
		$file = realpath( (string) $uploads['basedir'] . '/' . $relative );
		if ( false === $root || false === $file || ! is_file( $file ) || ! is_readable( $file ) ) {
			return '';
		}
		if ( ! str_starts_with( $file, rtrim( $root, '/' ) . '/' ) ) {
			return '';
		}
		$type = wp_check_filetype( $file, get_allowed_mime_types() );
		return empty( $type['ext'] ) ? '' : $file;
	}

	/**
	 * A path relative from one folder to a file, both below docs/.
	 *
	 * @param string $from Folder, "." for docs/ itself.
	 * @param string $to   File.
	 * @return string
	 */
	private static function relative( string $from, string $to ): string {
		$from_parts = '.' === $from || '' === $from ? array() : explode( '/', $from );
		$to_parts   = explode( '/', $to );
		while ( array() !== $from_parts && isset( $to_parts[1] ) && $from_parts[0] === $to_parts[0] ) {
			array_shift( $from_parts );
			array_shift( $to_parts );
		}
		return str_repeat( '../', count( $from_parts ) ) . implode( '/', array_map( 'rawurlencode', $to_parts ) );
	}

	/**
	 * The transport block of a page: the lines the import applies, and the
	 * date of the last update for whoever reads the file.
	 *
	 * @param WP_Post             $post  Page.
	 * @param array<int, WP_Post> $pages Exported pages.
	 * @return string
	 */
	private static function transport( WP_Post $post, array $pages ): string {
		$parent = (int) $post->post_parent;
		$lines  = array(
			'Seitentyp'             => self::term_names( $post->ID, Taxonomies::PAGE_TYPE, 1 ),
			'Slug'                  => '' !== $post->post_name ? rawurldecode( $post->post_name ) : '',
			'Verantwortliche Rolle' => self::term_names( $post->ID, Taxonomies::ROLE, 1 ),
			// The import takes one topic: the first one travels.
			'Thema'                 => self::term_names( $post->ID, Taxonomies::TOPIC, 1 ),
			'Zielgruppe'            => self::term_names( $post->ID, Taxonomies::AUDIENCE, 0 ),
			'Eltern-Seite'          => isset( $pages[ $parent ] ) ? $pages[ $parent ]->post_title : 'oberste Ebene',
			'Reihenfolge'           => (string) (int) $post->menu_order,
			'Textauszug'            => trim( (string) preg_replace( '/\s+/u', ' ', $post->post_excerpt ) ),
			'Letzte Aktualisierung' => (string) get_post_meta( $post->ID, Metadata::UPDATED, true ),
			'Letzte Prüfung'        => (string) get_post_meta( $post->ID, Metadata::REVIEWED, true ),
			'Prüfintervall'         => (int) get_post_meta( $post->ID, Metadata::INTERVAL, true ) > 0 ? (int) get_post_meta( $post->ID, Metadata::INTERVAL, true ) . ' Tage' : '',
		);

		// The block is data, in the German labels the import reads, whatever the
		// language of the site.
		$out = "## Transport-Metadaten\n";
		foreach ( $lines as $label => $value ) {
			$value = trim( str_replace( array( "\r", "\n" ), ' ', (string) $value ) );
			if ( '' !== $value ) {
				$out .= '* ' . $label . ': ' . $value . "\n";
			}
		}
		return $out;
	}

	/**
	 * The names of a page's terms in one taxonomy, comma-separated.
	 *
	 * @param int    $post_id  Page.
	 * @param string $taxonomy Taxonomy.
	 * @param int    $limit    How many, 0 for all.
	 * @return string
	 */
	private static function term_names( int $post_id, string $taxonomy, int $limit ): string {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return '';
		}
		$names = array();
		foreach ( $terms as $term ) {
			$names[] = str_replace( ',', ' ', $term->name );
		}
		if ( $limit > 0 ) {
			$names = array_slice( $names, 0, $limit );
		}
		return implode( ', ', $names );
	}

	/**
	 * The mkdocs.yml: the navigation in the order of the handbook.
	 *
	 * Strings are written as JSON strings, which YAML reads as double-quoted
	 * scalars, so a title with a colon or a quote cannot break the file.
	 *
	 * @param WP_Term             $term  Handbook.
	 * @param array<int, WP_Post> $pages Exported pages.
	 * @param array<int, string>  $paths Page ID to path below docs/.
	 * @return string
	 */
	private static function mkdocs( WP_Term $term, array $pages, array $paths ): string {
		$out  = 'site_name: ' . self::yaml_string( $term->name ) . "\n";
		$out .= "docs_dir: docs\n";
		$out .= "nav:\n";
		$out .= self::nav_level( self::children( $pages ), 0, $pages, $paths, 1 );
		return $out;
	}

	/**
	 * One level of the navigation.
	 *
	 * @param array<int, array<int, int>> $children Parent to child IDs.
	 * @param int                         $above    Parent of this level.
	 * @param array<int, WP_Post>         $pages    Exported pages.
	 * @param array<int, string>          $paths    Paths.
	 * @param int                         $depth    Indentation depth.
	 * @return string
	 */
	private static function nav_level( array $children, int $above, array $pages, array $paths, int $depth ): string {
		$pad = str_repeat( '  ', $depth );
		$out = '';
		foreach ( $children[ $above ] ?? array() as $id ) {
			$title = self::yaml_string( $pages[ $id ]->post_title );
			if ( isset( $children[ $id ] ) ) {
				// A section: its own page first, as README.md, then its pages.
				$out .= $pad . '- ' . $title . ":\n";
				$out .= $pad . '    - ' . self::yaml_string( $paths[ $id ] ) . "\n";
				$out .= self::nav_level( $children, $id, $pages, $paths, $depth + 2 );
				continue;
			}
			$out .= $pad . '- ' . $title . ': ' . self::yaml_string( $paths[ $id ] ) . "\n";
		}
		return $out;
	}

	/**
	 * A string as a double-quoted YAML scalar.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function yaml_string( string $text ): string {
		$json = wp_json_encode( $text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : '""';
	}

	/**
	 * EXPORT.txt: what the ZIP is, how it goes back in, and what it left out.
	 *
	 * @param WP_Term                          $term     Handbook.
	 * @param int                              $root_id  Area page, or 0.
	 * @param int                              $count    Pages exported.
	 * @param array<string, array<int,string>> $left_out Blocks left out per page title.
	 * @param array<int, string>               $password Titles of pages with a password.
	 * @param array<string, string>            $github   GitHub address per page title.
	 * @return string
	 */
	private static function report( WP_Term $term, int $root_id, int $count, array $left_out, array $password, array $github ): string {
		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: handbook name, 2: date and time of the export, 3: version of the plugin. */
			__( 'Markdown export of the handbook "%1$s", made on %2$s with Living Handbook %3$s.', 'living-handbook' ),
			$term->name,
			wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
			LIVING_HANDBOOK_VERSION
		);
		if ( $root_id > 0 ) {
			$lines[] = sprintf(
				/* translators: %s: title of the area page. */
				__( 'Only the area "%s" with its pages.', 'living-handbook' ),
				get_the_title( $root_id )
			);
		}
		$lines[] = sprintf(
			/* translators: %d: number of pages. */
			_n( '%d page is in the folder docs/, its images in docs/assets/.', '%d pages are in the folder docs/, their images in docs/assets/.', $count, 'living-handbook' ),
			$count
		);
		$lines[] = '';
		$lines[] = __( 'To import it again: Handbook, Import, tab ZIP file, upload this ZIP. The mkdocs.yml gives the structure, titles and order, the transport block at the end of each page its metadata. Images come back into the media library; links to other files point into this folder and have to be set again.', 'living-handbook' );
		$lines[] = __( 'Learning paths are not part of a Markdown export.', 'living-handbook' );

		if ( array() !== $left_out ) {
			$lines[] = '';
			$lines[] = __( 'Blocks of the plugin without a Markdown form, left out:', 'living-handbook' );
			foreach ( $left_out as $title => $blocks ) {
				$lines[] = '- ' . $title . ': ' . implode( ', ', $blocks );
			}
		}
		if ( array() !== $password ) {
			$lines[] = '';
			$lines[] = __( 'Pages with a password, not exported:', 'living-handbook' );
			foreach ( $password as $title ) {
				$lines[] = '- ' . $title;
			}
		}
		if ( array() !== $github ) {
			$lines[] = '';
			$lines[] = __( 'Pages synced from GitHub, exported as they are stored here. Their source:', 'living-handbook' );
			foreach ( $github as $title => $url ) {
				$lines[] = '- ' . $title . ': ' . $url;
			}
		}

		return implode( "\n", $lines ) . "\n";
	}
}
