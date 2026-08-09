<?php
/**
 * The looks a static export can be given.
 *
 * An export leaves the site, and where it lands decides what it should look
 * like. A copy for the team reads best in the colours they know; a copy for an
 * audit or an external partner is better off neutral, without a palette that
 * says nothing to them; and a copy somebody reads at night wants a dark page.
 * So the look is chosen at export time rather than fixed here.
 *
 * The whole mechanism is CSS custom properties. The plugin's own stylesheet
 * reads every colour through a `--lh-user-*` override with a fallback, so a
 * theme sets a handful of properties and the badges, the cards, the freshness
 * colours and the metadata footer all follow. No theme here restyles a single
 * component.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The named looks offered on the export screen.
 */
final class SiteThemes {

	/**
	 * The look an export gets when nothing else is chosen.
	 */
	public const DEFAULT_THEME = 'site';

	/**
	 * Every available look: key to label and CSS.
	 *
	 * @return array<string, array{label: string, css: string}>
	 */
	public static function all(): array {
		$themes = array(
			'site'  => array(
				'label' => __( 'Like this site', 'living-handbook' ),
				'css'   => '',
			),
			'plain' => array(
				'label' => __( 'Plain, neutral', 'living-handbook' ),
				'css'   => self::plain(),
			),
			'dark'  => array(
				'label' => __( 'Dark', 'living-handbook' ),
				'css'   => self::dark(),
			),
			'paper' => array(
				'label' => __( 'Paper, for printing', 'living-handbook' ),
				'css'   => self::paper(),
			),
		);

		/**
		 * Filter the looks offered for a static website export.
		 *
		 * Each entry is keyed by a slug and holds a label and a block of CSS,
		 * appended after the export's own stylesheet. Setting the `--lh-user-*`
		 * custom properties is enough to recolour everything the plugin renders;
		 * a theme that restyles individual components will need revisiting
		 * whenever those components change.
		 *
		 * @param array<string, array{label: string, css: string}> $themes The available looks.
		 */
		/**
		 * A filter can return anything at all, whatever the documented shape says,
		 * so what comes back is treated as unknown and rebuilt entry by entry.
		 *
		 * @var array<string, mixed> $filtered
		 */
		$filtered = (array) apply_filters( 'living_handbook_static_export_themes', $themes );

		$out = array();
		foreach ( $filtered as $key => $theme ) {
			$slug  = sanitize_key( (string) $key );
			$entry = is_array( $theme ) ? $theme : array();
			$label = isset( $entry['label'] ) ? (string) $entry['label'] : '';
			if ( '' === $slug || '' === $label ) {
				continue;
			}
			$out[ $slug ] = array(
				'label' => $label,
				'css'   => isset( $entry['css'] ) ? (string) $entry['css'] : '',
			);
		}

		// The default is not optional: it is what an export falls back to, and a
		// filter that removed it would leave the screen with no valid choice.
		if ( ! isset( $out[ self::DEFAULT_THEME ] ) ) {
			$out = array( self::DEFAULT_THEME => $themes[ self::DEFAULT_THEME ] ) + $out;
		}

		return $out;
	}

	/**
	 * The key of a chosen look, or the default when it is not one we have.
	 *
	 * @param string $key Requested key.
	 * @return string
	 */
	public static function normalize( string $key ): string {
		$key = sanitize_key( $key );

		return isset( self::all()[ $key ] ) ? $key : self::DEFAULT_THEME;
	}

	/**
	 * The CSS of one look.
	 *
	 * @param string $key Theme key.
	 * @return string
	 */
	public static function css( string $key ): string {
		$themes = self::all();
		$key    = self::normalize( $key );

		return $themes[ $key ]['css'];
	}

	/**
	 * Whether a look wants the site's own colours.
	 *
	 * Only the default does. The others exist precisely to leave the site's
	 * palette behind, and layering the settings on top would half-apply it: a
	 * dark page with the site's light surface colour is worse than either.
	 *
	 * @param string $key Theme key.
	 * @return bool
	 */
	public static function uses_site_colours( string $key ): bool {
		return self::DEFAULT_THEME === self::normalize( $key );
	}

	/**
	 * Neutral and light: a grey-blue accent, no house style.
	 *
	 * @return string
	 */
	private static function plain(): string {
		return <<<'CSS'
:root {
	--lh-user-surface: #ffffff;
	--lh-user-surface-text: #1f2328;
	--lh-user-accent: #35566f;
	--lh-user-on-accent: #ffffff;
	--lh-user-badge-bg: #eef1f4;
	--lh-user-badge-text: #2b333b;
	--lh-user-badge-audience-bg: #e7eef4;
	--lh-user-badge-audience-text: #24343f;
}
body.lh-body { background: var(--lh-surface); color: var(--lh-surface-text); line-height: 1.6; }
body.lh-body, body.lh-body .wp-site-blocks { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
CSS;
	}

	/**
	 * Dark: a page for reading at night, and for a screen that is dark anyway.
	 *
	 * The text is not pure white on pure black; that pairing vibrates and tires
	 * the eye over a long page. The freshness colours are lightened, because the
	 * defaults were chosen for contrast against white and disappear on dark.
	 *
	 * @return string
	 */
	private static function dark(): string {
		return <<<'CSS'
:root {
	--lh-user-surface: #16191d;
	--lh-user-surface-text: #e6e8ea;
	--lh-user-accent: #7cb0d8;
	--lh-user-on-accent: #10141a;
	--lh-user-badge-bg: #262b31;
	--lh-user-badge-text: #e6e8ea;
	--lh-user-badge-audience-bg: #23303a;
	--lh-user-badge-audience-text: #dbe7ef;
	--lh-user-ok: #6dc48d;
	--lh-user-due: #e0b060;
	--lh-user-overdue: #ef8b80;
	--lh-user-none: #9aa4ae;
	color-scheme: dark;
}
body.lh-body { background: var(--lh-surface); color: var(--lh-surface-text); line-height: 1.6; }
body.lh-body, body.lh-body .wp-site-blocks { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
CSS;
	}

	/**
	 * Paper: high contrast, serif text, a narrow measure, and a page that prints
	 * as a document rather than as a screenshot of a website.
	 *
	 * @return string
	 */
	private static function paper(): string {
		return <<<'CSS'
:root {
	--lh-user-surface: #ffffff;
	--lh-user-surface-text: #14181c;
	--lh-user-accent: #2f4858;
	--lh-user-on-accent: #ffffff;
	--lh-user-badge-bg: #f0f0ee;
	--lh-user-badge-text: #2a2a28;
	--lh-user-badge-audience-bg: #edeeef;
	--lh-user-badge-audience-text: #2a2a28;
}
body.lh-body { background: var(--lh-surface); color: var(--lh-surface-text); line-height: 1.6; }
body.lh-body, body.lh-body .wp-site-blocks { font-family: Georgia, "Iowan Old Style", "Times New Roman", serif; }
.lh-page { max-width: 38rem; }
.lh-head { border-bottom-width: 2px; }
.lh-nav { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
CSS;
	}

	/**
	 * What every look gets: a print stylesheet.
	 *
	 * A static export is the copy people print, and the browser's default would
	 * put the page tree, the search box and the menu button on paper. This drops
	 * the furniture, spells out where a link goes, and keeps a heading with the
	 * text under it.
	 *
	 * @return string
	 */
	public static function print_css(): string {
		return <<<'CSS'
@media print {
	.lh-head, .lh-nav, .lh-foot, .lh-results, .lh-toc { display: none !important; }
	.lh-layout { display: block; max-width: none; padding: 0; }
	.lh-page { max-width: none; }
	.lh-content a[href^="http"]::after { content: " (" attr(href) ")"; font-size: 0.85em; word-break: break-all; }
	h1, h2, h3 { break-after: avoid-page; }
	pre, table, figure, .mermaid { break-inside: avoid; }
	body.lh-body { background: #fff; color: #000; }
}
CSS;
	}
}
