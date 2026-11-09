<?php
/**
 * The server rule of the protected folder, and the test that it holds.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Files;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends requests for the protected folder to WordPress.
 *
 * Apache and LiteSpeed read the .htaccess this class writes into the folder;
 * nothing has to be done by hand where the server allows .htaccess files
 * (AllowOverride). nginx reads no .htaccess: its rule has to go into the
 * server configuration, and the settings page shows it ready to copy.
 *
 * Whether the rule holds is not assumed. selftest() asks for a file in the
 * folder without signing in, the way a stranger would. When the plugin
 * answers, the rule holds; when the file itself comes back, it does not, and
 * the settings page says so in red with the rule for the server it found.
 */
final class ServerRules {

	/**
	 * Option: the last self-test, {result, server, time, detail}.
	 */
	public const OPTION_SELFTEST = 'living_handbook_protect_selftest';

	/**
	 * Marker in the .htaccess, so an outdated one is recognised and rewritten.
	 */
	private const MARKER = '# Living Handbook protected files, rule 1';

	/**
	 * Creates the folder, its rule, an empty index and the self-test file,
	 * where they are missing or outdated.
	 *
	 * @return bool Whether the folder is usable.
	 */
	public static function ensure(): bool {
		$folder = ProtectedFiles::folder();
		$path   = $folder['path'];
		if ( ! wp_mkdir_p( $path ) ) {
			return false;
		}

		$files = array(
			'.htaccess'            => self::htaccess(),
			'index.php'            => "<?php\n// Silence is golden.\n",
			FileDelivery::SELFTEST => self::selftest_body(),
		);
		foreach ( $files as $name => $content ) {
			$file = $path . '/' . $name;
			if ( ! is_file( $file ) || (string) file_get_contents( $file ) !== $content ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file of the plugin's own.
				file_put_contents( $file, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- the plugin's own rule files in the uploads folder.
			}
		}
		return true;
	}

	/**
	 * The .htaccess of the protected folder, for Apache and LiteSpeed.
	 *
	 * The rule sends every request to the site's index.php with the path
	 * below the folder. Without mod_rewrite the folder is closed altogether,
	 * so a server that cannot rewrite fails closed rather than open.
	 *
	 * @return string
	 */
	public static function htaccess(): string {
		$index = self::index_path();
		return self::MARKER . "\n"
			. "Options -Indexes\n"
			. "<IfModule mod_rewrite.c>\n"
			. "RewriteEngine On\n"
			. 'RewriteRule ^(.*)$ ' . $index . '?' . FileDelivery::QUERY_VAR . "=$1 [L,QSA,B]\n"
			. "</IfModule>\n"
			. "<IfModule !mod_rewrite.c>\n"
			. "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
			. "</IfModule>\n";
	}

	/**
	 * The nginx rule, for the server block of the site.
	 *
	 * The second location is only needed with the X-Accel-Redirect handoff
	 * (filter living_handbook_protected_files_handoff); without it PHP sends
	 * the file.
	 *
	 * @return string
	 */
	public static function nginx(): string {
		$folder = ProtectedFiles::folder();
		$url    = untrailingslashit( (string) wp_parse_url( $folder['url'], PHP_URL_PATH ) );
		$index  = self::index_path();

		return "# Living Handbook: protected files\n"
			. 'location ^~ ' . $url . "/ {\n"
			. '    rewrite ^' . preg_quote( $url, '#' ) . '/(.*)$ ' . $index . '?' . FileDelivery::QUERY_VAR . "=\$1 last;\n"
			. "}\n"
			. "# Only with the X-Accel-Redirect handoff:\n"
			. "location /lh-protected-internal/ {\n"
			. "    internal;\n"
			. '    alias ' . trailingslashit( $folder['path'] ) . ";\n"
			. "}\n";
	}

	/**
	 * The address path of the site's index.php.
	 *
	 * @return string
	 */
	private static function index_path(): string {
		$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return trailingslashit( '' !== $path ? $path : '/' ) . 'index.php';
	}

	/**
	 * The content of the self-test file: a value no other answer contains.
	 *
	 * @return string
	 */
	private static function selftest_body(): string {
		return 'living-handbook-selftest-' . substr( md5( (string) wp_salt( 'auth' ) ), 0, 16 ) . "\n";
	}

	/**
	 * The web server, as far as PHP can tell.
	 *
	 * @return string apache, nginx, litespeed or unknown.
	 */
	public static function server(): string {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) && is_string( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';
		foreach ( array( 'litespeed', 'nginx', 'apache' ) as $name ) {
			if ( false !== strpos( $software, $name ) ) {
				return $name;
			}
		}
		return 'unknown';
	}

	/**
	 * Asks for the self-test file without signing in and records the answer.
	 *
	 * @return array{result:string, server:string, time:int, detail:string}
	 *   result: protected (the plugin answered), open (the file came back),
	 *   locked (the server refused before WordPress saw the request),
	 *   or unknown (no answer, for instance where the site cannot reach itself).
	 */
	public static function selftest(): array {
		self::ensure();
		$folder   = ProtectedFiles::folder();
		$response = wp_remote_get(
			$folder['url'] . '/' . FileDelivery::SELFTEST,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'cookies'     => array(),
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter for requests to the site itself.
			)
		);

		$result = array(
			'result' => 'unknown',
			'server' => self::server(),
			'time'   => time(),
			'detail' => '',
		);
		if ( is_wp_error( $response ) ) {
			$result['detail'] = $response->get_error_message();
		} elseif ( '' !== (string) wp_remote_retrieve_header( $response, FileDelivery::SELFTEST_HEADER ) ) {
			$result['result'] = 'protected';
		} elseif ( false !== strpos( (string) wp_remote_retrieve_body( $response ), trim( self::selftest_body() ) ) ) {
			$result['result'] = 'open';
		} elseif ( 403 === (int) wp_remote_retrieve_response_code( $response ) ) {
			// Refused, but not by the plugin: the server locks the folder
			// (Apache without mod_rewrite). Safe, but nobody gets a file.
			$result['result'] = 'locked';
		} else {
			$result['detail'] = 'HTTP ' . (int) wp_remote_retrieve_response_code( $response );
		}

		update_option( self::OPTION_SELFTEST, $result, false );
		return $result;
	}

	/**
	 * The last self-test, or an empty one.
	 *
	 * @return array{result:string, server:string, time:int, detail:string}
	 */
	public static function last(): array {
		$stored = get_option( self::OPTION_SELFTEST, array() );
		return array(
			'result' => (string) ( $stored['result'] ?? '' ),
			'server' => (string) ( $stored['server'] ?? self::server() ),
			'time'   => (int) ( $stored['time'] ?? 0 ),
			'detail' => (string) ( $stored['detail'] ?? '' ),
		);
	}

	/**
	 * Removes the rule files and the folder when it is empty. For the
	 * uninstall, after the files went back.
	 *
	 * @return void
	 */
	public static function remove(): void {
		$path = ProtectedFiles::folder()['path'];
		foreach ( array( '.htaccess', 'index.php', FileDelivery::SELFTEST ) as $name ) {
			if ( is_file( $path . '/' . $name ) ) {
				wp_delete_file( $path . '/' . $name );
			}
		}
		self::remove_empty_dirs( $path );
	}

	/**
	 * Removes a folder tree that holds no files.
	 *
	 * @param string $path Folder.
	 * @return void
	 */
	private static function remove_empty_dirs( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}
		foreach ( (array) scandir( $path ) as $entry ) {
			if ( '.' !== $entry && '..' !== $entry && is_dir( $path . '/' . $entry ) ) {
				self::remove_empty_dirs( $path . '/' . $entry );
			}
		}
		@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- only succeeds when empty, which is the point.
	}
}
