<?php
/**
 * Maintenance metadata for handbook pages.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Meta;

use LivingHandbook\Frontend\FreshnessStatus;
use LivingHandbook\PostType\Handbook;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native custom fields for the freshness mechanic, plus a small meta box.
 */
final class Metadata {

	public const UPDATED    = 'living_handbook_last_updated';
	public const REVIEWED   = 'living_handbook_last_reviewed';
	public const INTERVAL   = 'living_handbook_review_interval';
	public const REVIEWER   = 'living_handbook_reviewer';
	public const TOC_DEPTH  = 'living_handbook_toc_depth';
	public const AI_EXCLUDE = 'living_handbook_ai_exclude';

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_field' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Handbook::POST_TYPE, array( $this, 'save' ) );
		add_action( 'save_post_' . Handbook::POST_TYPE, array( $this, 'set_updated' ) );
	}

	/**
	 * Register the meta fields, REST-readable.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$auth = static function (): bool {
			return current_user_can( 'edit_posts' );
		};

		$fields = array(
			self::UPDATED   => 'string',
			self::REVIEWED  => 'string',
			self::INTERVAL  => 'integer',
			self::REVIEWER  => 'integer',
			self::TOC_DEPTH => 'integer',
		);
		foreach ( $fields as $key => $type ) {
			register_post_meta(
				Handbook::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}

		register_post_meta(
			Handbook::POST_TYPE,
			self::AI_EXCLUDE,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
	}

	/**
	 * Expose an aggregated, read-only status field over REST so a later AI
	 * reader gets the derived freshness (due/overdue), the permalink and the
	 * AI-exclusion flag in one place, next to the raw meta.
	 *
	 * @return void
	 */
	public function register_rest_field(): void {
		register_rest_field(
			Handbook::POST_TYPE,
			'living_handbook_status',
			array(
				'get_callback' => static function ( array $post ): array {
					$post_id = isset( $post['id'] ) ? (int) $post['id'] : 0;
					return array(
						'freshness'  => FreshnessStatus::for_post( $post_id ),
						'reviewed'   => (string) get_post_meta( $post_id, self::REVIEWED, true ),
						'interval'   => (int) get_post_meta( $post_id, self::INTERVAL, true ),
						'permalink'  => (string) get_permalink( $post_id ),
						'ai_exclude' => (bool) get_post_meta( $post_id, self::AI_EXCLUDE, true ),
					);
				},
				'schema'       => array(
					'type'        => 'object',
					'description' => 'Derived handbook status: freshness, review data, permalink and AI-exclusion.',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'freshness'  => array( 'type' => 'string' ),
						'reviewed'   => array( 'type' => 'string' ),
						'interval'   => array( 'type' => 'integer' ),
						'permalink'  => array( 'type' => 'string' ),
						'ai_exclude' => array( 'type' => 'boolean' ),
					),
				),
			)
		);
	}

	/**
	 * Register the editor meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'living_handbook_meta',
			__( 'Handbook maintenance', 'living-handbook' ),
			array( $this, 'render' ),
			Handbook::POST_TYPE,
			'side'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( 'living_handbook_meta', 'living_handbook_meta_nonce' );
		$reviewed   = (string) get_post_meta( $post->ID, self::REVIEWED, true );
		$interval   = (int) get_post_meta( $post->ID, self::INTERVAL, true );
		$reviewer   = (int) get_post_meta( $post->ID, self::REVIEWER, true );
		$depth      = (int) get_post_meta( $post->ID, self::TOC_DEPTH, true );
		$ai_exclude = (bool) get_post_meta( $post->ID, self::AI_EXCLUDE, true );
		?>
		<p>
			<label for="living_handbook_reviewed"><strong><?php esc_html_e( 'Last reviewed', 'living-handbook' ); ?></strong></label><br>
			<input type="date" id="living_handbook_reviewed" name="living_handbook_reviewed" value="<?php echo esc_attr( $reviewed ); ?>" class="widefat">
		</p>
		<p>
			<label for="living_handbook_interval"><strong><?php esc_html_e( 'Review interval (days)', 'living-handbook' ); ?></strong></label><br>
			<input type="number" min="0" step="1" id="living_handbook_interval" name="living_handbook_interval" value="<?php echo esc_attr( (string) $interval ); ?>" class="widefat">
		</p>
		<p>
			<label for="living_handbook_reviewer"><strong><?php esc_html_e( 'Reviewed by', 'living-handbook' ); ?></strong></label><br>
			<?php
			wp_dropdown_users(
				array(
					'name'             => 'living_handbook_reviewer',
					'id'               => 'living_handbook_reviewer',
					'selected'         => $reviewer,
					'show_option_none' => __( 'none', 'living-handbook' ),
					'class'            => 'widefat',
				)
			);
			?>
		</p>
		<p>
			<label for="living_handbook_toc_depth"><strong><?php esc_html_e( 'On this page: heading depth', 'living-handbook' ); ?></strong></label><br>
			<select id="living_handbook_toc_depth" name="living_handbook_toc_depth" class="widefat">
				<option value="0" <?php selected( 0, $depth ); ?>><?php esc_html_e( 'Use the block default', 'living-handbook' ); ?></option>
				<?php for ( $level = 1; $level <= 6; $level++ ) : ?>
					<option value="<?php echo esc_attr( (string) $level ); ?>" <?php selected( $level, $depth ); ?>>
						<?php
						/* translators: %d: heading level, e.g. up to H2. */
						echo esc_html( sprintf( __( 'Up to H%d', 'living-handbook' ), $level ) );
						?>
					</option>
				<?php endfor; ?>
			</select>
		</p>
		<p>
			<label>
				<input type="checkbox" name="living_handbook_ai_exclude" value="1" <?php checked( $ai_exclude ); ?>>
				<?php esc_html_e( 'Exclude this page from AI use', 'living-handbook' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'The last updated date is set automatically on save.', 'living-handbook' ); ?></p>
		<?php
	}

	/**
	 * Save the meta box fields.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['living_handbook_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['living_handbook_meta_nonce'] ) ), 'living_handbook_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$reviewed = isset( $_POST['living_handbook_reviewed'] ) ? sanitize_text_field( wp_unslash( $_POST['living_handbook_reviewed'] ) ) : '';
		if ( '' !== $reviewed && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reviewed ) ) {
			$reviewed = '';
		}
		update_post_meta( $post_id, self::REVIEWED, $reviewed );

		$interval = isset( $_POST['living_handbook_interval'] ) ? absint( $_POST['living_handbook_interval'] ) : 0;
		update_post_meta( $post_id, self::INTERVAL, $interval );

		$reviewer = isset( $_POST['living_handbook_reviewer'] ) ? absint( $_POST['living_handbook_reviewer'] ) : 0;
		update_post_meta( $post_id, self::REVIEWER, $reviewer );

		$depth = isset( $_POST['living_handbook_toc_depth'] ) ? absint( $_POST['living_handbook_toc_depth'] ) : 0;
		if ( $depth > 6 ) {
			$depth = 0;
		}
		update_post_meta( $post_id, self::TOC_DEPTH, $depth );

		$ai_exclude = isset( $_POST['living_handbook_ai_exclude'] );
		update_post_meta( $post_id, self::AI_EXCLUDE, $ai_exclude );
	}

	/**
	 * Set the last updated date automatically on every save.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function set_updated( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::UPDATED, current_time( 'Y-m-d' ) );
	}
}
