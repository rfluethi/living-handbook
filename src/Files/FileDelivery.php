<?php
/**
 * Hands out a protected file after the access check (E-0076, Konzept 10).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Files;

use LivingHandbook\Access\AccessController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers index.php?lh_file=YYYY/MM/name.ext.
 *
 * The server rule of the protected folder sends every request there to this
 * address. respond() decides what the answer is and emit() sends it; the split
 * is what lets the tests check every answer without a request ending the test.
 *
 * Who gets the file: whoever may read the page it belongs to
 * (AccessController::can_view_post(), and the page's password where it has
 * one), and whoever may edit the file. A guest who opens a protected file
 * directly in the browser is sent to the login; an image or a download
 * embedded in a page gets 403 instead, told apart by the Sec-Fetch-Dest
 * header the browser sends. A signed-in person without access gets 403.
 *
 * Path first: the requested name is normalised, may contain no "..", no
 * backslash and no null byte, must resolve inside the protected folder after
 * realpath(), and must belong to an attachment. Anything else is a 404, the
 * same answer as a missing file.
 */
final class FileDelivery {

	/**
	 * Query argument the server rule writes.
	 */
	public const QUERY_VAR = 'lh_file';

	/**
	 * Name of the self-test file in the protected folder.
	 */
	public const SELFTEST = 'lh-selftest.txt';

	/**
	 * Header that tells the self-test the answer came from the plugin.
	 */
	public const SELFTEST_HEADER = 'X-Living-Handbook-Protected';

	/**
	 * Read size when PHP sends the file.
	 */
	private const CHUNK = 1048576;

	/**
	 * Hooks the delivery.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'parse_request', array( $this, 'maybe_serve' ), 0 );
	}

	/**
	 * Serves a protected file when the request asks for one, and ends it.
	 *
	 * @return void
	 */
	public function maybe_serve(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a file request; the access check below is the gate.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}
		$headers = self::request_headers();
		$from    = self::from_uri( (string) ( $headers['REQUEST_URI'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised and confined in respond().
		$requested = null !== $from ? $from : ( is_string( $_GET[ self::QUERY_VAR ] ) ? (string) wp_unslash( $_GET[ self::QUERY_VAR ] ) : '' );

		self::emit( self::respond( $requested, get_current_user_id(), $headers ) );
		exit;
	}

	/**
	 * The file path from the address the browser asked for, when it lies in
	 * the protected folder.
	 *
	 * Preferred to the query variable: a server rule copies the path into the
	 * query unescaped (nginx does), so a name with "&" or "#" would arrive cut
	 * off. The address itself is what was asked for.
	 *
	 * @param string $uri Request URI.
	 * @return string|null Null when the address is not in the folder.
	 */
	public static function from_uri( string $uri ): ?string {
		$path   = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$prefix = (string) wp_parse_url( ProtectedFiles::folder()['url'], PHP_URL_PATH ) . '/';
		if ( '' === $path || '/' === $prefix || 0 !== strpos( $path, $prefix ) ) {
			return null;
		}
		return rawurldecode( substr( $path, strlen( $prefix ) ) );
	}

	/**
	 * The request headers this class reads.
	 *
	 * @return array<string, string>
	 */
	private static function request_headers(): array {
		$keys = array( 'HTTP_SEC_FETCH_DEST', 'HTTP_ACCEPT', 'HTTP_RANGE', 'HTTP_IF_NONE_MATCH', 'HTTP_IF_MODIFIED_SINCE', 'REQUEST_URI' );
		$out  = array();
		foreach ( $keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ) {
				// The address keeps its %-escapes, which sanitize_text_field()
				// would strip; it is only parsed and passed on as a URL.
				$out[ $key ] = 'REQUEST_URI' === $key
					? str_replace( array( "\r", "\n", "\0" ), '', (string) wp_unslash( $_SERVER[ $key ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above.
					: sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}
		return $out;
	}

	/**
	 * Decides the answer to a request for a protected file.
	 *
	 * @param string               $requested Path below the protected folder, as requested.
	 * @param int                  $user_id   Current user, 0 for a guest.
	 * @param array<string,string> $headers   Request headers, keyed like $_SERVER.
	 * @return array<string, mixed> Response: status, headers, and file, offset, length or redirect.
	 */
	public static function respond( string $requested, int $user_id, array $headers = array() ): array {
		$relative = self::normalise( $requested );
		if ( null === $relative ) {
			return self::status( 404 );
		}

		if ( self::SELFTEST === $relative ) {
			$answer                                     = self::status( 403 );
			$answer['headers'][ self::SELFTEST_HEADER ] = '1';
			return $answer;
		}

		$folder = ProtectedFiles::folder();
		$base   = realpath( $folder['path'] );
		$real   = realpath( $folder['path'] . '/' . $relative );
		if ( false === $base || false === $real || 0 !== strpos( $real, $base . DIRECTORY_SEPARATOR ) || ! is_file( $real ) ) {
			return self::status( 404 );
		}

		$attachment_id = self::attachment_for( $relative );
		if ( $attachment_id <= 0 ) {
			return self::status( 404 );
		}

		if ( ! self::may_read( $attachment_id, $user_id ) ) {
			if ( 0 === $user_id && self::is_navigation( $headers ) ) {
				$uri = (string) ( $headers['REQUEST_URI'] ?? '' );
				return array(
					'status'   => 302,
					'headers'  => array( 'Cache-Control' => 'no-store' ),
					'redirect' => wp_login_url( '' !== $uri ? home_url( $uri ) : '' ),
				);
			}
			return self::status( 403 );
		}

		return self::file_response( $real, $headers );
	}

	/**
	 * The requested path, cleaned, or null when it is not one this class
	 * answers.
	 *
	 * @param string $requested As requested.
	 * @return string|null
	 */
	public static function normalise( string $requested ): ?string {
		if ( '' === $requested || false !== strpos( $requested, "\0" ) || false !== strpos( $requested, '\\' ) ) {
			return null;
		}
		$path = ltrim( (string) preg_replace( '#/+#', '/', $requested ), '/' );
		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || '.' === $part || '..' === $part ) {
				return null;
			}
		}
		return $path;
	}

	/**
	 * The attachment a file belongs to: its main file, a size or the
	 * original of a scaled image.
	 *
	 * @param string $relative Path below the protected folder.
	 * @return int 0 when none.
	 */
	public static function attachment_for( string $relative ): int {
		global $wpdb;

		$stored = ProtectedFiles::DIR . '/' . $relative;
		$cached = wp_cache_get( $stored, 'living_handbook_files' );
		if ( is_int( $cached ) ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- the one way from a file to its attachment; cached above.
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1", $stored ) );

		if ( $id <= 0 ) {
			$name = basename( $relative );
			$dir  = dirname( $stored );
			$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s", $wpdb->esc_like( $dir . '/' ) . '%' ) );
			foreach ( array_map( 'intval', (array) $ids ) as $candidate ) {
				if ( dirname( (string) get_post_meta( $candidate, '_wp_attached_file', true ) ) !== $dir ) {
					continue;
				}
				/**
				 * The metadata as a plain array, so a missing key reads as empty.
				 *
				 * @var array<string, mixed> $meta
				 */
				$meta  = (array) wp_get_attachment_metadata( $candidate, true );
				$names = array_map( static fn( $size ) => is_array( $size ) ? basename( (string) ( $size['file'] ?? '' ) ) : '', (array) ( $meta['sizes'] ?? array() ) );
				if ( ! empty( $meta['original_image'] ) ) {
					$names[] = basename( (string) $meta['original_image'] );
				}
				if ( in_array( $name, $names, true ) ) {
					$id = $candidate;
					break;
				}
			}
		}
		// phpcs:enable

		wp_cache_set( $stored, $id, 'living_handbook_files', HOUR_IN_SECONDS );
		return $id;
	}

	/**
	 * Whether a person may have an attachment's file.
	 *
	 * @param int $attachment_id Attachment.
	 * @param int $user_id       Person, 0 for a guest.
	 * @return bool
	 */
	public static function may_read( int $attachment_id, int $user_id ): bool {
		if ( $user_id > 0 && user_can( $user_id, 'edit_post', $attachment_id ) ) {
			return true;
		}

		$parent = (int) wp_get_post_parent_id( $attachment_id );

		// A file whose page no longer needs protection is on its way out of the
		// folder (the queue moves it); until then it is served like any other.
		if ( ! ProtectedFiles::protects( $parent ) ) {
			return true;
		}

		if ( $parent <= 0 || ! AccessController::can_view_post( $parent, $user_id ) ) {
			return false;
		}

		// A draft, a scheduled or a private page shows its files to whoever
		// may see the page itself, as core decides for the page.
		if ( 'publish' !== get_post_status( $parent ) && ( $user_id <= 0 || ! user_can( $user_id, 'read_post', $parent ) ) ) {
			return false;
		}

		return ! post_password_required( $parent );
	}

	/**
	 * Whether the browser is opening the file itself, rather than embedding it.
	 *
	 * @param array<string,string> $headers Request headers.
	 * @return bool
	 */
	private static function is_navigation( array $headers ): bool {
		$dest = strtolower( (string) ( $headers['HTTP_SEC_FETCH_DEST'] ?? '' ) );
		if ( '' !== $dest ) {
			return 'document' === $dest;
		}
		// An older browser without Fetch Metadata: an address typed or clicked
		// asks for HTML first, an image tag does not.
		return false !== strpos( (string) ( $headers['HTTP_ACCEPT'] ?? '' ), 'text/html' );
	}

	/**
	 * A response without a file.
	 *
	 * @param int $status HTTP status.
	 * @return array<string, mixed>
	 */
	private static function status( int $status ): array {
		return array(
			'status'  => $status,
			'headers' => array(
				'Cache-Control'          => 'no-store',
				'X-Content-Type-Options' => 'nosniff',
			),
		);
	}

	/**
	 * The response that sends a file, whole or in part, or 304.
	 *
	 * @param string               $real    Resolved path.
	 * @param array<string,string> $headers Request headers.
	 * @return array<string, mixed>
	 */
	private static function file_response( string $real, array $headers ): array {
		$size  = (int) filesize( $real );
		$mtime = (int) filemtime( $real );
		$etag  = '"' . md5( $real . '|' . $size . '|' . $mtime ) . '"';
		$type  = wp_check_filetype( $real );
		$mime  = is_string( $type['type'] ) && '' !== $type['type'] ? $type['type'] : 'application/octet-stream';
		$name  = basename( $real );

		$out = array(
			'Content-Type'           => $mime,
			'X-Content-Type-Options' => 'nosniff',
			// Private: a shared cache must not hand it to the next person; the
			// browser keeps it and asks again with the ETag.
			'Cache-Control'          => 'private, max-age=0, must-revalidate',
			'Last-Modified'          => gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT',
			'ETag'                   => $etag,
			'Accept-Ranges'          => 'bytes',
			'Content-Disposition'    => ( self::inline( $mime ) ? 'inline' : 'attachment' ) . '; filename="' . str_replace( array( '"', "\r", "\n" ), '', $name ) . '"',
		);
		// Markup a browser would run: an SVG or HTML file is shown without
		// scripts, plugins or anything it could load.
		if ( in_array( $mime, array( 'image/svg+xml', 'text/html', 'application/xhtml+xml', 'text/xml', 'application/xml' ), true ) ) {
			$out['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox";
		}

		$match = (string) ( $headers['HTTP_IF_NONE_MATCH'] ?? '' );
		$since = (string) ( $headers['HTTP_IF_MODIFIED_SINCE'] ?? '' );
		if ( ( '' !== $match && in_array( $etag, array_map( 'trim', explode( ',', $match ) ), true ) )
			|| ( '' === $match && '' !== $since && false !== strtotime( $since ) && strtotime( $since ) >= $mtime ) ) {
			return array(
				'status'  => 304,
				'headers' => array_intersect_key( $out, array_flip( array( 'Cache-Control', 'ETag', 'Last-Modified' ) ) ),
			);
		}

		$start  = 0;
		$length = $size;
		$status = 200;
		$range  = (string) ( $headers['HTTP_RANGE'] ?? '' );
		if ( '' !== $range ) {
			if ( 1 !== preg_match( '/^bytes=(\d*)-(\d*)$/', trim( $range ), $m ) || ( '' === $m[1] && '' === $m[2] ) ) {
				return self::unsatisfiable( $size );
			}
			if ( '' === $m[1] ) {
				$start = max( 0, $size - (int) $m[2] );
				$end   = $size - 1;
			} else {
				$start = (int) $m[1];
				$end   = '' === $m[2] ? $size - 1 : min( (int) $m[2], $size - 1 );
			}
			if ( $start >= $size || $start > $end ) {
				return self::unsatisfiable( $size );
			}
			$length               = $end - $start + 1;
			$status               = 206;
			$out['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $size;
		}
		$out['Content-Length'] = (string) $length;

		$response = array(
			'status'  => $status,
			'headers' => $out,
			'file'    => $real,
			'offset'  => $start,
			'length'  => $length,
		);

		return 200 === $status ? self::with_handoff( $response, $real ) : $response;
	}

	/**
	 * Answer 416 to a range that does not fit the file.
	 *
	 * @param int $size File size.
	 * @return array<string, mixed>
	 */
	private static function unsatisfiable( int $size ): array {
		$answer                             = self::status( 416 );
		$answer['headers']['Content-Range'] = 'bytes */' . $size;
		return $answer;
	}

	/**
	 * Whether a type is shown in the browser rather than downloaded.
	 *
	 * @param string $mime Type.
	 * @return bool
	 */
	private static function inline( string $mime ): bool {
		return 0 === strpos( $mime, 'image/' ) || 0 === strpos( $mime, 'video/' ) || 0 === strpos( $mime, 'audio/' ) || 'application/pdf' === $mime || 'text/plain' === $mime;
	}

	/**
	 * Lets the web server send the file, where the site has set that up.
	 *
	 * Off unless a filter names the way, because a header the server does not
	 * understand is a broken file: the plugin cannot see from inside PHP
	 * whether nginx has the internal location, or Apache mod_xsendfile.
	 *
	 * @param array<string, mixed> $response The response PHP would send.
	 * @param string               $real     Resolved path.
	 * @return array<string, mixed>
	 */
	private static function with_handoff( array $response, string $real ): array {
		/**
		 * Filters how the web server takes over sending a protected file.
		 *
		 * '' (default): PHP reads the file. 'x-accel-redirect': nginx, with an
		 * internal location (see living_handbook_protected_files_internal_path).
		 * 'x-sendfile': Apache with mod_xsendfile. 'x-litespeed-location':
		 * LiteSpeed.
		 *
		 * @param string $way The way, or ''.
		 */
		$way = (string) apply_filters( 'living_handbook_protected_files_handoff', '' );

		$folder   = ProtectedFiles::folder();
		$relative = ltrim( substr( $real, strlen( (string) realpath( $folder['path'] ) ) ), '/\\' );

		switch ( $way ) {
			case 'x-accel-redirect':
				$response['headers']['X-Accel-Redirect'] = ServerRules::internal_path() . str_replace( '%2F', '/', rawurlencode( $relative ) );
				break;
			case 'x-sendfile':
				$response['headers']['X-Sendfile'] = $real;
				break;
			case 'x-litespeed-location':
				$response['headers']['X-LiteSpeed-Location'] = (string) wp_parse_url( $folder['url'], PHP_URL_PATH ) . '/' . str_replace( '%2F', '/', rawurlencode( $relative ) );
				break;
			default:
				return $response;
		}

		unset( $response['headers']['Content-Length'] );
		$response['file'] = '';
		return $response;
	}

	/**
	 * Sends a response from respond().
	 *
	 * @param array<string, mixed> $response Response.
	 * @return void
	 */
	public static function emit( array $response ): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		status_header( (int) $response['status'] );
		foreach ( (array) $response['headers'] as $name => $value ) {
			header( $name . ': ' . $value );
		}

		if ( ! empty( $response['redirect'] ) ) {
			header( 'Location: ' . esc_url_raw( (string) $response['redirect'] ) );
			return;
		}
		if ( empty( $response['file'] ) ) {
			return;
		}

		$handle = fopen( (string) $response['file'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a file in blocks; WP_Filesystem reads whole files.
		if ( false === $handle ) {
			return;
		}
		fseek( $handle, (int) $response['offset'] );
		$left = (int) $response['length'];
		while ( $left > 0 && ! feof( $handle ) ) {
			$block = fread( $handle, min( self::CHUNK, $left ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- see above.
			if ( false === $block ) {
				break;
			}
			echo $block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the file itself, sent as its own type.
			$left -= strlen( $block );
			flush();
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see above.
	}
}
