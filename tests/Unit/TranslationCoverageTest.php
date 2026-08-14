<?php
/**
 * The German translations are complete, and nobody has to remember to look.
 *
 * 0.56.0 shipped its whole settings tab in English on a German site, because the
 * translation template had last been generated before that tab existed. Nothing
 * failed, nothing was reported, and it was found by a screenshot a release
 * later. An untranslated string is invisible to everyone except the person
 * reading the wrong language.
 *
 * So it is measured here instead: every entry in the shipped catalogues has a
 * translation, and de_CH is the Swiss variant of de_DE rather than a second text
 * that drifts away from it. Both run in CI on every push.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The .po catalogues under languages/.
 */
final class TranslationCoverageTest extends TestCase {

	/**
	 * Entries that are deliberately not translated: the plugin's own addresses
	 * and the author's name, which read the same in every language. They come
	 * from the plugin header and cannot be removed from the catalogue.
	 */
	private const NOT_TRANSLATED_ON_PURPOSE = array(
		'https://github.com/rfluethi/living-handbook',
		'https://rfluethi.com',
		'Rico F. Luethi',
	);

	/**
	 * Parse a .po file into msgid => msgstr, plurals included.
	 *
	 * Deliberately a small parser rather than a dependency: the format is three
	 * keywords and continuation lines, and a test that needs gettext installed to
	 * run is a test that quietly does not run.
	 *
	 * @param string $path Absolute path to the .po file.
	 * @return array<string, string> Source string to translation.
	 */
	private function entries( string $path ): array {
		$lines = file( $path, FILE_IGNORE_NEW_LINES );
		$this->assertIsArray( $lines, $path . ' could not be read.' );

		$entries = array();
		$id      = null;
		$str     = null;
		$field   = '';

		$unquote = static function ( string $line ): string {
			$start = strpos( $line, '"' );
			$end   = strrpos( $line, '"' );
			if ( false === $start || $end <= $start ) {
				return '';
			}

			return stripcslashes( substr( $line, $start + 1, $end - $start - 1 ) );
		};

		$flush = static function () use ( &$entries, &$id, &$str ): void {
			if ( null !== $id && '' !== $id ) {
				$entries[ $id ] = (string) $str;
			}
			$id  = null;
			$str = null;
		};

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				$flush();
				$field = '';
				continue;
			}

			if ( str_starts_with( $line, 'msgid_plural' ) ) {
				$field = 'skip';
				continue;
			}

			if ( str_starts_with( $line, 'msgid' ) ) {
				$flush();
				$field = 'id';
				$id    = $unquote( $line );
				continue;
			}

			if ( str_starts_with( $line, 'msgstr' ) ) {
				// msgstr[0] and msgstr[1] both count: a plural entry is
				// translated when its first form is.
				if ( str_starts_with( $line, 'msgstr[1]' ) ) {
					$field = 'skip';
					continue;
				}
				$field = 'str';
				$str   = ( null === $str ? '' : $str ) . $unquote( $line );
				continue;
			}

			if ( str_starts_with( $line, '"' ) ) {
				if ( 'id' === $field ) {
					$id .= $unquote( $line );
				} elseif ( 'str' === $field ) {
					$str .= $unquote( $line );
				}
			}
		}

		$flush();

		return $entries;
	}

	/**
	 * Every string the template knows is in both catalogues.
	 *
	 * This is the gap the other tests cannot see. They check the catalogues
	 * against themselves: every entry that is in a .po has a translation. A
	 * string that never reached the .po is therefore invisible to them, and
	 * that is exactly what happens when the build runs without gettext, because
	 * `msgmerge` is what carries a new string from the template into the
	 * catalogue. The result ships in English on a German site and nothing says a
	 * word, which is the 0.56.0 story again with a different cause.
	 *
	 * So the template is compared against the catalogues here. The build fails
	 * before a release rather than a screenshot finding it afterwards.
	 *
	 * @dataProvider locales
	 * @param string $locale Locale to check.
	 * @return void
	 */
	public function test_the_catalogue_covers_every_string_of_the_template( string $locale ): void {
		$template = dirname( __DIR__, 2 ) . '/languages/living-handbook.pot';
		$this->assertFileExists( $template );

		$missing = array_values(
			array_diff(
				array_keys( $this->entries( $template ) ),
				array_keys( $this->entries( $this->catalogue( $locale ) ) )
			)
		);

		$this->assertSame(
			array(),
			$missing,
			count( $missing ) . ' string(s) are in living-handbook.pot but not in ' . $locale
			. ". Run bin/check-and-build.sh with gettext installed so msgmerge carries them over, then translate them:\n  "
			. implode( "\n  ", array_slice( $missing, 0, 20 ) )
		);
	}

	/**
	 * The path of a shipped catalogue.
	 *
	 * @param string $locale Locale, e.g. de_DE.
	 * @return string
	 */
	private function catalogue( string $locale ): string {
		$path = dirname( __DIR__, 2 ) . '/languages/living-handbook-' . $locale . '.po';
		$this->assertFileExists( $path );

		return $path;
	}

	/**
	 * Every string in a shipped catalogue is translated.
	 *
	 * @dataProvider locales
	 * @param string $locale Locale to check.
	 * @return void
	 */
	public function test_every_string_is_translated( string $locale ): void {
		$missing = array();

		foreach ( $this->entries( $this->catalogue( $locale ) ) as $source => $translation ) {
			if ( '' === $translation && ! in_array( $source, self::NOT_TRANSLATED_ON_PURPOSE, true ) ) {
				$missing[] = $source;
			}
		}

		$this->assertSame(
			array(),
			$missing,
			count( $missing ) . ' string(s) in ' . $locale . " have no translation. Run bin/check-and-build.sh to refresh the catalogues, then translate what it added:\n  " . implode( "\n  ", array_slice( $missing, 0, 20 ) )
		);
	}

	/**
	 * The locales that ship.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function locales(): array {
		return array(
			'de_DE' => array( 'de_DE' ),
			'de_CH' => array( 'de_CH' ),
		);
	}

	/**
	 * The catalogues cover the same strings. A string that reached only one of
	 * them means the Swiss variant was not derived from the German one but
	 * edited by hand, which is how the two start drifting apart.
	 *
	 * @return void
	 */
	public function test_the_two_german_catalogues_cover_the_same_strings(): void {
		$de = array_keys( $this->entries( $this->catalogue( 'de_DE' ) ) );
		$ch = array_keys( $this->entries( $this->catalogue( 'de_CH' ) ) );

		sort( $de );
		sort( $ch );

		$this->assertSame( $de, $ch, 'de_DE and de_CH do not cover the same strings.' );
	}

	/**
	 * de_CH is de_DE with the sharp s resolved to ss, and nothing else. This is
	 * the convention written down in CONTRIBUTING.md; here it is checked.
	 *
	 * @return void
	 */
	public function test_the_swiss_catalogue_is_the_german_one_without_the_sharp_s(): void {
		$de = $this->entries( $this->catalogue( 'de_DE' ) );
		$ch = $this->entries( $this->catalogue( 'de_CH' ) );

		$differs = array();
		foreach ( $de as $source => $german ) {
			$swiss = $ch[ $source ] ?? '';
			if ( str_replace( 'ß', 'ss', $german ) !== $swiss ) {
				$differs[] = $source;
			}
		}

		$this->assertSame(
			array(),
			$differs,
			"de_CH is not de_DE with ß resolved to ss for:\n  " . implode( "\n  ", array_slice( $differs, 0, 10 ) )
		);
	}

	/**
	 * And no sharp s survives in the Swiss catalogue, which is the one thing the
	 * whole convention exists for.
	 *
	 * @return void
	 */
	public function test_no_sharp_s_survives_in_the_swiss_catalogue(): void {
		foreach ( $this->entries( $this->catalogue( 'de_CH' ) ) as $source => $translation ) {
			$this->assertStringNotContainsString( 'ß', $translation, 'de_CH still has a ß in the translation of: ' . $source );
		}
	}
}
