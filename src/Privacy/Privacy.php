<?php
/**
 * Personal data the plugin keeps, and the WordPress privacy tools for it.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Privacy;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Feedback\Feedback;
use LivingHandbook\Meta\Metadata;
use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exporter, eraser, policy text and account clean-up for the two places the
 * plugin stores a user id.
 *
 * - The voter list of a page (Feedback::VOTERS): the ids of the signed-in
 *   people who answered "Was this helpful?", kept so each of them counts once.
 *   Which answer someone gave is not stored with the id, only the two totals.
 * - The reviewer of a page (Metadata::REVIEWER): the person who last reviewed
 *   it, shown with name and avatar in the page footer.
 *
 * Neither was reachable through Tools > Export Personal Data or Erase Personal
 * Data, and the privacy policy guide said nothing about the plugin. A deleted
 * account also stayed in every voter list.
 *
 * The two are not treated alike on erasure, on purpose. Taking an id out of a
 * voter list loses nothing: the totals stay, and the only consequence is that
 * the person could vote again, which a deleted account cannot. Taking a
 * reviewer off a page removes who is accountable for the last review, which is
 * the page's editorial record rather than the person's data about themselves.
 * Whether that should go is a decision for the site, not for an automatic
 * eraser, so the reviewer is exported and reported as retained, with the
 * reason, and an administrator who decides otherwise changes it on the page.
 */
final class Privacy {

	/**
	 * Pages handled per exporter or eraser call. WordPress calls again with the
	 * next page number until "done", so a site with many pages is processed in
	 * steps that each fit in one request.
	 */
	private const BATCH = 100;

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_action( 'deleted_user', array( $this, 'forget_deleted_user' ) );
	}

	/**
	 * Add the two exporters.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporters( array $exporters ): array {
		$exporters['living-handbook-feedback'] = array(
			'exporter_friendly_name' => __( 'Living Handbook feedback', 'living-handbook' ),
			'callback'               => array( $this, 'export_feedback' ),
		);
		$exporters['living-handbook-reviewer'] = array(
			'exporter_friendly_name' => __( 'Living Handbook reviews', 'living-handbook' ),
			'callback'               => array( $this, 'export_reviews' ),
		);

		return $exporters;
	}

	/**
	 * Add the eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_erasers( array $erasers ): array {
		$erasers['living-handbook'] = array(
			'eraser_friendly_name' => __( 'Living Handbook', 'living-handbook' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * The pages this person voted on.
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Batch number, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export_feedback( string $email_address, int $page = 1 ): array {
		$user_id = self::user_id( $email_address );
		if ( 0 === $user_id ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$batch = self::pages_with_voters( $page );
		$data  = array();
		foreach ( $batch as $post_id ) {
			if ( ! in_array( $user_id, self::voters( $post_id ), true ) ) {
				continue;
			}
			$data[] = array(
				'group_id'          => 'living-handbook-feedback',
				'group_label'       => __( 'Handbook feedback', 'living-handbook' ),
				'group_description' => __( 'Handbook pages on which you answered "Was this helpful?". Your account is recorded so that your answer counts once; whether you answered yes or no is not stored with it.', 'living-handbook' ),
				'item_id'           => 'living-handbook-feedback-' . $post_id,
				'data'              => self::page_fields( $post_id ),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $batch ) < self::BATCH,
		);
	}

	/**
	 * The pages this person is recorded as reviewer of.
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Batch number, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export_reviews( string $email_address, int $page = 1 ): array {
		$user_id = self::user_id( $email_address );
		if ( 0 === $user_id ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$batch = self::pages_reviewed_by( $user_id, $page );
		$data  = array();
		foreach ( $batch as $post_id ) {
			$fields   = self::page_fields( $post_id );
			$fields[] = array(
				'name'  => __( 'Last reviewed', 'living-handbook' ),
				'value' => (string) get_post_meta( $post_id, Metadata::REVIEWED, true ),
			);
			$data[]   = array(
				'group_id'          => 'living-handbook-reviewer',
				'group_label'       => __( 'Handbook reviews', 'living-handbook' ),
				'group_description' => __( 'Handbook pages that name you as the person who last reviewed them. Your name and avatar are shown in the footer of these pages.', 'living-handbook' ),
				'item_id'           => 'living-handbook-reviewer-' . $post_id,
				'data'              => $fields,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $batch ) < self::BATCH,
		);
	}

	/**
	 * Remove the person from every voter list, and report the reviews kept.
	 *
	 * The batches run over every page that has a voter list, whoever is on it,
	 * ordered by id. That set does not shrink while the eraser works, because a
	 * list that ends up empty is kept as an empty list, so the next batch number
	 * still points at the next pages.
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Batch number, from 1.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$user_id = self::user_id( $email_address );
		if ( 0 === $user_id ) {
			return $result;
		}

		$batch = self::pages_with_voters( $page );
		foreach ( $batch as $post_id ) {
			if ( self::remove_voter( $post_id, $user_id ) ) {
				$result['items_removed'] = true;
			}
		}
		$result['done'] = count( $batch ) < self::BATCH;

		// Reported once, on the first batch.
		if ( 1 === $page ) {
			$reviewed = self::pages_reviewed_by( $user_id, 1, -1 );
			if ( array() !== $reviewed ) {
				$result['items_retained'] = true;
				$result['messages'][]     = sprintf(
					/* translators: %d: number of handbook pages. */
					_n(
						'Living Handbook: this person is still named as the reviewer of %d page. Who reviewed a page is part of its editorial record, so it is not erased automatically. Change the reviewer on the page if it should go.',
						'Living Handbook: this person is still named as the reviewer of %d pages. Who reviewed a page is part of its editorial record, so it is not erased automatically. Change the reviewer on the pages if it should go.',
						count( $reviewed ),
						'living-handbook'
					),
					count( $reviewed )
				);
			}
		}

		return $result;
	}

	/**
	 * Take a deleted account out of every voter list.
	 *
	 * The reviewer is left as it is, for the reason given on the class: it is
	 * the page's record. A reviewer id without an account behind it shows no
	 * name and no avatar in the footer, so nothing personal is displayed.
	 *
	 * @param int $user_id The deleted user's id.
	 * @return void
	 */
	public function forget_deleted_user( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$candidates = self::pages_that_may_list( $user_id );
		if ( array() === $candidates ) {
			return;
		}
		update_meta_cache( 'post', $candidates );
		foreach ( $candidates as $post_id ) {
			self::remove_voter( $post_id, $user_id );
		}
	}

	/**
	 * Handbook pages whose voter list may hold this id.
	 *
	 * Deleting an account used to read the voter list of every page that had
	 * one, a query per page, inside the request that deletes the user. The
	 * list is stored serialised, an id as "i:<id>;" (or "s:<n>:"<id>";" in data
	 * written as strings), so the database can narrow it down to the pages that
	 * contain that text. That is a superset, since an array key is written the
	 * same way; remove_voter() then checks each list exactly.
	 *
	 * Every status, the trash included: a page in the trash still holds the ids.
	 *
	 * @param int $user_id User id.
	 * @return array<int, int>
	 */
	private static function pages_that_may_list( int $user_id ): array {
		global $wpdb;

		$id = (string) $user_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one query across all voter lists instead of one per page; account deletion, not a page view.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s ) ORDER BY pm.post_id ASC",
				Feedback::VOTERS,
				Handbook::POST_TYPE,
				'%' . $wpdb->esc_like( 'i:' . $id . ';' ) . '%',
				'%' . $wpdb->esc_like( 's:' . strlen( $id ) . ':"' . $id . '";' ) . '%'
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Suggest the plugin's part of the privacy policy.
	 *
	 * @return void
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content( __( 'Living Handbook', 'living-handbook' ), wp_kses_post( wpautop( self::policy_text(), false ) ) );
	}

	/**
	 * The suggested policy text.
	 *
	 * @return string
	 */
	public static function policy_text(): string {
		$paragraphs = array(
			'<strong>' . __( 'Feedback on handbook pages', 'living-handbook' ) . '</strong> '
				. __( 'When you are signed in and answer "Was this helpful?" on a handbook page, we store your user account with that page so that your answer is counted only once. Whether you answered yes or no is not stored with your account, only the total of each answer. Without signing in, an answer is counted without any personal data: no cookie, no IP address and no account.', 'living-handbook' ),
			'<strong>' . __( 'Who edited and reviewed a page', 'living-handbook' ) . '</strong> '
				. __( 'The footer of a handbook page can show who created, last updated and last reviewed it, with their name and profile picture. The reviewer is stored with the page.', 'living-handbook' ),
			'<strong>' . __( 'Profile pictures', 'living-handbook' ) . '</strong> '
				. __( 'Profile pictures in the page footer are provided by Gravatar, a service of Automattic, unless this site uses a different avatar setting. To show one, your browser loads the image from gravatar.com, which receives a hash of the person\'s email address, your IP address and the usual browser information.', 'living-handbook' ),
			'<strong>' . __( 'Content from GitHub', 'living-handbook' ) . '</strong> '
				. __( 'Handbook pages can be imported and kept up to date from GitHub. These requests are made by this website\'s server to api.github.com, raw.githubusercontent.com and codeload.github.com. They name the repository and the files, and contain no data about the visitors of this site.', 'living-handbook' ),
			'<strong>' . __( 'Progress through learning paths', 'living-handbook' ) . '</strong> '
				. __( 'Which lessons of a learning path you have opened is kept in your browser\'s local storage, so the path can mark them as read. It is not sent to this website and is gone when you clear your browser data.', 'living-handbook' ),
		);

		return implode( "\n\n", $paragraphs );
	}

	/**
	 * The account behind an email address, or 0.
	 *
	 * @param string $email_address Email address.
	 * @return int
	 */
	private static function user_id( string $email_address ): int {
		$user = get_user_by( 'email', $email_address );

		return $user instanceof \WP_User ? (int) $user->ID : 0;
	}

	/**
	 * One batch of handbook pages that have a voter list, in id order.
	 *
	 * Every status, the trash included: a page in the trash still holds the ids.
	 *
	 * @param int $page Batch number, from 1.
	 * @return array<int, int>
	 */
	private static function pages_with_voters( int $page ): array {
		$ids = array_map(
			'intval',
			get_posts(
				AccessController::internal(
					array(
						'post_type'      => Handbook::POST_TYPE,
						'post_status'    => array_keys( get_post_stati() ),
						'meta_key'       => Feedback::VOTERS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Privacy request, not a page view.
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'posts_per_page' => self::BATCH,
						'paged'          => max( 1, $page ),
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				)
			)
		);
		// The caller reads every list of the batch; one query for all of them.
		update_meta_cache( 'post', $ids );

		return $ids;
	}

	/**
	 * Handbook pages that name this person as reviewer.
	 *
	 * @param int $user_id  User id.
	 * @param int $page     Batch number, from 1.
	 * @param int $per_page Pages per batch; -1 for all.
	 * @return array<int, int>
	 */
	private static function pages_reviewed_by( int $user_id, int $page, int $per_page = self::BATCH ): array {
		return array_map(
			'intval',
			get_posts(
				AccessController::internal(
					array(
						'post_type'      => Handbook::POST_TYPE,
						'post_status'    => array_keys( get_post_stati() ),
						'meta_key'       => Metadata::REVIEWER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Privacy request, not a page view.
						'meta_value'     => (string) $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Privacy request, not a page view.
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'posts_per_page' => $per_page,
						'paged'          => max( 1, $page ),
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				)
			)
		);
	}

	/**
	 * The voter list of a page, as integers.
	 *
	 * @param int $post_id Page id.
	 * @return array<int, int>
	 */
	private static function voters( int $post_id ): array {
		$voters = get_post_meta( $post_id, Feedback::VOTERS, true );

		return is_array( $voters ) ? array_map( 'intval', $voters ) : array();
	}

	/**
	 * Take one id out of a page's voter list. The counts stay as they are.
	 *
	 * @param int $post_id Page id.
	 * @param int $user_id User id.
	 * @return bool Whether the id was there.
	 */
	private static function remove_voter( int $post_id, int $user_id ): bool {
		$voters = self::voters( $post_id );
		if ( ! in_array( $user_id, $voters, true ) ) {
			return false;
		}

		update_post_meta(
			$post_id,
			Feedback::VOTERS,
			array_values( array_filter( $voters, static fn( int $id ): bool => $id !== $user_id ) )
		);

		return true;
	}

	/**
	 * Title and address of a page, for an export item.
	 *
	 * @param int $post_id Page id.
	 * @return array<int, array{name: string, value: string}>
	 */
	private static function page_fields( int $post_id ): array {
		return array(
			array(
				'name'  => __( 'Page', 'living-handbook' ),
				'value' => get_the_title( $post_id ),
			),
			array(
				'name'  => __( 'Address', 'living-handbook' ),
				'value' => (string) get_permalink( $post_id ),
			),
		);
	}
}
