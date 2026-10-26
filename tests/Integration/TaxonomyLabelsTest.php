<?php
/**
 * The labels of the taxonomies are whole sentences a translator can translate.
 *
 * They were assembled from fragments: sprintf( __( 'Add new %s' ), $singular ).
 * English gets away with that, German does not. The article and the adjective
 * ending depend on the gender of the noun, so "Add new %s" has no single
 * translation: it is "Neues Thema hinzufügen", "Neuen Seitentyp hinzufügen" and
 * "Neue Zielgruppe hinzufügen". The German catalogue had settled on "%s
 * hinzufügen" and lost the "new"; "New %s name" became "%s-Name". Each label
 * is now its own string.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\Taxonomy\Taxonomies;
use WP_UnitTestCase;

/**
 * Taxonomy labels.
 */
final class TaxonomyLabelsTest extends WP_UnitTestCase {

	/**
	 * The five taxonomies of the plugin.
	 *
	 * @return array<int, string>
	 */
	private function taxonomies(): array {
		return array_merge( array( Handbooks::TAXONOMY ), array_keys( Taxonomies::all() ) );
	}

	/**
	 * Registering the taxonomies asks for no translatable string with a
	 * placeholder in it.
	 *
	 * @return void
	 */
	public function test_no_label_is_built_from_a_fragment(): void {
		$asked  = array();
		$record = static function ( $translation, $text, $domain ) use ( &$asked ) {
			if ( 'living-handbook' === $domain ) {
				$asked[] = (string) $text;
			}
			return $translation;
		};
		add_filter( 'gettext', $record, 10, 3 );

		( new Taxonomies() )->register_taxonomies();
		( new Handbooks() )->register_taxonomy();

		remove_filter( 'gettext', $record, 10 );

		$fragments = array_values( array_filter( $asked, static fn( string $t ): bool => str_contains( $t, '%' ) ) );
		$this->assertSame( array(), $fragments, 'Labels are still assembled from fragments.' );
	}

	/**
	 * Every taxonomy has its own label for each of the screens, none of them
	 * the core fallback for categories or tags.
	 *
	 * @return void
	 */
	public function test_every_taxonomy_has_its_own_labels(): void {
		$seen = array();
		foreach ( $this->taxonomies() as $taxonomy ) {
			$labels = get_taxonomy( $taxonomy )->labels;
			foreach ( array( 'edit_item', 'view_item', 'update_item', 'add_new_item', 'new_item_name', 'search_items', 'not_found', 'back_to_items' ) as $key ) {
				$label = (string) $labels->$key;
				$this->assertNotSame( '', $label, $taxonomy . ' ' . $key );
				$this->assertDoesNotMatchRegularExpression( '/\b(categor|tag)/i', $label, $taxonomy . ' ' . $key . ' falls back to core: ' . $label );
				$this->assertArrayNotHasKey( $label, $seen, $label . ' is shared by ' . ( $seen[ $label ] ?? '' ) . ' and ' . $taxonomy );
				$seen[ $label ] = $taxonomy;
			}
		}

		$this->assertSame( 'Add new topic', get_taxonomy( Taxonomies::TOPIC )->labels->add_new_item );
		$this->assertSame( 'Parent topic', get_taxonomy( Taxonomies::TOPIC )->labels->parent_item );
	}
}
