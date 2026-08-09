<?php
/**
 * The build script keeps its own output readable, and keeps everything else.
 *
 * wp-cli 2.12 on PHP 8.4 raises a deprecation inside its bundled php-cli-tools
 * on every table it prints, so a build drowned in hundreds of "PHP Deprecated"
 * lines from code this plugin does not own. The fix routes every wp call
 * through one function that drops exactly those lines, by file path.
 *
 * The risk of a fix like that is not that it stops working. It is that somebody
 * later reaches for the blunt instrument, `2>/dev/null`, or adds a wp call that
 * bypasses the filter, and then a real error disappears without anybody
 * noticing, which is a far more expensive kind of quiet than the noise was.
 * This test holds the shape.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * bin/check-and-build.sh.
 */
final class BuildScriptTest extends TestCase {

	/**
	 * The build script's source.
	 *
	 * @return string
	 */
	private function script(): string {
		$path = dirname( __DIR__, 2 ) . '/bin/check-and-build.sh';
		$this->assertFileExists( $path );

		return (string) file_get_contents( $path );
	}

	/**
	 * The lines that call wp, without the comments that talk about it.
	 *
	 * @return array<int, string>
	 */
	private function wp_calls(): array {
		$out = array();
		foreach ( explode( "\n", $this->script() ) as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed || str_starts_with( $trimmed, '#' ) ) {
				continue;
			}
			if ( 1 === preg_match( '/(^|\s|\$\()wp\s/', $trimmed ) ) {
				$out[] = $trimmed;
			}
		}

		return $out;
	}

	/**
	 * Every wp call either goes through the filter or throws its output away on
	 * purpose. The second kind is the availability probe, `wp plugin list
	 * >/dev/null 2>&1`, which asks a question rather than doing work.
	 *
	 * @return void
	 */
	public function test_every_wp_call_is_filtered_or_a_probe(): void {
		$calls = $this->wp_calls();
		$this->assertNotEmpty( $calls, 'The script calls wp; if it no longer does, this test is what is out of date.' );

		foreach ( $calls as $line ) {
			if ( str_starts_with( $line, 'wp_quiet' ) || str_contains( $line, 'wp_quiet()' ) || str_contains( $line, 'WP_CLI_PHP_ARGS=' ) ) {
				continue;
			}

			$this->assertStringContainsString(
				'>/dev/null 2>&1',
				$line,
				'A wp call that neither goes through wp_quiet nor is a silent probe: ' . $line
			);
		}
	}

	/**
	 * The noise is dropped by the path it comes from, not by silencing a stream.
	 *
	 * @return void
	 */
	public function test_the_filter_selects_by_path(): void {
		$script = $this->script();

		$this->assertStringContainsString( "grep -v 'php-cli-tools'", $script );
		$this->assertStringNotContainsString( 'wp_quiet() {' . "\n" . "\t" . 'wp "$@" 2>/dev/null', $script );
	}

	/**
	 * A developer can get the noise back, because a deprecation that turns out to
	 * matter has to be reachable without editing the script.
	 *
	 * @return void
	 */
	public function test_an_explicit_setting_from_the_environment_wins(): void {
		$this->assertStringContainsString( 'WP_CLI_PHP_ARGS="${WP_CLI_PHP_ARGS:-', $this->script() );
	}

	/**
	 * The exit status survives the filter. Without the explicit capture, the
	 * status of the pipeline would be grep's, and a failing make-pot would read
	 * as a successful one.
	 *
	 * @return void
	 */
	public function test_the_status_of_the_wp_call_is_returned(): void {
		$script = $this->script();

		$this->assertStringContainsString( '|| status=$?', $script );
		$this->assertStringContainsString( 'return "$status"', $script );
	}
}
