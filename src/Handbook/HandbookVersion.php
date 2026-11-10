<?php
/**
 * The version of each handbook: an edition and a count of days with changes.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Handbook;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Git\GitSync;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every handbook carries a version such as "2.1.14" (E-0084).
 *
 * - The edition ("2.1") is set by hand on the handbook, or comes from the
 *   newest version tag of the GitHub repository its pages are synced from.
 * - The count ("14") is the plugin's: one more for every day on which the
 *   published content of the handbook changed. A new edition starts it at 0.
 *
 * It is the version of a handbook's content, not of the plugin (E-0082), and
 * it carries no date, so a handbook that stays right for a long time does not
 * look old.
 */
final class HandbookVersion {

	/**
	 * The edition set by hand or taken from the tag. Empty means "1".
	 */
	public const META_EDITION = 'living_handbook_version_edition';

	/**
	 * Days with changes since the edition was set.
	 */
	public const META_COUNT = 'living_handbook_version_count';

	/**
	 * The last day (site time, Y-m-d) that was counted.
	 */
	public const META_DAY = 'living_handbook_version_day';

	/**
	 * Where the edition comes from: "manual" or "github".
	 */
	public const META_SOURCE = 'living_handbook_version_source';

	/**
	 * The repository (owner/repo) whose tags give the edition.
	 */
	public const META_REPO = 'living_handbook_version_repo';

	/**
	 * The tag the edition was last taken from, as GitHub names it.
	 */
	public const META_TAG = 'living_handbook_version_tag';

	/**
	 * Why the last attempt to read the tags failed, or empty.
	 */
	public const META_ERROR = 'living_handbook_version_error';

	public const SOURCE_MANUAL = 'manual';
	public const SOURCE_GITHUB = 'github';

	/**
	 * An edition: one to three numbers separated by dots.
	 */
	private const EDITION_PATTERN = '/^\d{1,6}(?:\.\d{1,6}){0,2}$/';

	/**
	 * A tag that names a version: an optional "v", then an edition.
	 */
	private const TAG_PATTERN = '/^[vV]?(\d{1,6}(?:\.\d{1,6}){0,2})$/';

	/**
	 * Hooks the counting, the fields and the tag refresh.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'transition_post_status', array( self::class, 'status_changed' ), 10, 3 );
		add_action( 'post_updated', array( self::class, 'post_updated' ), 10, 3 );
		add_action( 'before_delete_post', array( self::class, 'before_delete' ), 10, 2 );
		add_action( 'set_object_terms', array( self::class, 'handbook_changed' ), 10, 6 );
	}

	/**
	 * The version of a handbook, such as "2.1.14".
	 *
	 * @param int $term_id Handbook.
	 * @return string
	 */
	public static function get( int $term_id ): string {
		return self::edition( $term_id ) . '.' . self::count( $term_id );
	}

	/**
	 * The edition of a handbook, "1" when none was set.
	 *
	 * @param int $term_id Handbook.
	 * @return string
	 */
	public static function edition( int $term_id ): string {
		$edition = (string) get_term_meta( $term_id, self::META_EDITION, true );
		return self::valid_edition( $edition ) ? $edition : '1';
	}

	/**
	 * The count of a handbook.
	 *
	 * @param int $term_id Handbook.
	 * @return int
	 */
	public static function count( int $term_id ): int {
		return max( 0, (int) get_term_meta( $term_id, self::META_COUNT, true ) );
	}

	/**
	 * Where the edition comes from.
	 *
	 * @param int $term_id Handbook.
	 * @return string self::SOURCE_MANUAL or self::SOURCE_GITHUB.
	 */
	public static function source( int $term_id ): string {
		return self::SOURCE_GITHUB === get_term_meta( $term_id, self::META_SOURCE, true ) ? self::SOURCE_GITHUB : self::SOURCE_MANUAL;
	}

	/**
	 * Whether a text is an edition.
	 *
	 * @param string $edition Text.
	 * @return bool
	 */
	public static function valid_edition( string $edition ): bool {
		return 1 === preg_match( self::EDITION_PATTERN, $edition );
	}

	/**
	 * The line readers see, such as "Handbook version 2.1.14".
	 *
	 * @param int $term_id Handbook.
	 * @return string Plain text.
	 */
	public static function label( int $term_id ): string {
		return sprintf(
			/* translators: %s: version of the handbook, such as 2.1.14. */
			__( 'Handbook version %s', 'living-handbook' ),
			self::get( $term_id )
		);
	}

	/**
	 * Sets a new edition. The count starts again at 0, and the next change
	 * counts, even on the same day.
	 *
	 * @param int    $term_id Handbook.
	 * @param string $edition Edition.
	 * @return bool Whether the edition changed.
	 */
	public static function set_edition( int $term_id, string $edition ): bool {
		if ( ! self::valid_edition( $edition ) || self::edition( $term_id ) === $edition ) {
			return false;
		}
		update_term_meta( $term_id, self::META_EDITION, $edition );
		update_term_meta( $term_id, self::META_COUNT, 0 );
		delete_term_meta( $term_id, self::META_DAY );
		return true;
	}

	/**
	 * Takes over the version a bundle carries, for a handbook the import
	 * created.
	 *
	 * @param int   $term_id Handbook.
	 * @param mixed $version The manifest's handbook.version.
	 * @return void
	 */
	public static function adopt( int $term_id, $version ): void {
		if ( ! is_array( $version ) ) {
			return;
		}
		$edition = isset( $version['edition'] ) && is_string( $version['edition'] ) ? $version['edition'] : '';
		if ( ! self::valid_edition( $edition ) ) {
			return;
		}
		update_term_meta( $term_id, self::META_EDITION, $edition );
		update_term_meta( $term_id, self::META_COUNT, max( 0, (int) ( $version['count'] ?? 0 ) ) );
		delete_term_meta( $term_id, self::META_DAY );
	}

	/**
	 * The version as the bundle carries it.
	 *
	 * @param int $term_id Handbook.
	 * @return array{edition: string, count: int, version: string}
	 */
	public static function for_bundle( int $term_id ): array {
		return array(
			'edition' => self::edition( $term_id ),
			'count'   => self::count( $term_id ),
			'version' => self::get( $term_id ),
		);
	}

	/**
	 * Counts a change to a handbook's published content: once a day.
	 *
	 * @param int $term_id Handbook.
	 * @return void
	 */
	public static function bump( int $term_id ): void {
		if ( $term_id <= 0 ) {
			return;
		}
		$today = wp_date( 'Y-m-d' );
		if ( get_term_meta( $term_id, self::META_DAY, true ) === $today ) {
			return;
		}
		update_term_meta( $term_id, self::META_COUNT, self::count( $term_id ) + 1 );
		update_term_meta( $term_id, self::META_DAY, $today );
	}

	/**
	 * A page published, or taken back: the published content changed.
	 *
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @param mixed  $post       Page.
	 * @return void
	 */
	public static function status_changed( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || ! self::counts( $post ) ) {
			return;
		}
		if ( ( 'publish' === $new_status ) !== ( 'publish' === $old_status ) ) {
			self::bump( Handbooks::for_post( (int) $post->ID ) );
		}
	}

	/**
	 * A published page with another title or text.
	 *
	 * Only the title and the text count: a review, a new excerpt or a
	 * reordering is no new content. Addresses that the file protection
	 * corrects are written past this hook.
	 *
	 * @param int   $post_id Page.
	 * @param mixed $after   After.
	 * @param mixed $before  Before.
	 * @return void
	 */
	public static function post_updated( $post_id, $after, $before ): void {
		if ( ! $after instanceof WP_Post || ! $before instanceof WP_Post || ! self::counts( $after ) ) {
			return;
		}
		if ( 'publish' !== $after->post_status || 'publish' !== $before->post_status ) {
			return;
		}
		if ( $after->post_title !== $before->post_title || $after->post_content !== $before->post_content ) {
			self::bump( Handbooks::for_post( (int) $post_id ) );
		}
	}

	/**
	 * A published page deleted without the trash.
	 *
	 * @param int          $post_id Page.
	 * @param WP_Post|null $post    Page.
	 * @return void
	 */
	public static function before_delete( $post_id, $post = null ): void {
		$post = $post instanceof WP_Post ? $post : get_post( (int) $post_id );
		if ( $post instanceof WP_Post && self::counts( $post ) && 'publish' === $post->post_status ) {
			self::bump( Handbooks::for_post( (int) $post->ID ) );
		}
	}

	/**
	 * A published page moved to another handbook: both changed.
	 *
	 * @param int                   $object_id  Page.
	 * @param mixed                 $terms      Terms given.
	 * @param array<int, int>|mixed $tt_ids     New term taxonomy IDs.
	 * @param string                $taxonomy   Taxonomy.
	 * @param bool                  $append     Append.
	 * @param array<int, int>|mixed $old_tt_ids Old term taxonomy IDs.
	 * @return void
	 */
	public static function handbook_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the signature is the hook's.
		if ( Handbooks::TAXONOMY !== $taxonomy ) {
			return;
		}
		$post = get_post( (int) $object_id );
		if ( ! $post instanceof WP_Post || ! self::counts( $post ) || 'publish' !== $post->post_status ) {
			return;
		}
		$new = array_map( 'intval', (array) $tt_ids );
		$old = array_map( 'intval', (array) $old_tt_ids );
		sort( $new );
		sort( $old );
		if ( $new === $old ) {
			return;
		}
		foreach ( array_unique( array_merge( $new, $old ) ) as $tt_id ) {
			$term = get_term_by( 'term_taxonomy_id', $tt_id, Handbooks::TAXONOMY );
			if ( $term instanceof WP_Term ) {
				self::bump( (int) $term->term_id );
			}
		}
	}

	/**
	 * Whether a post is handbook content that counts.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	private static function counts( WP_Post $post ): bool {
		return Handbook::POST_TYPE === $post->post_type && 'revision' !== $post->post_type;
	}

	/**
	 * The GitHub repositories a handbook's pages are synced from, with the
	 * refs they follow.
	 *
	 * @param int $term_id Handbook.
	 * @return array<string, array<int, string>> "owner/repo" to refs.
	 */
	public static function repositories( int $term_id ): array {
		$ids = get_posts(
			AccessController::internal(
				array(
					'post_type'      => Handbook::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => GitSync::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one query per handbook, on its edit screen and once per sync pass.
					'meta_value'     => GitSync::SOURCE_GITHUB, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see meta_key.
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- see meta_key.
						array(
							'taxonomy' => Handbooks::TAXONOMY,
							'terms'    => $term_id,
						),
					),
				)
			)
		);

		$repos = array();
		foreach ( $ids as $id ) {
			$parts = self::raw_parts( (string) get_post_meta( (int) $id, GitSync::META_URL, true ) );
			if ( null === $parts ) {
				continue;
			}
			$key = $parts['owner'] . '/' . $parts['repo'];
			if ( ! in_array( $parts['ref'], $repos[ $key ] ?? array(), true ) ) {
				$repos[ $key ][] = $parts['ref'];
			}
		}
		ksort( $repos );
		return $repos;
	}

	/**
	 * Owner, repository and ref of a raw.githubusercontent.com address.
	 *
	 * @param string $url Address.
	 * @return array{owner: string, repo: string, ref: string}|null
	 */
	private static function raw_parts( string $url ): ?array {
		if ( 'raw.githubusercontent.com' !== strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
			return null;
		}
		$segments = array_map( 'rawurldecode', explode( '/', ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) ) );
		if ( count( $segments ) < 4 || '' === $segments[0] || '' === $segments[1] || '' === $segments[2] ) {
			return null;
		}
		return array(
			'owner' => $segments[0],
			'repo'  => $segments[1],
			'ref'   => $segments[2],
		);
	}

	/**
	 * The edition a tag names, or '' when it names none.
	 *
	 * @param string $tag Tag.
	 * @return string
	 */
	public static function edition_from_tag( string $tag ): string {
		return 1 === preg_match( self::TAG_PATTERN, $tag, $match ) ? $match[1] : '';
	}

	/**
	 * The highest version tag among names, or '' when none names a version.
	 *
	 * @param array<int, string> $tags Tags.
	 * @return string The tag as named.
	 */
	public static function highest_tag( array $tags ): string {
		$best = '';
		foreach ( $tags as $tag ) {
			$edition = self::edition_from_tag( (string) $tag );
			if ( '' !== $edition && ( '' === $best || version_compare( $edition, self::edition_from_tag( $best ), '>' ) ) ) {
				$best = (string) $tag;
			}
		}
		return $best;
	}

	/**
	 * Takes the edition from GitHub for every handbook that wants it. Runs at
	 * the start of each pass of the scheduled sync.
	 *
	 * @return void
	 */
	public static function refresh_tags(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => Handbooks::TAXONOMY,
				'hide_empty' => false,
				'meta_key'   => self::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- once per sync pass.
				'meta_value' => self::SOURCE_GITHUB, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see meta_key.
			)
		);
		if ( ! is_array( $terms ) ) {
			return;
		}
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				self::refresh_tag( (int) $term->term_id );
			}
		}
	}

	/**
	 * Takes the edition of one handbook from GitHub.
	 *
	 * When the pages of the chosen repository follow a ref that is a version
	 * tag, that ref is the answer and GitHub is not asked. Otherwise one request
	 * lists the repository's tags and the highest version tag wins. When GitHub
	 * does not answer, the edition stays and the reason is kept for the edit
	 * screen.
	 *
	 * @param int $term_id Handbook.
	 * @return void
	 */
	public static function refresh_tag( int $term_id ): void {
		if ( self::SOURCE_GITHUB !== self::source( $term_id ) ) {
			return;
		}
		$repos = self::repositories( $term_id );
		$repo  = (string) get_term_meta( $term_id, self::META_REPO, true );
		if ( ! isset( $repos[ $repo ] ) ) {
			$repo = (string) array_key_first( $repos );
		}
		if ( '' === $repo ) {
			update_term_meta( $term_id, self::META_ERROR, __( 'The handbook has no pages from GitHub.', 'living-handbook' ) );
			return;
		}

		$tag = self::highest_tag( $repos[ $repo ] );
		if ( '' === $tag ) {
			$tags = self::fetch_tags( $repo );
			if ( is_string( $tags ) ) {
				update_term_meta( $term_id, self::META_ERROR, $tags );
				return;
			}
			$tag = self::highest_tag( $tags );
			if ( '' === $tag ) {
				update_term_meta( $term_id, self::META_ERROR, __( 'The repository has no tag that names a version, such as v1.0.', 'living-handbook' ) );
				return;
			}
		}

		delete_term_meta( $term_id, self::META_ERROR );
		if ( get_term_meta( $term_id, self::META_TAG, true ) !== $tag || self::edition_from_tag( $tag ) !== self::edition( $term_id ) ) {
			update_term_meta( $term_id, self::META_TAG, $tag );
			self::set_edition( $term_id, self::edition_from_tag( $tag ) );
		}
	}

	/**
	 * The tag names of a repository, or the reason they could not be read.
	 *
	 * @param string $repo "owner/repo".
	 * @return array<int, string>|string
	 */
	private static function fetch_tags( string $repo ) {
		list( $owner, $name ) = array_pad( explode( '/', $repo, 2 ), 2, '' );
		$url                  = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $name ) . '/tags?per_page=100';

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'limit_response_size' => MB_IN_BYTES,
				'headers'             => array(
					'User-Agent' => 'LivingHandbook',
					'Accept'     => 'application/vnd.github+json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return sprintf(
				/* translators: %d: HTTP status code. */
				__( 'GitHub answered the request for the tags with status %d.', 'living-handbook' ),
				$code
			);
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return __( 'GitHub answered the request for the tags with something that is no list of tags.', 'living-handbook' );
		}
		$names = array();
		foreach ( $data as $entry ) {
			if ( is_array( $entry ) && isset( $entry['name'] ) && is_string( $entry['name'] ) ) {
				$names[] = $entry['name'];
			}
		}
		return $names;
	}
}
