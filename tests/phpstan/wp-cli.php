<?php
/**
 * WP-CLI stub for static analysis only: the command class runs inside WP-CLI,
 * which PHPStan does not load. Never loaded at runtime.
 *
 * @package LivingHandbook
 */

// phpcs:disable

class WP_CLI {
	/** @param string $name @param callable|class-string $callable */
	public static function add_command( string $name, $callable ): void {}
	public static function log( string $message ): void {}
	public static function success( string $message ): void {}
	public static function warning( string $message ): void {}
	/** @return never */
	public static function error( string $message ): void { exit; }
}
