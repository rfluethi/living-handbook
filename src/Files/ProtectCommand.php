<?php
/**
 * WP-CLI: wp living-handbook protect-files.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Files;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Moves every handbook file to where it belongs, from the command line.
 */
final class ProtectCommand {

	/**
	 * Moves the files of non-public handbooks into the protected folder, and
	 * the others out of it.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Lists the files that would move, and moves none.
	 *
	 * ## EXAMPLES
	 *
	 *     wp living-handbook protect-files --dry-run
	 *     wp living-handbook protect-files
	 *
	 * @param array<int, string>    $args       Positional arguments, unused.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI's signature.
		$misplaced = ProtectedFiles::misplaced();

		if ( isset( $assoc_args['dry-run'] ) ) {
			foreach ( $misplaced as $id ) {
				$parent = (int) wp_get_post_parent_id( $id );
				WP_CLI::log( sprintf( '%d %s %s', $id, ProtectedFiles::protects( $parent ) ? 'protect' : 'release', (string) get_post_meta( $id, '_wp_attached_file', true ) ) );
			}
			WP_CLI::success( sprintf( '%d file(s) would move.', count( $misplaced ) ) );
			return;
		}

		ServerRules::ensure();
		update_option( ProtectedFiles::OPTION_QUEUE, $misplaced, false );
		$moved  = 0;
		$failed = 0;
		do {
			$pass = ProtectedFiles::run_queue( 60.0 );
			if ( $pass['busy'] ) {
				WP_CLI::error( 'Another pass is moving files right now. Try again in a few minutes.' );
			}
			$moved  += $pass['moved'];
			$failed += $pass['failed'];
		} while ( $pass['remaining'] > 0 );
		ProtectedFiles::unschedule();

		if ( $failed > 0 ) {
			WP_CLI::warning( sprintf( '%d file(s) could not be moved.', $failed ) );
		}
		WP_CLI::success( sprintf( '%d file(s) moved.', $moved ) );
	}
}
