<?php
/**
 * Temporary files the plugin leaves in the temp folder on purpose.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects the plugin's own temporary files once nobody can want them any more.
 *
 * Three jobs write a file that outlives the request which made it: the folder
 * import keeps the downloaded repository archive for its next pass, and the
 * static website export and the bundle export keep the ZIP until the browser
 * fetches it. Each deletes its file at the end of the happy path. When the end
 * never comes, because the tab was closed or the download broke off, the file
 * stayed for good: the only cleanup ran with the scheduled sync, and a site with
 * the sync switched off had none at all.
 *
 * Only files whose name starts with one of the plugin's prefixes are touched,
 * and only once they are older than any job could still be running.
 */
final class TempFiles {

	/**
	 * Deletes files with the given name prefix that are older than the cutoff.
	 *
	 * Looks in the system temp folder, where the files are written, and in the
	 * WordPress temp folder, where wp_tempnam() puts them when the system one is
	 * not writable.
	 *
	 * @param string $prefix     File name prefix, for example "lh-site".
	 * @param int    $older_than Age in seconds from which a file is collected.
	 * @return int How many files were deleted.
	 */
	public static function sweep( string $prefix, int $older_than ): int {
		if ( '' === $prefix || 1 !== preg_match( '/^lh-[a-z]+$/', $prefix ) ) {
			return 0;
		}

		$folders = array( rtrim( sys_get_temp_dir(), '/\\' ) );
		if ( function_exists( 'get_temp_dir' ) ) {
			$folders[] = rtrim( get_temp_dir(), '/\\' );
		}

		$cutoff  = time() - max( 0, $older_than );
		$deleted = 0;
		foreach ( array_unique( $folders ) as $folder ) {
			$found = glob( $folder . '/' . $prefix . '*' );
			if ( ! is_array( $found ) ) {
				continue;
			}
			foreach ( $found as $file ) {
				if ( ! is_file( $file ) ) {
					continue;
				}
				$age = (int) @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A file that vanished between glob and here is simply skipped.
				if ( $age > 0 && $age <= $cutoff ) {
					wp_delete_file( $file );
					++$deleted;
				}
			}
		}
		return $deleted;
	}
}
