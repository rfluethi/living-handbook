<?php
/**
 * Finding the bundled libraries under either name.
 *
 * The release build moves them into LivingHandbook\Vendor\, a development
 * checkout leaves them where Composer put them, and the plugin has to work in
 * both. This is the piece that decides which one is in front of it, so it is
 * tested with classes made up for the purpose: a real library would only prove
 * whichever case this checkout happens to be.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit\Support;

use LivingHandbook\Support\Vendored;
use PHPUnit\Framework\TestCase;

/**
 * Class name resolution for the bundled libraries.
 */
final class VendoredTest extends TestCase {

	/**
	 * Declare the stand-ins once: a class that exists under both names, one that
	 * only exists prefixed, and one that only exists plain.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		eval(
			'namespace LivingHandbook\Vendor\Made\Up { class BothWays {} class OnlyScoped {} }' .
			'namespace Made\Up { class BothWays {} class OnlyPlain {} }'
		); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Declaring throwaway classes for this test; nothing here comes from outside.
	}

	/**
	 * A scoped build wins: if the prefixed copy is there, it is used, even when
	 * another plugin has loaded something under the plain name.
	 *
	 * @return void
	 */
	public function test_the_prefixed_class_is_preferred(): void {
		$this->assertSame(
			'LivingHandbook\Vendor\Made\Up\BothWays',
			Vendored::name( 'Made\Up\BothWays' )
		);
	}

	/**
	 * A development checkout has no prefix, so the plain name is the answer.
	 *
	 * @return void
	 */
	public function test_the_plain_class_is_used_when_there_is_no_prefixed_one(): void {
		$this->assertSame( 'Made\Up\OnlyPlain', Vendored::name( 'Made\Up\OnlyPlain' ) );
	}

	/**
	 * A class only present prefixed is found under the prefix.
	 *
	 * @return void
	 */
	public function test_a_scoped_only_class_is_found(): void {
		$this->assertSame(
			'LivingHandbook\Vendor\Made\Up\OnlyScoped',
			Vendored::name( 'Made\Up\OnlyScoped' )
		);
		$this->assertTrue( Vendored::exists( 'Made\Up\OnlyScoped' ) );
	}

	/**
	 * A missing library is reported as missing, and its name comes back
	 * unchanged rather than as a prefixed name that exists nowhere.
	 *
	 * @return void
	 */
	public function test_a_missing_class_is_reported_missing(): void {
		$this->assertFalse( Vendored::exists( 'Made\Up\NotHere' ) );
		$this->assertSame( 'Made\Up\NotHere', Vendored::name( 'Made\Up\NotHere' ) );
	}

	/**
	 * A leading backslash is tolerated, so a caller copying a name out of a
	 * library's own documentation gets the same answer.
	 *
	 * @return void
	 */
	public function test_a_leading_backslash_makes_no_difference(): void {
		$this->assertSame(
			Vendored::name( 'Made\Up\BothWays' ),
			Vendored::name( '\Made\Up\BothWays' )
		);
	}

	/**
	 * Interfaces count too: the Markdown library is told apart from its older
	 * version by an interface, not a class.
	 *
	 * @return void
	 */
	public function test_interfaces_are_found_as_well(): void {
		eval( 'namespace LivingHandbook\Vendor\Made\Up { interface Contract {} }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- See setUpBeforeClass().

		$this->assertTrue( Vendored::exists( 'Made\Up\Contract' ) );
		$this->assertSame( 'LivingHandbook\Vendor\Made\Up\Contract', Vendored::name( 'Made\Up\Contract' ) );
	}
}
