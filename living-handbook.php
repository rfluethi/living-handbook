<?php
/**
 * Plugin Name:       Living Handbook
 * Plugin URI:        https://github.com/rfluethi/living-handbook
 * Description:       An internal team handbook for WordPress: structured page types, clear ownership, and freshness tracking so docs don't rot.
 * Version:           0.50.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Rico F. Luethi
 * Author URI:        https://rfluethi.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       living-handbook
 * Domain Path:       /languages
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIVING_HANDBOOK_VERSION', '0.50.0' );
define( 'LIVING_HANDBOOK_FILE', __FILE__ );
define( 'LIVING_HANDBOOK_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIVING_HANDBOOK_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4 style autoloader for the LivingHandbook namespace, mapped to src/.
 * The plugin's own classes need no Composer. The Markdown import and GitHub sync
 * additionally use two Composer libraries (league/commonmark, symfony/yaml); the
 * build ships them in vendor/ and MarkdownConverter loads vendor/autoload.php on
 * demand. If vendor/ is absent, only import and sync are disabled.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = LIVING_HANDBOOK_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

// Boot the plugin once WordPress and all plugins are loaded.
add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );
