<?php
/**
 * The images a bundle carries.
 *
 * The editor inserts a large image at one of its sizes, "plan-1024x768.jpg",
 * not as the original. The export only recognised an attachment by its own
 * URL, so such an image did not travel, and the page on the target site went
 * on loading it from the old one: a hotlink that breaks the day the old site
 * goes, and leaks every reader's visit to it until then.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Import\HandbookExport;
use LivingHandbook\Import\HandbookImport;
use WP_Term;
use WP_UnitTestCase;
use ZipArchive;

/**
 * HandbookExport and the files behind a page's images.
 */
final class BundleMediaTest extends WP_UnitTestCase {

	/**
	 * Bundles written by a test.
	 *
	 * @var array<int, string>
	 */
	private array $bundles = array();

	/**
	 * Run as somebody who may export.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Remove the bundles.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->bundles as $path ) {
			wp_delete_file( $path );
		}
		parent::tear_down();
	}

	/**
	 * Upload a real JPEG large enough to get intermediate sizes.
	 *
	 * @return int Attachment ID.
	 */
	private function upload_large_image(): int {
		$image = imagecreatetruecolor( 1600, 1200 );
		ob_start();
		imagejpeg( $image );
		$data = (string) ob_get_clean();

		$upload = wp_upload_bits( 'plan-' . wp_generate_password( 6, false, false ) . '.jpg', null, $data );
		$this->assertEmpty( $upload['error'] );

		$id = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Plan',
				'post_status'    => 'inherit',
			),
			(string) $upload['file']
		);
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, (string) $upload['file'] ) );
		return $id;
	}

	/**
	 * A page showing an image at its "large" size carries that file.
	 *
	 * @return void
	 */
	public function test_an_image_at_an_intermediate_size_travels(): void {
		$image = $this->upload_large_image();
		$large = wp_get_attachment_image_src( $image, 'large' );
		$this->assertIsArray( $large );
		$this->assertMatchesRegularExpression( '/-\d+x\d+\.jpg$/', (string) $large[0], 'The test needs a URL with a size in it.' );

		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Media ' . wp_generate_password( 6, false ),
			)
		);
		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'    => 'handbook',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:image --><figure class="wp-block-image"><img src="' . esc_url( (string) $large[0] ) . '" alt=""/></figure><!-- /wp:image -->',
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );

		$term = get_term( $term_id, 'handbook_set' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$path            = ( new HandbookExport() )->build_zip( $term );
		$this->bundles[] = $path;

		$zip = new ZipArchive();
		$this->assertTrue( true === $zip->open( $path ) );
		$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$this->assertIsArray( $manifest );

		$by_url = array();
		foreach ( (array) $manifest['media'] as $item ) {
			$by_url[ (string) $item['original_url'] ] = (string) $item['file'];
		}
		$this->assertArrayHasKey( (string) $large[0], $by_url, 'The size the page shows is in the bundle, under the URL the content uses.' );
		$this->assertNotFalse( $zip->getFromName( $by_url[ (string) $large[0] ] ), 'And its file is in the ZIP.' );
		$zip->close();
	}

	/**
	 * A protected image travels in the bundle, read from the protected folder.
	 *
	 * @return void
	 */
	public function test_a_protected_image_travels(): void {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Intern ' . wp_generate_password( 6, false ),
			)
		);
		update_term_meta( $term_id, 'living_handbook_visibility', 'members' );
		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );
		$image = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $post_id );
		try {
			$this->assertTrue( \LivingHandbook\Files\ProtectedFiles::is_protected( $image ) );
			$url = (string) wp_get_attachment_url( $image );
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '<!-- wp:image {"id":' . $image . '} --><figure class="wp-block-image"><img src="' . esc_url( $url ) . '" alt=""/></figure><!-- /wp:image -->',
				)
			);

			$term = get_term( $term_id, 'handbook_set' );
			$this->assertInstanceOf( WP_Term::class, $term );
			$path            = ( new HandbookExport() )->build_zip( $term );
			$this->bundles[] = $path;

			$zip = new ZipArchive();
			$this->assertTrue( true === $zip->open( $path ) );
			$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
			$this->assertIsArray( $manifest );
			$by_url = array();
			foreach ( (array) $manifest['media'] as $item ) {
				$by_url[ (string) $item['original_url'] ] = (string) $item['file'];
			}
			$this->assertArrayHasKey( $url, $by_url );
			$this->assertSame( (string) file_get_contents( (string) get_attached_file( $image ) ), (string) $zip->getFromName( $by_url[ $url ] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.

			// Back in, into another members handbook: the file is attached to its
			// page and lands in the protected folder, and the page follows.
			$target = (int) self::factory()->term->create( array( 'taxonomy' => 'handbook_set' ) );
			update_term_meta( $target, 'living_handbook_visibility', 'members' );
			$report = ( new HandbookImport() )->import_manifest(
				$manifest,
				static fn( string $file ) => $zip->getFromName( $file ),
				HandbookImport::RULE_CREATE,
				$target
			);
			$zip->close();
			$this->assertSame( 1, $report['created'], (string) wp_json_encode( $report ) );
			$this->assertContains( 'The bundle was made with Living Handbook ' . LIVING_HANDBOOK_VERSION . '.', $report['notes'], 'A real bundle names its version.' );
			$pages = get_posts(
				array(
					'post_type'   => 'handbook',
					'post_status' => 'any',
					'fields'      => 'ids',
					'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- test.
						array(
							'taxonomy' => 'handbook_set',
							'terms'    => $target,
						),
					),
				)
			);
			$this->assertCount( 1, $pages );
			$children = get_children(
				array(
					'post_parent' => (int) $pages[0],
					'post_type'   => 'attachment',
					'fields'      => 'ids',
				)
			);
			$this->assertCount( 1, $children, 'The imported file belongs to its page.' );
			$copy = (int) reset( $children );
			$this->assertTrue( \LivingHandbook\Files\ProtectedFiles::is_protected( $copy ) );
			$this->assertStringContainsString( (string) wp_get_attachment_url( $copy ), (string) get_post_field( 'post_content', (int) $pages[0], 'raw' ) );
			wp_delete_attachment( $copy, true );
		} finally {
			wp_delete_attachment( $image, true );
			\LivingHandbook\Files\ServerRules::remove();
		}
	}

	/**
	 * A URL that only looks like a size of an attachment is not followed.
	 *
	 * @return void
	 */
	public function test_a_made_up_size_is_not_carried(): void {
		$image = $this->upload_large_image();
		$url   = (string) wp_get_attachment_url( $image );
		$fake  = (string) preg_replace( '/\.jpg$/', '-7x7.jpg', $url );

		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'handbook_set',
				'name'     => 'Fake ' . wp_generate_password( 6, false ),
			)
		);
		$post_id = (int) self::factory()->post->create(
			array(
				'post_type'    => 'handbook',
				'post_status'  => 'publish',
				'post_content' => '<p><img src="' . esc_url( $fake ) . '" alt=""></p>',
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), 'handbook_set' );

		$term = get_term( $term_id, 'handbook_set' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$media    = array();
		$manifest = ( new HandbookExport() )->build_manifest( $term, $media );

		$this->assertSame( array(), $manifest['media'] );
	}
}
