<?php
/**
 * The file protection on the Access tab of the settings page.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Files;

use LivingHandbook\PostType\Handbook;
use LivingHandbook\Setup\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows whether the protection holds, and lets an administrator test it and
 * move the files that wait.
 */
final class ProtectionAdmin {

	/**
	 * The admin-post action of the self-test.
	 */
	public const ACTION_TEST = 'living_handbook_protect_selftest';

	/**
	 * The admin-post action of "Move now".
	 */
	public const ACTION_MOVE = 'living_handbook_protect_now';

	/**
	 * Hooks the two actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_TEST, array( $this, 'handle_test' ) );
		add_action( 'admin_post_' . self::ACTION_MOVE, array( $this, 'handle_move' ) );
	}

	/**
	 * Runs the self-test and returns to the tab.
	 *
	 * @return void
	 */
	public function handle_test(): void {
		check_admin_referer( self::ACTION_TEST );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'living-handbook' ) );
		}
		ServerRules::selftest();
		$this->back();
	}

	/**
	 * Moves files for one pass and returns to the tab.
	 *
	 * @return void
	 */
	public function handle_move(): void {
		check_admin_referer( self::ACTION_MOVE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'living-handbook' ) );
		}
		if ( ! is_array( get_option( ProtectedFiles::OPTION_QUEUE ) ) && 'all' !== get_option( ProtectedFiles::OPTION_QUEUE ) ) {
			update_option( ProtectedFiles::OPTION_QUEUE, 'all', false );
		}
		ProtectedFiles::run_queue();
		$this->back();
	}

	/**
	 * Back to the Access tab.
	 *
	 * @return void
	 */
	private function back(): void {
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Handbook::POST_TYPE . '&page=' . Settings::PAGE_SLUG . '&tab=access' ) );
		exit;
	}

	/**
	 * The field on the Access tab.
	 *
	 * The two buttons sit inside the settings form, so they send it elsewhere
	 * with formaction rather than nesting a form.
	 *
	 * @return void
	 */
	public static function render_field(): void {
		$last      = ServerRules::last();
		$misplaced = count( ProtectedFiles::misplaced() );
		$server    = ServerRules::server();

		switch ( $last['result'] ) {
			case 'protected':
				$class = 'notice-success';
				$text  = __( 'The protection holds: a file in the protected folder was refused to a visitor who is not signed in.', 'living-handbook' );
				break;
			case 'open':
				$class = 'notice-error';
				$text  = __( 'The protection does not hold: the protected folder hands out its files to anyone. Add the rule below to the server, or ask your host to.', 'living-handbook' );
				break;
			case 'locked':
				$class = 'notice-error';
				$text  = __( 'The folder is locked, but the files do not reach anyone: the server refuses them before WordPress sees the request. On Apache this means mod_rewrite is missing or the .htaccess may not use it (AllowOverride).', 'living-handbook' );
				break;
			case 'unknown':
				$class = 'notice-warning';
				/* translators: %s: error message of the request. */
				$text = sprintf( __( 'The test could not reach the site: %s. Until it passes, treat the protection as not set up.', 'living-handbook' ), $last['detail'] );
				break;
			default:
				$class = 'notice-warning';
				$text  = __( 'Not tested yet. Until the test passes, treat the protection as not set up.', 'living-handbook' );
		}

		echo '<div class="notice inline ' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
		if ( $last['time'] > 0 ) {
			/* translators: %s: date and time of the last test. */
			echo '<p class="description">' . esc_html( sprintf( __( 'Last tested: %s', 'living-handbook' ), wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $last['time'] ) ) ) . '</p>';
		}

		if ( self::offloaded() ) {
			echo '<div class="notice inline notice-warning"><p>' . esc_html__( 'The uploads are served from another address than the site, probably by an offload plugin or a CDN. Their copies are not protected by this folder.', 'living-handbook' ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Files of pages in handbooks that are not public live in a protected folder, and the plugin hands them out only to whoever may read the page. Public files stay where they are.', 'living-handbook' ) . '</p>';

		$test = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_TEST ), self::ACTION_TEST );
		echo '<p><button type="submit" class="button" formaction="' . esc_url( $test ) . '" formmethod="post">' . esc_html__( 'Test the protection', 'living-handbook' ) . '</button></p>';

		if ( $misplaced > 0 ) {
			$move = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_MOVE ), self::ACTION_MOVE );
			/* translators: %d: number of files that wait to be moved. */
			echo '<p>' . esc_html( sprintf( _n( '%d file waits to be moved. This happens in the background; you can also move now.', '%d files wait to be moved. This happens in the background; you can also move now.', $misplaced, 'living-handbook' ), $misplaced ) ) . '</p>';
			echo '<p><button type="submit" class="button" formaction="' . esc_url( $move ) . '" formmethod="post">' . esc_html__( 'Move now', 'living-handbook' ) . '</button></p>';
		}

		if ( 'nginx' === $server || 'open' === $last['result'] ) {
			echo '<p>' . esc_html__( 'nginx reads no .htaccess. Add this to the server block of the site:', 'living-handbook' ) . '</p>';
			echo '<textarea class="large-text code" rows="9" readonly>' . esc_textarea( ServerRules::nginx() ) . '</textarea>';
		}
		echo '<p class="description">' . esc_html__( 'A protected file shown on a public page appears there only to whoever may read its handbook.', 'living-handbook' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Deactivating the plugin keeps these files locked: the rule stays, and without the plugin nothing answers it. Deleting the plugin with "Also delete all handbook pages, handbooks and their data" ticked moves them back first.', 'living-handbook' ) . '</p>';
	}

	/**
	 * Whether the uploads are served from another host than the site.
	 *
	 * @return bool
	 */
	public static function offloaded(): bool {
		$uploads = wp_get_upload_dir();
		$files   = strtolower( (string) wp_parse_url( (string) $uploads['baseurl'], PHP_URL_HOST ) );
		$site    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return '' !== $files && '' !== $site && $files !== $site;
	}
}
