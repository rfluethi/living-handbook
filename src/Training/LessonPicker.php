<?php
/**
 * Choosing the lessons of a learning path in the editor.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Training;

use LivingHandbook\Access\AccessController;
use LivingHandbook\Handbook\Handbooks;
use LivingHandbook\PostType\Handbook;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The lesson picker: a search field that loads matches on demand, and the
 * chosen lessons as an ordered list beside it.
 *
 * Why a search field and not a list of all pages: a handbook of two thousand
 * pages rendered as a select box is the same mistake the core page-parent
 * dropdown makes, and it was measured on this project (2441 ms against 20 ms,
 * log of 2026-08-08). The search runs against a REST route that is scoped to
 * the handbook of the path and checks access for every hit, so it can never
 * offer a page the editor may not read.
 *
 * The order is changed with Move up and Move down buttons rather than by
 * dragging. That is not a shortcut: a drag needs a pointer, and WCAG 2.2
 * requires a single-pointer alternative for it anyway (SC 2.5.7), so buttons
 * that work with the keyboard, with a screen reader and with a mouse are the
 * whole feature rather than half of it. Dragging can be added later; nothing in
 * the data model or the markup stands in its way.
 */
final class LessonPicker {

	/**
	 * Nonce action and field name of the meta box.
	 */
	private const NONCE_ACTION = 'living_handbook_lessons';
	private const NONCE_FIELD  = 'living_handbook_lessons_nonce';

	/**
	 * The form field carrying the ordered lesson ids, comma separated.
	 */
	private const FIELD = 'living_handbook_lessons';

	/**
	 * The REST route that answers the lesson search.
	 */
	public const REST_ROUTE = '/training-lessons';

	/**
	 * How many matches one search returns.
	 */
	private const RESULTS = 10;

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Training::POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Register the REST route behind the search field.
	 *
	 * The permission callback asks for edit rights on the path being edited,
	 * which is the only context this route is used from. The route additionally
	 * checks every hit against can_view_post(), so an editor who may edit the
	 * path but not read a restricted handbook still cannot list its pages.
	 *
	 * @return void
	 */
	public function register_rest(): void {
		register_rest_route(
			'living-handbook/v1',
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_search' ),
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					$training_id = (int) $request->get_param( 'training_id' );

					return $training_id > 0 && current_user_can( 'edit_post', $training_id );
				},
				'args'                => array(
					'training_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'q'           => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST handler: pages of the path's handbook whose title matches.
	 *
	 * Searching titles only, not full text: the picker answers "which page did I
	 * mean", and a full-text search on a large handbook returns pages whose title
	 * gives no clue why they matched.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_search( WP_REST_Request $request ): WP_REST_Response {
		$training_id = (int) $request->get_param( 'training_id' );
		$training    = get_post( $training_id );
		if ( ! $training instanceof WP_Post || Training::POST_TYPE !== $training->post_type ) {
			return new WP_REST_Response( array( 'results' => array() ), 400 );
		}

		$handbook = Handbooks::for_post( $training_id );
		$term     = $handbook > 0 ? get_term( $handbook, Handbooks::TAXONOMY ) : null;
		if ( ! $term instanceof WP_Term ) {
			return new WP_REST_Response(
				array(
					'results' => array(),
					'reason'  => 'no-handbook',
				),
				200
			);
		}

		$user_id = get_current_user_id();
		if ( ! AccessController::can_view_term( $handbook, $user_id ) ) {
			return new WP_REST_Response( array( 'results' => array() ), 403 );
		}

		$search = (string) $request->get_param( 'q' );

		$args = array(
			'post_type'              => Handbook::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => self::RESULTS,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy'         => Handbooks::TAXONOMY,
					'field'            => 'term_id',
					'terms'            => $handbook,
					'include_children' => false,
				),
			),
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
			// Restrict the search to titles: without this a word from the body of
			// a hundred pages buries the page actually named after it.
			add_filter( 'posts_search', array( __CLASS__, 'search_titles_only' ), 10, 2 );
		}

		$query = new WP_Query( $args );
		remove_filter( 'posts_search', array( __CLASS__, 'search_titles_only' ), 10 );

		$results = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post || ! AccessController::can_view_post( $post->ID, $user_id ) ) {
				continue;
			}
			$results[] = array(
				'id'    => (int) $post->ID,
				'title' => html_entity_decode( get_the_title( $post->ID ), ENT_QUOTES, 'UTF-8' ),
			);
		}

		return new WP_REST_Response( array( 'results' => $results ), 200 );
	}

	/**
	 * Narrow a search to post titles.
	 *
	 * Core searches title, excerpt and content at once, and there is no argument
	 * for title-only, so the WHERE clause is rebuilt. It is added around one
	 * query and removed straight after, so nothing else on the request is
	 * affected.
	 *
	 * @param string   $search Search SQL.
	 * @param WP_Query $query  The query being built.
	 * @return string
	 */
	public static function search_titles_only( string $search, WP_Query $query ): string {
		global $wpdb;

		$terms = $query->get( 's' );
		if ( ! is_string( $terms ) || '' === $terms ) {
			return $search;
		}

		$like = '%' . $wpdb->esc_like( $terms ) . '%';

		return $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s ", $like );
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'living_handbook_lessons',
			__( 'Lessons', 'living-handbook' ),
			array( $this, 'render' ),
			Training::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Current learning path.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$handbook = Handbooks::for_post( $post->ID );
		$lessons  = Lessons::stored( $post->ID );
		?>
		<div class="living-handbook-lessons" data-training="<?php echo esc_attr( (string) $post->ID ); ?>">
			<input type="hidden" name="<?php echo esc_attr( self::FIELD ); ?>" value="<?php echo esc_attr( implode( ',', $lessons ) ); ?>" class="living-handbook-lessons__value">

			<?php if ( $handbook <= 0 ) : ?>
				<p class="living-handbook-lessons__hint">
					<?php esc_html_e( 'Choose a handbook for this learning path and save it once. The lessons are searched inside that handbook, so there is nothing to search in yet.', 'living-handbook' ); ?>
				</p>
			<?php else : ?>
				<p>
					<label for="living-handbook-lessons-search"><strong><?php esc_html_e( 'Add a lesson', 'living-handbook' ); ?></strong></label><br>
					<input type="search" id="living-handbook-lessons-search" class="living-handbook-lessons__search regular-text" autocomplete="off"
						placeholder="<?php esc_attr_e( 'Search pages of this handbook', 'living-handbook' ); ?>"
						aria-describedby="living-handbook-lessons-help">
				</p>
				<p class="description" id="living-handbook-lessons-help">
					<?php esc_html_e( 'Only published pages of the handbook this learning path belongs to can be added. A page stays an ordinary handbook page; the path only says in which order to read.', 'living-handbook' ); ?>
				</p>
				<ul class="living-handbook-lessons__results" role="listbox" aria-label="<?php esc_attr_e( 'Search results', 'living-handbook' ); ?>"></ul>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Lessons in this learning path', 'living-handbook' ); ?></h3>
			<ol class="living-handbook-lessons__list">
				<?php foreach ( $lessons as $lesson_id ) : ?>
					<?php
					$title = get_the_title( $lesson_id );
					if ( '' === $title ) {
						/* translators: %d: id of a page that no longer exists. */
						$title = sprintf( __( 'Page %d is gone', 'living-handbook' ), $lesson_id );
					}
					?>
					<li data-id="<?php echo esc_attr( (string) $lesson_id ); ?>">
						<span class="living-handbook-lessons__title"><?php echo esc_html( $title ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
			<p class="living-handbook-lessons__empty description"<?php echo array() === $lessons ? '' : ' hidden'; ?>>
				<?php esc_html_e( 'No lessons yet.', 'living-handbook' ); ?>
			</p>
			<noscript>
				<p class="description"><?php esc_html_e( 'Choosing lessons needs JavaScript. The list above shows what is stored, and saving the page keeps it unchanged.', 'living-handbook' ); ?></p>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Save the lesson list.
	 *
	 * The ids arrive as one comma separated field, which is what keeps the order
	 * a first-class part of the value instead of something reconstructed from the
	 * order of form fields. Everything else is checked in Lessons::sanitize().
	 *
	 * @param int $post_id Learning path id.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';
		$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

		Lessons::store( $post_id, array_values( $ids ) );
	}

	/**
	 * Load the picker script on the learning path editor and nowhere else.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || Training::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_register_script(
			'living-handbook-training-lessons',
			LIVING_HANDBOOK_URL . 'assets/js/training-lessons.js',
			array( 'wp-api-fetch', 'wp-dom-ready', 'wp-i18n' ),
			LIVING_HANDBOOK_VERSION,
			true
		);
		wp_enqueue_script( 'living-handbook-training-lessons' );
		wp_localize_script(
			'living-handbook-training-lessons',
			'lhTraining',
			array( 'searchPath' => '/living-handbook/v1' . self::REST_ROUTE )
		);
		wp_set_script_translations( 'living-handbook-training-lessons', 'living-handbook', LIVING_HANDBOOK_DIR . 'languages' );
	}
}
