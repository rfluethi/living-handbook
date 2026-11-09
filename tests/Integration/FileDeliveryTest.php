<?php
/**
 * Delivery of protected files: who gets a file, and how (E-0076).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Tests\Integration;

use LivingHandbook\Files\FileDelivery;
use LivingHandbook\Files\ProtectedFiles;
use LivingHandbook\Files\ServerRules;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_UnitTestCase;

/**
 * The same access check as for the page, and a response a browser can cache
 * for itself but no shared cache.
 */
final class FileDeliveryTest extends WP_UnitTestCase {

	/**
	 * Attachments made by a test.
	 *
	 * @var array<int, int>
	 */
	private array $made = array();

	/**
	 * Files a test put next to the attachments.
	 *
	 * @var array<int, string>
	 */
	private array $extra = array();

	/**
	 * Remove files and rules.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->made as $id ) {
			wp_delete_attachment( $id, true );
		}
		foreach ( $this->extra as $path ) {
			if ( is_link( $path ) || file_exists( $path ) ) {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture, symlinks included.
			}
		}
		$this->made  = array();
		$this->extra = array();
		ServerRules::remove();
		delete_option( 'living_handbook_moved_files' );
		ProtectedFiles::unschedule();
		wp_cache_flush();
		parent::tear_down();
	}

	/**
	 * A page in a handbook of the given visibility, with an uploaded image.
	 *
	 * @param string             $visibility Visibility.
	 * @param array<string,mixed> $page_args  More page arguments.
	 * @return array{page:int, id:int, relative:string, term:int}
	 */
	private function protected_image( string $visibility = Handbooks::VISIBILITY_MEMBERS, array $page_args = array() ): array {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Handbooks::TAXONOMY ) );
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );
		$page = (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => Handbook::POST_TYPE,
					'post_status' => 'publish',
				),
				$page_args
			)
		);
		wp_set_object_terms( $page, array( $term_id ), Handbooks::TAXONOMY );
		$id           = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $page );
		$this->made[] = $id;
		$this->assertTrue( ProtectedFiles::is_protected( $id ) );
		return array(
			'page'     => $page,
			'id'       => $id,
			'relative' => substr( (string) get_post_meta( $id, '_wp_attached_file', true ), strlen( ProtectedFiles::DIR . '/' ) ),
			'term'     => $term_id,
		);
	}

	/**
	 * Paths that try to leave the folder or hide something are refused.
	 *
	 * @return void
	 */
	public function test_normalise_refuses_tricks(): void {
		foreach ( array( '', '../wp-config.php', '2026/../../x', "a\0b.jpg", 'a\\b.jpg', './x.jpg', '2026//', '/' ) as $bad ) {
			$this->assertNull( FileDelivery::normalise( $bad ), $bad );
		}
		$this->assertSame( '2026/11/a.jpg', FileDelivery::normalise( '/2026//11/a.jpg' ) );
	}

	/**
	 * The path comes from the address itself where it can, so a name with
	 * "&" survives a rule that copies it into the query unescaped.
	 *
	 * @return void
	 */
	public function test_path_from_the_address(): void {
		$prefix = (string) wp_parse_url( ProtectedFiles::folder()['url'], PHP_URL_PATH );
		$this->assertSame( '2026/11/a&b c.pdf', FileDelivery::from_uri( $prefix . '/2026/11/a%26b%20c.pdf?x=1' ) );
		$this->assertNull( FileDelivery::from_uri( '/?lh_file=2026/11/a.jpg' ) );
		$this->assertNull( FileDelivery::normalise( (string) FileDelivery::from_uri( $prefix . '/2026/%2e%2e/%2e%2e/wp-config.php' ) ) );
		$this->assertNull( FileDelivery::normalise( (string) FileDelivery::from_uri( $prefix . '/2026/a%00.jpg' ) ) );
	}

	/**
	 * Unknown files, files without attachment and links out of the folder
	 * answer 404, without telling which.
	 *
	 * @return void
	 */
	public function test_unknown_and_foreign_files_answer_404(): void {
		$image  = $this->protected_image();
		$folder = ProtectedFiles::folder();
		$admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertSame( 404, FileDelivery::respond( '2026/01/fehlt.jpg', $admin )['status'] );

		$stray         = $folder['path'] . '/' . dirname( $image['relative'] ) . '/streu.txt';
		$this->extra[] = $stray;
		file_put_contents( $stray, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		$this->assertSame( 404, FileDelivery::respond( dirname( $image['relative'] ) . '/streu.txt', $admin )['status'], 'A file without attachment.' );

		$link          = $folder['path'] . '/' . dirname( $image['relative'] ) . '/config.jpg';
		$this->extra[] = $link;
		symlink( ABSPATH . 'wp-config-sample.php', $link );
		$this->assertSame( 404, FileDelivery::respond( dirname( $image['relative'] ) . '/config.jpg', $admin )['status'], 'A link out of the folder.' );
	}

	/**
	 * Who may read the page gets the file; who may not gets 403, a guest
	 * opening it directly the login.
	 *
	 * @return void
	 */
	public function test_access_follows_the_page(): void {
		$image  = $this->protected_image();
		$reader = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( 200, FileDelivery::respond( $image['relative'], $reader )['status'] );
		$this->assertSame( 403, FileDelivery::respond( $image['relative'], 0, array( 'HTTP_SEC_FETCH_DEST' => 'image' ) )['status'] );

		$open = FileDelivery::respond(
			$image['relative'],
			0,
			array(
				'HTTP_SEC_FETCH_DEST' => 'document',
				'REQUEST_URI'         => '/wp-content/uploads/' . ProtectedFiles::DIR . '/' . $image['relative'],
			)
		);
		$this->assertSame( 302, $open['status'] );
		$this->assertStringContainsString( 'wp-login.php', (string) $open['redirect'] );
		$this->assertStringContainsString( rawurlencode( ProtectedFiles::DIR ), (string) $open['redirect'] );

		$this->assertSame( 302, FileDelivery::respond( $image['relative'], 0, array( 'HTTP_ACCEPT' => 'text/html,*/*' ) )['status'], 'An older browser without Fetch Metadata.' );
	}

	/**
	 * A restricted handbook: only the people named get the file, and whoever
	 * may edit the file. An editor is not named and may not (E-0075).
	 *
	 * @return void
	 */
	public function test_restricted_handbook(): void {
		$image   = $this->protected_image( Handbooks::VISIBILITY_RESTRICTED );
		$named   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$other   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$editor  = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		$admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$uploder = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		update_term_meta( $image['term'], Handbooks::META_USERS, array( $named ) );
		wp_update_post(
			array(
				'ID'          => $image['id'],
				'post_author' => $uploder,
			)
		);

		$this->assertSame( 200, FileDelivery::respond( $image['relative'], $named )['status'] );
		$this->assertSame( 403, FileDelivery::respond( $image['relative'], $other )['status'] );
		$this->assertSame( 403, FileDelivery::respond( $image['relative'], $editor )['status'] );
		$this->assertSame( 200, FileDelivery::respond( $image['relative'], $admin )['status'] );
		$this->assertSame( 200, FileDelivery::respond( $image['relative'], $uploder )['status'] );
	}

	/**
	 * A draft page and a page with a password keep their files to themselves.
	 *
	 * @return void
	 */
	public function test_draft_and_password_pages(): void {
		$reader = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$author = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$draft  = $this->protected_image(
			Handbooks::VISIBILITY_MEMBERS,
			array(
				'post_status' => 'draft',
				'post_author' => $author,
			)
		);
		$this->assertSame( 403, FileDelivery::respond( $draft['relative'], $reader )['status'] );

		$this->assertSame( 200, FileDelivery::respond( $draft['relative'], $author )['status'], 'The author of the draft.' );

		$secret = $this->protected_image( Handbooks::VISIBILITY_MEMBERS, array( 'post_password' => 'geheim' ) );
		$this->assertSame( 403, FileDelivery::respond( $secret['relative'], $reader )['status'] );
	}

	/**
	 * Every size of an image is found, and is served like the main file.
	 *
	 * @return void
	 */
	public function test_sizes_are_found(): void {
		$image  = $this->protected_image();
		$reader = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$meta   = wp_get_attachment_metadata( $image['id'], true );
		foreach ( (array) $meta['sizes'] as $size ) {
			$relative = dirname( $image['relative'] ) . '/' . $size['file'];
			$this->assertSame( $image['id'], FileDelivery::attachment_for( $relative ) );
			$this->assertSame( 200, FileDelivery::respond( $relative, $reader )['status'] );
		}
	}

	/**
	 * The headers: private caching with ETag, 304, ranges.
	 *
	 * @return void
	 */
	public function test_headers_revalidation_and_ranges(): void {
		$image  = $this->protected_image();
		$reader = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$size   = (int) filesize( (string) get_attached_file( $image['id'] ) );

		$full = FileDelivery::respond( $image['relative'], $reader );
		$this->assertSame( 'image/jpeg', $full['headers']['Content-Type'] );
		$this->assertSame( 'nosniff', $full['headers']['X-Content-Type-Options'] );
		$this->assertStringStartsWith( 'private', $full['headers']['Cache-Control'] );
		$this->assertStringStartsWith( 'inline', $full['headers']['Content-Disposition'] );
		$this->assertSame( $size, $full['length'] );

		$again = FileDelivery::respond( $image['relative'], $reader, array( 'HTTP_IF_NONE_MATCH' => $full['headers']['ETag'] ) );
		$this->assertSame( 304, $again['status'] );

		$part = FileDelivery::respond( $image['relative'], $reader, array( 'HTTP_RANGE' => 'bytes=10-19' ) );
		$this->assertSame( 206, $part['status'] );
		$this->assertSame( 10, $part['offset'] );
		$this->assertSame( 10, $part['length'] );
		$this->assertSame( 'bytes 10-19/' . $size, $part['headers']['Content-Range'] );

		$tail = FileDelivery::respond( $image['relative'], $reader, array( 'HTTP_RANGE' => 'bytes=-5' ) );
		$this->assertSame( 206, $tail['status'] );
		$this->assertSame( $size - 5, $tail['offset'] );

		$this->assertSame( 416, FileDelivery::respond( $image['relative'], $reader, array( 'HTTP_RANGE' => 'bytes=' . ( $size + 10 ) . '-' ) )['status'] );
	}

	/**
	 * The self-test file answers with the marker header and no content.
	 *
	 * @return void
	 */
	public function test_selftest_file(): void {
		$answer = FileDelivery::respond( FileDelivery::SELFTEST, 0 );
		$this->assertSame( 403, $answer['status'] );
		$this->assertSame( '1', $answer['headers'][ FileDelivery::SELFTEST_HEADER ] );
		$this->assertArrayNotHasKey( 'file', $answer );
	}

	/**
	 * With the hand-off switched on, the web server sends the file.
	 *
	 * @return void
	 */
	public function test_handoff_to_the_web_server(): void {
		$image  = $this->protected_image();
		$reader = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		add_filter( 'living_handbook_protected_files_handoff', static fn() => 'x-accel-redirect' );

		$answer = FileDelivery::respond( $image['relative'], $reader );
		$this->assertSame( 200, $answer['status'] );
		$this->assertSame( '/lh-protected-internal/' . $image['relative'], $answer['headers']['X-Accel-Redirect'] );
		$this->assertSame( '', $answer['file'], 'PHP sends no body.' );
		$this->assertArrayNotHasKey( 'Content-Length', $answer['headers'] );
	}

	/**
	 * The rules: Apache sends the folder to WordPress, or refuses everything
	 * without mod_rewrite; the nginx block maps the internal location.
	 *
	 * @return void
	 */
	public function test_server_rules(): void {
		$this->assertTrue( ServerRules::ensure() );
		$folder   = ProtectedFiles::folder();
		$htaccess = (string) file_get_contents( $folder['path'] . '/.htaccess' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$this->assertStringContainsString( 'index.php?lh_file=$1 [L,QSA,B]', $htaccess );
		$this->assertStringContainsString( 'Require all denied', $htaccess );
		$this->assertFileExists( $folder['path'] . '/index.php' );
		$this->assertFileExists( $folder['path'] . '/' . FileDelivery::SELFTEST );

		$nginx = ServerRules::nginx();
		$this->assertStringContainsString( 'location /lh-protected-internal/', $nginx );
		$this->assertStringContainsString( 'internal;', $nginx );
		$this->assertStringContainsString( 'alias ' . $folder['path'] . '/', $nginx );

		ServerRules::remove();
		$this->assertFileDoesNotExist( $folder['path'] . '/.htaccess' );
	}
}
