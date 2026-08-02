<?php
/**
 * A repository archive as an import source.
 *
 * Importing a folder used to mean one HTTP request per Markdown file plus one
 * per referenced image. A folder with 200 pages and 300 images is 500 requests,
 * which runs into the GitHub rate limit (60 per hour without authentication)
 * long before it finishes, and every request is a chance for the run to die
 * halfway. GitHub also offers the whole repository as one archive, so the same
 * import becomes a single request whose content is, by definition, one
 * consistent commit.
 *
 * The archive is downloaded to a temporary file, never extracted to disk, and
 * read entry by entry through ZipArchive. That is the same approach the ZIP
 * import already uses, and it rules out path traversal structurally: no archive
 * path is ever used as a file system path.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Git;

use WP_Error;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Downloads a repository archive and serves the files inside it.
 */
final class ArchiveSource {

	/**
	 * Host that serves the archive after GitHub redirects to it.
	 *
	 * The API endpoint answers with a 302 to this host. The redirect is followed
	 * by hand rather than by the HTTP client, so the target host is checked
	 * against this list instead of being trusted.
	 */
	private const ARCHIVE_HOSTS = array( 'codeload.github.com' );

	/**
	 * Largest archive that is downloaded, in bytes.
	 */
	private const MAX_ARCHIVE_BYTES = 52428800; // 50 MB.

	/**
	 * Most entries an archive may hold.
	 */
	private const MAX_ENTRIES = 5000;

	/**
	 * Largest single file that is read out of the archive, in bytes.
	 */
	private const MAX_FILE_BYTES = 5242880; // 5 MB.

	/**
	 * Path of the downloaded archive on disk.
	 *
	 * @var string
	 */
	private string $path = '';

	/**
	 * The open archive.
	 *
	 * @var ZipArchive|null
	 */
	private ?ZipArchive $zip = null;

	/**
	 * Entry names inside the archive, without the leading repository folder.
	 *
	 * Maps the cleaned relative path to its index in the archive.
	 *
	 * @var array<string, int>
	 */
	private array $entries = array();

	/**
	 * Download the archive of a repository and open it.
	 *
	 * @param string $owner  Repository owner.
	 * @param string $repo   Repository name.
	 * @param string $branch Branch, tag or commit.
	 * @return true|WP_Error True when the archive is open and readable.
	 */
	public function open( string $owner, string $repo, string $branch ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'living_handbook_import',
				__( 'ZipArchive is not available on the server.', 'living-handbook' ),
				array( 'status' => 501 )
			);
		}

		$location = $this->resolve_archive_url( $owner, $repo, $branch );
		if ( $location instanceof WP_Error ) {
			return $location;
		}

		$file = $this->download( $location );
		if ( $file instanceof WP_Error ) {
			return $file;
		}

		return $this->read_entries();
	}

	/**
	 * Ask the API where the archive lives, without following the redirect.
	 *
	 * @param string $owner  Repository owner.
	 * @param string $repo   Repository name.
	 * @param string $branch Branch, tag or commit.
	 * @return string|WP_Error The archive URL.
	 */
	private function resolve_archive_url( string $owner, string $repo, string $branch ) {
		$api = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo )
			. '/zipball/' . rawurlencode( $branch );

		$response = wp_safe_remote_get(
			$api,
			array(
				'timeout'     => 20,
				// Not followed by the client: the redirect target is checked here.
				'redirection' => 0,
				'headers'     => array(
					'User-Agent' => 'LivingHandbook',
					'Accept'     => 'application/vnd.github+json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'living_handbook_import', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 403 === $code && '0' === (string) wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' ) ) {
			return new WP_Error(
				'living_handbook_import',
				__( 'GitHub API rate limit reached (unauthenticated, 60 requests per hour). Try again later.', 'living-handbook' ),
				array( 'status' => 429 )
			);
		}

		if ( 200 === $code ) {
			// Some setups hand the archive straight back instead of redirecting.
			return $api;
		}

		if ( 301 !== $code && 302 !== $code && 307 !== $code ) {
			return new WP_Error(
				'living_handbook_import',
				/* translators: %d: HTTP status code returned by the GitHub API. */
				sprintf( __( 'GitHub API HTTP %d', 'living-handbook' ), $code ),
				array( 'status' => 502 )
			);
		}

		$location = (string) wp_remote_retrieve_header( $response, 'location' );
		if ( '' === $location || ! self::is_allowed_archive_url( $location ) ) {
			return new WP_Error(
				'living_handbook_import',
				__( 'The archive download was redirected to an unexpected address.', 'living-handbook' ),
				array( 'status' => 502 )
			);
		}

		return $location;
	}

	/**
	 * Whether an archive URL may be fetched.
	 *
	 * Deliberately separate from the Markdown source check in GitSync: this list
	 * exists only for the archive download, so widening it cannot widen where
	 * ordinary page sources may come from.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	public static function is_allowed_archive_url( string $url ): bool {
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return false;
		}

		/**
		 * Filter the hosts a repository archive may be downloaded from.
		 *
		 * A host added here is trusted to serve archive content. It does not
		 * become an allowed source for individual pages, that list is separate.
		 *
		 * @param string[] $hosts Allowed host names.
		 */
		$allowed = apply_filters( 'living_handbook_archive_allowed_hosts', array_merge( self::ARCHIVE_HOSTS, array( 'api.github.com' ) ) );

		return in_array( strtolower( $host ), array_map( 'strtolower', (array) $allowed ), true );
	}

	/**
	 * Download the archive into a temporary file.
	 *
	 * The response is streamed to disk rather than held in memory: an archive is
	 * megabytes, and a 50 MB string would be a memory limit away from failing.
	 *
	 * @param string $url Archive URL.
	 * @return true|WP_Error
	 */
	private function download( string $url ) {
		$tmp = tempnam( sys_get_temp_dir(), 'lh-archive' );
		if ( ! is_string( $tmp ) ) {
			// The system temp directory is not writable: fall back to the
			// WordPress temp file, which lives in uploads.
			$tmp = wp_tempnam( 'lh-archive' );
		}

		/**
		 * Filter the largest repository archive that is downloaded, in bytes.
		 *
		 * @param int $bytes Maximum size.
		 */
		$max = (int) apply_filters( 'living_handbook_archive_max_bytes', self::MAX_ARCHIVE_BYTES );

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 60,
				'redirection'         => 0,
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => $max,
				'headers'             => array( 'User-Agent' => 'LivingHandbook' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'living_handbook_import', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			wp_delete_file( $tmp );
			return new WP_Error(
				'living_handbook_import',
				/* translators: %d: HTTP status code returned while downloading the archive. */
				sprintf( __( 'Archive download failed with HTTP %d', 'living-handbook' ), $code ),
				array( 'status' => 502 )
			);
		}

		$size = (int) @filesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A missing file is handled by the check below.
		if ( $size <= 0 ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'living_handbook_import', __( 'The downloaded archive is empty.', 'living-handbook' ), array( 'status' => 502 ) );
		}
		if ( $size > $max ) {
			wp_delete_file( $tmp );
			return new WP_Error(
				'living_handbook_import',
				__( 'The repository archive is larger than the configured limit.', 'living-handbook' ),
				array( 'status' => 413 )
			);
		}

		$this->path = $tmp;
		return true;
	}

	/**
	 * Open the downloaded archive and index its entries.
	 *
	 * GitHub wraps everything in one folder named after the repository and the
	 * commit. That prefix is stripped, so callers work with paths as they appear
	 * in the repository.
	 *
	 * @return true|WP_Error
	 */
	private function read_entries() {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $this->path ) ) {
			$this->close();
			return new WP_Error( 'living_handbook_import', __( 'Could not open the repository archive.', 'living-handbook' ), array( 'status' => 502 ) );
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- numFiles is a property of PHP's ZipArchive, not ours to rename.
		if ( $zip->numFiles > self::MAX_ENTRIES ) {
			$zip->close();
			$this->close();
			return new WP_Error(
				'living_handbook_import',
				__( 'The repository archive holds more entries than the import allows.', 'living-handbook' ),
				array( 'status' => 413 )
			);
		}

		$prefix = '';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- numFiles is a property of PHP's ZipArchive, not ours to rename.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( '' === $name ) {
				continue;
			}
			if ( '' === $prefix ) {
				$slash = strpos( $name, '/' );
				if ( false !== $slash ) {
					$prefix = substr( $name, 0, $slash + 1 );
				}
			}
			if ( '' !== $prefix && 0 === strpos( $name, $prefix ) ) {
				$relative = substr( $name, strlen( $prefix ) );
			} else {
				$relative = $name;
			}
			if ( '' === $relative || '/' === substr( $relative, -1 ) ) {
				continue;
			}
			$this->entries[ $relative ] = $i;
		}

		$this->zip = $zip;
		return true;
	}

	/**
	 * All file paths in the archive under a folder, relative to the repository
	 * root, sorted so an import runs in a predictable order.
	 *
	 * @param string $folder Folder path, empty for the whole repository.
	 * @param string $suffix Optional file name suffix filter, for example '.md'.
	 * @return array<int, string>
	 */
	public function files_under( string $folder, string $suffix = '' ): array {
		$folder = trim( $folder, '/' );
		$prefix = '' === $folder ? '' : $folder . '/';
		$found  = array();

		foreach ( array_keys( $this->entries ) as $path ) {
			if ( '' !== $prefix && 0 !== strpos( $path, $prefix ) ) {
				continue;
			}
			if ( '' !== $suffix && substr( strtolower( $path ), -strlen( $suffix ) ) !== strtolower( $suffix ) ) {
				continue;
			}
			$found[] = $path;
		}

		sort( $found );
		return $found;
	}

	/**
	 * Read one file out of the archive.
	 *
	 * @param string $path Path relative to the repository root.
	 * @return string|null The file contents, or null when it is missing or too large.
	 */
	public function contents( string $path ): ?string {
		if ( ! $this->zip instanceof ZipArchive || ! isset( $this->entries[ $path ] ) ) {
			return null;
		}

		$index = $this->entries[ $path ];
		$stat = $this->zip->statIndex( $index );
		if ( ! is_array( $stat ) || (int) $stat['size'] > self::MAX_FILE_BYTES ) {
			return null;
		}

		$data = $this->zip->getFromIndex( $index );
		return is_string( $data ) ? $data : null;
	}

	/**
	 * Whether the archive holds a given file.
	 *
	 * @param string $path Path relative to the repository root.
	 * @return bool
	 */
	public function has( string $path ): bool {
		return isset( $this->entries[ $path ] );
	}

	/**
	 * Close the archive and delete the downloaded file.
	 *
	 * @return void
	 */
	public function close(): void {
		if ( $this->zip instanceof ZipArchive ) {
			$this->zip->close();
			$this->zip = null;
		}
		if ( '' !== $this->path && file_exists( $this->path ) ) {
			wp_delete_file( $this->path );
		}
		$this->path    = '';
		$this->entries = array();
	}
}
