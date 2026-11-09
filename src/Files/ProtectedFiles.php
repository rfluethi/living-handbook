<?php
/**
 * Where the files of a handbook page live (E-0076, Konzept 10).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Files;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Support\Lock;
use LivingHandbook\Support\TimeBudget;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the files of non-public handbooks in the protected folder.
 *
 * The web server hands out a file without asking WordPress. So a file whose
 * page belongs to a handbook that is not public lives under
 * uploads/living-handbook-protected/, where a server rule (ServerRules) sends
 * every request to WordPress and FileDelivery checks access before it answers.
 * Every other file stays where it was, and stays as fast as it was.
 *
 * A file is protected when its parent is a handbook page or a learning path
 * whose handbook is not public, or that belongs to no handbook: such a page is
 * work in progress and readable only to those who read everything, so its
 * files are treated the same way (fail closed).
 *
 * A file moves at four moments:
 *
 * - when it is uploaded to such a page: in wp_generate_attachment_metadata,
 *   after core made the image sizes. Not earlier: core makes the sizes from
 *   the path it uploaded to and writes that path into the metadata, so a
 *   file moved in add_attachment would get its sizes in the public folder.
 *   add_attachment only notes the attachment and checks it at the end of
 *   the request, for one created without metadata. Not through the cron
 *   queue: a cron run beside a long upload could move the main file while
 *   core still makes the sizes from it;
 * - when it is attached to another page (attachment_updated);
 * - when its page moves to another handbook (set_object_terms);
 * - when its handbook changes visibility, and once after the update to
 *   0.79.0: these can touch hundreds of files and go to a queue that a cron
 *   event works off in passes, under a lock.
 *
 * Moving renames the file and all its sizes, updates _wp_attached_file and
 * the path in the metadata, and replaces the old addresses in the content of
 * every handbook page and learning path. Content elsewhere is corrected on
 * output from a map of moved addresses (the_content).
 */
final class ProtectedFiles {

	/**
	 * Folder below the uploads folder.
	 */
	public const DIR = 'living-handbook-protected';

	/**
	 * Option: queue of attachment ids waiting to be checked, or "all".
	 */
	public const OPTION_QUEUE = 'living_handbook_protect_queue';

	/**
	 * Option: moved addresses, old URL to new URL, for content outside the
	 * handbooks.
	 */
	public const OPTION_MOVED = 'living_handbook_moved_files';

	/**
	 * Cron hook that works off the queue.
	 */
	public const CRON_HOOK = 'living_handbook_protect_files';

	/**
	 * Lock name for a pass over the queue.
	 */
	public const LOCK = 'living_handbook_protect_lock';

	/**
	 * Most attachments a page moves at once before the rest goes to the queue.
	 */
	private const INLINE_LIMIT = 20;

	/**
	 * Most moved addresses kept for content outside the handbooks.
	 */
	private const MOVED_LIMIT = 2000;

	/**
	 * Set while this class moves a file, so its own updates do not trigger it
	 * again.
	 *
	 * @var bool
	 */
	private static bool $moving = false;

	/**
	 * Attachments created in this request, checked at its end.
	 *
	 * @var array<int, int>
	 */
	private static array $created = array();

	/**
	 * Hooks the moments a file may have to move.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_generate_attachment_metadata', array( self::class, 'after_metadata' ), 99, 2 );
		add_action( 'add_attachment', array( self::class, 'note_new' ) );
		add_filter( 'wp_unique_filename', array( self::class, 'unique_in_both' ), 10, 3 );
		add_action( 'attachment_updated', array( self::class, 'attachment_updated' ), 10, 3 );
		add_action( 'set_object_terms', array( self::class, 'terms_set' ), 10, 4 );
		add_action( 'added_term_meta', array( self::class, 'visibility_changed' ), 10, 3 );
		add_action( 'updated_term_meta', array( self::class, 'visibility_changed' ), 10, 3 );
		add_action( self::CRON_HOOK, array( self::class, 'run_cron' ) );
		add_filter( 'the_content', array( self::class, 'rewrite_moved' ), 99 );
	}

	/**
	 * The protected folder: path and URL.
	 *
	 * @return array{path:string, url:string}
	 */
	public static function folder(): array {
		$uploads = wp_get_upload_dir();
		return array(
			'path' => trailingslashit( (string) $uploads['basedir'] ) . self::DIR,
			'url'  => trailingslashit( (string) $uploads['baseurl'] ) . self::DIR,
		);
	}

	/**
	 * Whether the files of a post belong in the protected folder.
	 *
	 * @param int $post_id Parent post.
	 * @return bool
	 */
	public static function protects( int $post_id ): bool {
		if ( $post_id <= 0 || ! in_array( get_post_type( $post_id ), AccessController::guarded_post_types(), true ) ) {
			return false;
		}

		$term_id = Handbooks::for_post( $post_id );
		if ( $term_id <= 0 ) {
			return true;
		}

		return Handbooks::VISIBILITY_PUBLIC !== (string) get_term_meta( $term_id, Handbooks::META_VISIBILITY, true );
	}

	/**
	 * Whether an attachment's file lies in the protected folder.
	 *
	 * @param int $attachment_id Attachment.
	 * @return bool
	 */
	public static function is_protected( int $attachment_id ): bool {
		return 0 === strpos( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ), self::DIR . '/' );
	}

	/**
	 * Puts an attachment's files where its parent says they belong.
	 *
	 * @param int $attachment_id Attachment.
	 * @return bool|null True when moved, false when a move failed, null when nothing had to move.
	 */
	public static function reconcile( int $attachment_id ): ?bool {
		if ( self::$moving ) {
			return null;
		}
		$attachment = get_post( $attachment_id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		$want = self::protects( (int) $attachment->post_parent );
		if ( self::is_protected( $attachment_id ) === $want ) {
			return null;
		}

		return self::move( $attachment_id, $want );
	}

	/**
	 * Moves an attachment's files into or out of the protected folder.
	 *
	 * The main file, every size, and the original of a scaled image are
	 * renamed together; when one rename fails, the ones done are undone and
	 * nothing is recorded. Then the stored path and the addresses in the
	 * handbook content are updated.
	 *
	 * @param int  $attachment_id Attachment.
	 * @param bool $protect       Into the protected folder, or out of it.
	 * @return bool
	 */
	public static function move( int $attachment_id, bool $protect ): bool {
		$meta  = wp_get_attachment_metadata( $attachment_id, true );
		$moved = self::move_files( $attachment_id, $protect, is_array( $meta ) ? $meta : array() );
		if ( null === $moved ) {
			return false;
		}
		if ( is_array( $meta ) && array() !== $moved ) {
			self::$moving = true;
			try {
				wp_update_attachment_metadata( $attachment_id, $moved );
			} finally {
				self::$moving = false;
			}
		}
		return true;
	}

	/**
	 * Moves the files right after core made the image sizes of a new upload.
	 *
	 * @param mixed $metadata      Metadata about to be saved.
	 * @param int   $attachment_id Attachment.
	 * @return mixed The metadata, with the new path when the files moved.
	 */
	public static function after_metadata( $metadata, $attachment_id ) {
		if ( self::$moving || ! is_array( $metadata ) ) {
			return $metadata;
		}
		$want = self::protects( (int) wp_get_post_parent_id( (int) $attachment_id ) );
		if ( self::is_protected( (int) $attachment_id ) === $want ) {
			return $metadata;
		}
		$moved = self::move_files( (int) $attachment_id, $want, $metadata );
		return null === $moved || array() === $moved ? $metadata : $moved;
	}

	/**
	 * Keeps a new file name free in the twin folder too.
	 *
	 * Core makes a name unique in the folder it uploads to. The file may move
	 * to the same month in the other folder, where a file of that name can
	 * lie already: the move would fail. So a name taken there counts as taken.
	 *
	 * @param mixed $filename Name core chose.
	 * @param mixed $ext      Extension with dot.
	 * @param mixed $dir      Folder.
	 * @return mixed
	 */
	public static function unique_in_both( $filename, $ext, $dir ) {
		if ( ! is_string( $filename ) || ! is_string( $dir ) ) {
			return $filename;
		}
		$twin = self::twin_dir( $dir );
		if ( null === $twin || ! is_dir( $twin ) ) {
			return $filename;
		}
		$ext  = is_string( $ext ) ? $ext : '';
		$stem = '' !== $ext && substr( $filename, -strlen( $ext ) ) === $ext ? substr( $filename, 0, -strlen( $ext ) ) : $filename;
		$name = $filename;
		$n    = 0;
		while ( $n < 1000 && ( self::name_taken( $twin, $name, $ext ) || ( $name !== $filename && self::name_taken( $dir, $name, $ext ) ) ) ) {
			++$n;
			$name = $stem . '-' . $n . $ext;
		}
		return $name;
	}

	/**
	 * The same month folder on the other side: protected for a normal one,
	 * normal for a protected one. Null outside the uploads.
	 *
	 * @param string $dir Folder.
	 * @return string|null
	 */
	private static function twin_dir( string $dir ): ?string {
		$uploads   = wp_get_upload_dir();
		$basedir   = untrailingslashit( (string) $uploads['basedir'] );
		$protected = $basedir . '/' . self::DIR;
		$dir       = untrailingslashit( $dir );
		if ( $dir === $protected || 0 === strpos( $dir, $protected . '/' ) ) {
			return $basedir . substr( $dir, strlen( $protected ) );
		}
		if ( $dir === $basedir || 0 === strpos( $dir, $basedir . '/' ) ) {
			return $protected . substr( $dir, strlen( $basedir ) );
		}
		return null;
	}

	/**
	 * Whether a folder holds the name or a size or original of it.
	 *
	 * @param string $dir  Folder.
	 * @param string $name File name.
	 * @param string $ext  Extension with dot.
	 * @return bool
	 */
	private static function name_taken( string $dir, string $name, string $ext ): bool {
		if ( file_exists( $dir . '/' . $name ) ) {
			return true;
		}
		$stem  = '' !== $ext ? substr( $name, 0, -strlen( $ext ) ) : $name;
		$files = is_dir( $dir ) ? scandir( $dir ) : false;
		if ( false === $files ) {
			return false;
		}
		$pattern = '/^' . preg_quote( $stem, '/' ) . '-(\d+x\d+|scaled)\./';
		foreach ( $files as $file ) {
			if ( 1 === preg_match( $pattern, $file ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Notes a new attachment, checked at the end of the request.
	 *
	 * @param int $attachment_id Attachment.
	 * @return void
	 */
	public static function note_new( int $attachment_id ): void {
		if ( array() === self::$created ) {
			add_action( 'shutdown', array( self::class, 'check_created' ), 0 );
		}
		self::$created[ $attachment_id ] = $attachment_id;
	}

	/**
	 * Checks the attachments created in this request. Most were moved in the
	 * metadata filter already; this catches the rest.
	 *
	 * @return void
	 */
	public static function check_created(): void {
		$ids           = self::$created;
		self::$created = array();
		foreach ( $ids as $id ) {
			self::reconcile( $id );
		}
	}

	/**
	 * Renames the files and updates the stored path and the addresses in the
	 * content. The metadata is returned rather than saved, so the metadata
	 * filter can hand it on.
	 *
	 * @param int                  $attachment_id Attachment.
	 * @param bool                 $protect       Into the protected folder, or out of it.
	 * @param array<string, mixed> $meta          Current metadata.
	 * @return array<string, mixed>|null The metadata with the new path (empty array when
	 *                                   there is none), or null when the move failed.
	 */
	private static function move_files( int $attachment_id, bool $protect, array $meta ): ?array {
		$relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' === $relative || 0 === strpos( $relative, '/' ) || false !== strpos( $relative, '..' ) ) {
			return null;
		}

		$prefix = self::DIR . '/';
		if ( ( 0 === strpos( $relative, $prefix ) ) === $protect ) {
			return $meta;
		}
		$target = $protect ? $prefix . $relative : substr( $relative, strlen( $prefix ) );

		$uploads = wp_get_upload_dir();
		$basedir = trailingslashit( (string) $uploads['basedir'] );
		$baseurl = trailingslashit( (string) $uploads['baseurl'] );
		$old_dir = dirname( $relative );
		$new_dir = dirname( $target );

		$names = self::file_names( $meta, basename( $relative ) );

		if ( ! wp_mkdir_p( $basedir . $new_dir ) ) {
			return null;
		}
		if ( $protect ) {
			ServerRules::ensure();
		}

		$done = array();
		foreach ( $names as $name ) {
			$from = $basedir . ( '.' === $old_dir ? '' : $old_dir . '/' ) . $name;
			$to   = $basedir . $new_dir . '/' . $name;
			if ( ! file_exists( $from ) ) {
				continue;
			}
			if ( file_exists( $to ) || ! @rename( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- a failed rename is answered below by undoing the others; WP_Filesystem has no atomic rename.
				foreach ( $done as $back_from => $back_to ) {
					@rename( $back_to, $back_from ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- undoing the renames above.
				}
				return null;
			}
			$done[ $from ] = $to;
		}

		self::$moving = true;
		try {
			update_post_meta( $attachment_id, '_wp_attached_file', $target );
		} finally {
			self::$moving = false;
		}
		if ( isset( $meta['file'] ) ) {
			$meta['file'] = $target;
		}
		wp_cache_delete( $prefix . ( $protect ? $relative : $target ), 'living_handbook_files' );

		$map = array();
		foreach ( $names as $name ) {
			$map[ $baseurl . $old_dir . '/' . $name ] = $baseurl . $new_dir . '/' . $name;
		}
		self::rewrite_content( $map );
		self::remember( $map, $protect );

		return $meta;
	}

	/**
	 * The file names of an attachment: the main file, its sizes and the
	 * original of a scaled image.
	 *
	 * @param array<string, mixed> $meta Metadata.
	 * @param string               $main Basename of the main file.
	 * @return array<int, string>
	 */
	private static function file_names( array $meta, string $main ): array {
		$names = array( $main );
		if ( array() !== $meta ) {
			foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
				if ( is_array( $size ) && ! empty( $size['file'] ) ) {
					$names[] = basename( (string) $size['file'] );
				}
			}
			if ( ! empty( $meta['original_image'] ) ) {
				$names[] = basename( (string) $meta['original_image'] );
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Replaces moved addresses in the content of handbook pages and learning
	 * paths, srcset included.
	 *
	 * Only posts whose content contains one of the old addresses are read, and
	 * they are written back without a new revision of meaning: the text did
	 * not change, the address did.
	 *
	 * @param array<string, string> $map Old URL to new URL.
	 * @return void
	 */
	private static function rewrite_content( array $map ): void {
		if ( array() === $map ) {
			return;
		}
		global $wpdb;

		$types = AccessController::guarded_post_types();
		$first = (string) array_key_first( $map );
		$path  = (string) wp_parse_url( dirname( $first ), PHP_URL_PATH );
		// The main file of a big image is name-scaled.jpg, its sizes name-300x200.jpg:
		// search by the stem they share.
		$stem = (string) preg_replace( '/-scaled$/', '', pathinfo( $first, PATHINFO_FILENAME ) );
		$like = '%' . $wpdb->esc_like( $path . '/' . $stem ) . '%';
		$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one LIKE over the guarded types instead of loading every page; the placeholders are built above.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$in}) AND post_content LIKE %s", array_merge( $types, array( $like ) ) ) );

		$replace = self::with_relative( $map );
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			$content = (string) get_post_field( 'post_content', $id, 'raw' );
			$changed = strtr( $content, $replace );
			if ( $changed !== $content ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $changed ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the address changed, not the text: no revision, no "last updated", no save hooks.
				clean_post_cache( $id );
			}
		}
	}

	/**
	 * The map with root-relative addresses added, as content written by hand
	 * or by the import carries them.
	 *
	 * @param array<string, string> $map Absolute old URL to new URL.
	 * @return array<string, string>
	 */
	private static function with_relative( array $map ): array {
		$out = $map;
		foreach ( $map as $old => $new ) {
			$old_path = (string) wp_parse_url( $old, PHP_URL_PATH );
			$new_path = (string) wp_parse_url( $new, PHP_URL_PATH );
			if ( '' !== $old_path && '' !== $new_path ) {
				$out[ '"' . $old_path ] = '"' . $new_path;
			}
		}
		return $out;
	}

	/**
	 * Keeps a moved address for content outside the handbooks.
	 *
	 * Moving back removes the entry instead of adding the reverse: the old
	 * address is valid again.
	 *
	 * @param array<string, string> $map     Old URL to new URL.
	 * @param bool                  $protect Direction of the move.
	 * @return void
	 */
	private static function remember( array $map, bool $protect ): void {
		$moved = (array) get_option( self::OPTION_MOVED, array() );
		foreach ( $map as $old => $new ) {
			if ( $protect ) {
				$moved[ $old ] = $new;
			} else {
				unset( $moved[ $new ] );
			}
		}
		if ( count( $moved ) > self::MOVED_LIMIT ) {
			$moved = array_slice( $moved, -self::MOVED_LIMIT, null, true );
		}
		update_option( self::OPTION_MOVED, $moved, false );
	}

	/**
	 * Corrects moved addresses in content outside the handbooks, on output.
	 *
	 * @param mixed $content Rendered content.
	 * @return mixed
	 */
	public static function rewrite_moved( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '/uploads/' ) ) {
			return $content;
		}
		$moved = (array) get_option( self::OPTION_MOVED, array() );
		if ( array() === $moved ) {
			return $content;
		}
		return strtr( $content, self::with_relative( array_map( 'strval', $moved ) ) );
	}

	/**
	 * An attachment got another parent.
	 *
	 * @param int   $post_id Attachment.
	 * @param mixed $after   After the update.
	 * @param mixed $before  Before.
	 * @return void
	 */
	public static function attachment_updated( int $post_id, $after, $before ): void {
		if ( $after instanceof WP_Post && $before instanceof WP_Post && (int) $after->post_parent !== (int) $before->post_parent ) {
			self::reconcile( $post_id );
		}
	}

	/**
	 * A page or learning path moved to another handbook.
	 *
	 * @param int    $object_id Post.
	 * @param mixed  $terms     Terms given.
	 * @param mixed  $tt_ids    Term taxonomy ids.
	 * @param string $taxonomy  Taxonomy.
	 * @return void
	 */
	public static function terms_set( $object_id, $terms, $tt_ids, $taxonomy ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed -- the hook's signature.
		if ( Handbooks::TAXONOMY !== $taxonomy || self::$moving ) {
			return;
		}
		self::check_children( (int) $object_id );
	}

	/**
	 * A handbook changed visibility: every file of its pages goes to the queue.
	 *
	 * @param int    $meta_id  Meta id.
	 * @param int    $term_id  Term.
	 * @param string $meta_key Key.
	 * @return void
	 */
	public static function visibility_changed( $meta_id, $term_id, $meta_key ): void {
		if ( Handbooks::META_VISIBILITY !== $meta_key ) {
			return;
		}
		$pages = get_posts(
			AccessController::internal(
				array(
					'post_type'      => AccessController::guarded_post_types(),
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the pages of one handbook.
						array(
							'taxonomy'         => Handbooks::TAXONOMY,
							'field'            => 'term_id',
							'terms'            => (int) $term_id,
							'include_children' => false,
						),
					),
				)
			)
		);
		self::enqueue( self::children( array_map( 'intval', $pages ) ) );
	}

	/**
	 * Checks the files of one page: at once when they are few, through the
	 * queue when they are many.
	 *
	 * @param int $post_id Page.
	 * @return void
	 */
	public static function check_children( int $post_id ): void {
		if ( ! in_array( get_post_type( $post_id ), AccessController::guarded_post_types(), true ) ) {
			return;
		}
		$children = self::children( array( $post_id ) );
		if ( count( $children ) > self::INLINE_LIMIT ) {
			self::enqueue( $children );
			return;
		}
		foreach ( $children as $id ) {
			self::reconcile( $id );
		}
	}

	/**
	 * The attachments of a set of posts.
	 *
	 * @param array<int, int> $parents Post ids.
	 * @return array<int, int>
	 */
	public static function children( array $parents ): array {
		$parents = array_values( array_filter( array_map( 'intval', $parents ) ) );
		if ( array() === $parents ) {
			return array();
		}
		global $wpdb;
		$in = implode( ',', $parents );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids only, integers; get_children() per parent would be a query each.
		return array_map( 'intval', (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent IN ({$in})" ) );
	}

	/**
	 * Every attachment whose parent is a guarded post.
	 *
	 * @return array<int, int>
	 */
	public static function all_candidates(): array {
		global $wpdb;
		$types = AccessController::guarded_post_types();
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one join instead of one query per page; the placeholders are built above.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT a.ID FROM {$wpdb->posts} a INNER JOIN {$wpdb->posts} p ON p.ID = a.post_parent WHERE a.post_type = 'attachment' AND p.post_type IN ({$in}) ORDER BY a.ID", $types ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * The candidates that are not where they belong.
	 *
	 * @return array<int, int>
	 */
	public static function misplaced(): array {
		return array_values(
			array_filter(
				self::all_candidates(),
				static function ( int $id ): bool {
					$parent = (int) wp_get_post_parent_id( $id );
					return self::protects( $parent ) !== self::is_protected( $id );
				}
			)
		);
	}

	/**
	 * Adds attachments to the queue and schedules a pass.
	 *
	 * @param array<int, int>|string $ids Ids, or "all" for every candidate.
	 * @return void
	 */
	public static function enqueue( $ids ): void {
		$queue = get_option( self::OPTION_QUEUE, array() );
		if ( 'all' === $ids || 'all' === $queue ) {
			$queue = 'all';
		} else {
			$queue = array_values( array_unique( array_merge( array_map( 'intval', (array) $queue ), array_map( 'intval', (array) $ids ) ) ) );
			if ( array() === $queue ) {
				return;
			}
		}
		update_option( self::OPTION_QUEUE, $queue, false );
		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/**
	 * The cron pass.
	 *
	 * @return void
	 */
	public static function run_cron(): void {
		self::run_queue();
	}

	/**
	 * Works off the queue in one pass, under a lock, and schedules the next
	 * pass when time ran out.
	 *
	 * @param float $seconds Time budget; default from TimeBudget.
	 * @return array{moved:int, failed:int, remaining:int, busy:bool}
	 *   busy: another pass holds the lock, this one did nothing.
	 */
	public static function run_queue( float $seconds = 0.0 ): array {
		$result = array(
			'moved'     => 0,
			'failed'    => 0,
			'remaining' => 0,
			'busy'      => false,
		);
		$lock   = Lock::acquire( self::LOCK, 10 * MINUTE_IN_SECONDS );
		if ( '' === $lock ) {
			$result['busy'] = true;
			return $result;
		}

		try {
			$queue = get_option( self::OPTION_QUEUE, array() );
			$ids   = 'all' === $queue ? self::all_candidates() : array_map( 'intval', (array) $queue );
			$until = microtime( true ) + ( $seconds > 0 ? $seconds : TimeBudget::seconds( 20.0 ) );

			while ( array() !== $ids && microtime( true ) < $until ) {
				$id   = (int) array_shift( $ids );
				$done = self::reconcile( $id );
				if ( true === $done ) {
					++$result['moved'];
				} elseif ( false === $done ) {
					++$result['failed'];
				}
			}

			$result['remaining'] = count( $ids );
			if ( array() === $ids ) {
				delete_option( self::OPTION_QUEUE );
			} else {
				update_option( self::OPTION_QUEUE, array_values( $ids ), false );
				wp_schedule_single_event( time() + 30, self::CRON_HOOK );
			}
		} finally {
			Lock::release( self::LOCK, $lock );
		}

		return $result;
	}

	/**
	 * Brings every protected file back to the normal folder. For the
	 * uninstall with "Remove everything": without the plugin the files would
	 * stay behind a rule that nothing answers.
	 *
	 * @return int Files moved back.
	 */
	public static function release_all(): int {
		global $wpdb;
		$like = $wpdb->esc_like( self::DIR . '/' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- once, at uninstall.
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s", $like ) );
		$moved = 0;
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			if ( self::move( $id, false ) ) {
				++$moved;
			}
		}
		return $moved;
	}

	/**
	 * Removes the cron event. For deactivation and uninstall.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
