<?php
/**
 * Minimal unit smoke test to prove the toolchain runs without WordPress.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Toolchain smoke test.
 */
final class SmokeTest extends TestCase {

	/**
	 * The plugin version follows semantic versioning.
	 *
	 * @return void
	 */
	public function test_version_is_semver(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', '0.5.0' );
	}
}
