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

		// Quick Edit for the three freshness fields in the handbook list.
		add_action( 'manage_' . Handbook::POST_TYPE . '_posts_custom_column', array( $this, 'quick_edit_data' ), 20, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_quick_edit' ) );
		add_action( 'save_post_' . Handbook::POST_TYPE, array( $this, 'save_quick_edit' ) );

		// And the same three in Bulk Edit, which is the difference between
		// maintaining ten pages and maintaining two hundred.
		add_action( 'bulk_edit_custom_box', array( $this, 'bulk_edit_box' ), 10, 2 );
		add_action( 'save_post_' . Handbook::POST_TYPE, array( $this, 'save_bulk_edit' ) );
	}

	/**
	 * Register the meta fields, REST-readable.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		// The object id is what matters: this second line of defence has to ask
		// whether the user may edit this post, not whether they may edit posts at
		// all. WordPress passes the id as the third argument.
		$auth = static function ( $allowed, $meta_key, $object_id ): bool {
			unset( $allowed, $meta_key );
			return current_user_can( 'edit_post', (int) $object_id );
		};

		$fields = array(
			self::UPDATED   => 'string',
			self::REVIEWED  => 'string',
			self::INTERVAL  => 'integer',
			self::REVIEWER  => 'integer',
			self::TOC_DEPTH => 'integer',
		);

		// The meta box validates what it saves, but a REST write goes straight to
		// the meta and would otherwise accept any string as a review date and any
		// number as a heading depth. Same rules as save(), enforced at the field.
		$sanitizers = array(
			self::UPDATED   => array( self::class, 'sanitize_date_value' ),
			self::REVIEWED  => array( self::class, 'sanitize_date_value' ),
			self::INTERVAL  => 'absint',
			self::REVIEWER  => 'absint',
			self::TOC_DEPTH => array( self::class, 'sanitize_toc_depth_value' ),
		);

		foreach ( $fields as $key => $type ) {
			register_post_meta(
				Handbook::POST_TYPE,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitizers[ $key ],
					'auth_callback'     => $auth,
				)
			);
		}

		register_post_meta(
			Handbook::POST_TYPE,
			self::AI_EXCLUDE,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => $auth,
			)
		);
	}

	/**
	 * Keep a date field to YYYY-MM-DD, the format the meta box and the freshness
	 * calculation expect. Anything else becomes an empty value rather than a
	 * string strtotime() would later guess at.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public static function sanitize_date_value( $value ): string {
		$date = trim( (string) $value );
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
	}

	/**
	 * Keep the heading depth within the range the table of contents renders,
	 * matching save(): anything above 6 falls back to 0 (use the default).
	 *
	 * @param mixed $value Incoming value.
	 * @return int
	 */
	public static function sanitize_toc_depth_value( $value ): int {
		$depth = absint( $value );
		return $depth > 6 ? 0 : $depth;
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
		<p class="description"><?php esc_html_e( 'A marker only. It does not block anything by itself; it is published in the page REST data (living_handbook_status), so an AI tool or export that reads the handbook can choose to skip this page.', 'living-handbook' ); ?></p>
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

	/**
	 * Carry the current freshness values into the review column as data
	 * attributes, so the Quick Edit script can prefill its fields (the inline
	 * editor renders them empty and does not know the stored values).
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function quick_edit_data( string $column, int $post_id ): void {
		if ( 'living_handbook_reviewed' !== $column ) {
			return;
		}
		printf(
			'<span class="living-handbook-qe-data" style="display:none" data-reviewed="%1$s" data-interval="%2$d" data-reviewer="%3$d"></span>',
			esc_attr( (string) get_post_meta( $post_id, self::REVIEWED, true ) ),
			(int) get_post_meta( $post_id, self::INTERVAL, true ),
			(int) get_post_meta( $post_id, self::REVIEWER, true )
		);
	}

	/**
	 * Render the three freshness fields in the Quick Edit form: last reviewed,
	 * review interval and reviewer. Hooked once, on the review column.
	 *
	 * @param string $column_name Column being rendered.
	 * @param string $post_type   Post type of the list.
	 * @return void
	 */
	public function quick_edit_box( string $column_name, string $post_type ): void {
		if ( Handbook::POST_TYPE !== $post_type || 'living_handbook_reviewed' !== $column_name ) {
			return;
		}
		wp_nonce_field( 'living_handbook_quick_edit', 'living_handbook_qe_nonce' );
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Last reviewed', 'living-handbook' ); ?></span>
					<input type="date" name="living_handbook_reviewed" value="">
				</label>
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Review interval (days)', 'living-handbook' ); ?></span>
					<input type="number" name="living_handbook_interval" min="0" step="1" value="">
				</label>
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Reviewed by', 'living-handbook' ); ?></span>
					<?php
					wp_dropdown_users(
						array(
							'name'              => 'living_handbook_reviewer',
							'show_option_none'  => __( 'none', 'living-handbook' ),
							'option_none_value' => 0,
						)
					);
					?>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * The three freshness fields in Bulk Edit.
	 *
	 * Quick Edit answers "I reviewed this page today". Bulk Edit answers the
	 * question a large handbook actually raises, "these forty pages are all
	 * reviewed yearly by the same person", which today means opening forty
	 * pages. WordPress offers the responsible role by itself, because that
	 * taxonomy is flat, and offers nothing for meta.
	 *
	 * Every field defaults to "leave unchanged" rather than to empty, because a
	 * bulk action that silently clears a field it was not asked about is the one
	 * mistake in this screen that cannot be undone from the screen.
	 *
	 * @param string $column_name Column the box is rendered for.
	 * @param string $post_type   Post type of the list.
	 * @return void
	 */
	public function bulk_edit_box( string $column_name, string $post_type ): void {
		if ( Handbook::POST_TYPE !== $post_type || 'living_handbook_reviewed' !== $column_name ) {
			return;
		}
		wp_nonce_field( 'living_handbook_bulk_edit', 'living_handbook_be_nonce' );
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Last reviewed', 'living-handbook' ); ?></span>
					<input type="date" name="living_handbook_reviewed" value="">
				</label>
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Review interval (days)', 'living-handbook' ); ?></span>
					<input type="number" name="living_handbook_interval" min="0" step="1" value="" placeholder="<?php esc_attr_e( 'unchanged', 'living-handbook' ); ?>">
				</label>
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Reviewed by', 'living-handbook' ); ?></span>
					<?php
					wp_dropdown_users(
						array(
							'name'              => 'living_handbook_reviewer',
							'show_option_none'  => __( '— No change —', 'living-handbook' ),
							'option_none_value' => -1,
							'selected'          => -1,
						)
					);
					?>
				</label>
				<p class="description"><?php esc_html_e( 'Empty fields are left as they are on each page.', 'living-handbook' ); ?></p>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Apply the Bulk Edit fields to one page.
	 *
	 * Guarded by its own nonce and by the presence of WordPress's bulk_edit
	 * marker, so this path cannot run from an ordinary save or from Quick Edit,
	 * which has a nonce of its own. A field left empty is not written: bulk
	 * editing forty pages must not clear the review date of the thirty-nine
	 * whose date was not the point.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_bulk_edit( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['bulk_edit'] ) ) {
			return;
		}
		if ( ! isset( $_REQUEST['living_handbook_be_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['living_handbook_be_nonce'] ) ), 'living_handbook_bulk_edit' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$reviewed = isset( $_REQUEST['living_handbook_reviewed'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['living_handbook_reviewed'] ) ) : '';
		if ( '' !== $reviewed && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reviewed ) ) {
			update_post_meta( $post_id, self::REVIEWED, $reviewed );
		}

		$interval = isset( $_REQUEST['living_handbook_interval'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['living_handbook_interval'] ) ) : '';
		if ( '' !== $interval ) {
			update_post_meta( $post_id, self::INTERVAL, absint( $interval ) );
		}

		$reviewer = isset( $_REQUEST['living_handbook_reviewer'] ) ? (int) $_REQUEST['living_handbook_reviewer'] : -1;
		if ( $reviewer >= 0 ) {
			update_post_meta( $post_id, self::REVIEWER, $reviewer );
		}
	}

	/**
	 * Enqueue the Quick Edit prefill script, only on the handbook list screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_quick_edit( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || Handbook::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script(
			'living-handbook-quick-edit',
			LIVING_HANDBOOK_URL . 'assets/js/quick-edit.js',
			array( 'jquery', 'inline-edit-post' ),
			LIVING_HANDBOOK_VERSION,
			true
		);
	}

	/**
	 * Save the three freshness fields submitted from Quick Edit. Guarded by its
	 * own nonce, which is absent on a normal editor save and on bulk edit, so
	 * this path runs for the inline Quick Edit only.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_quick_edit( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['living_handbook_qe_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['living_handbook_qe_nonce'] ) ), 'living_handbook_quick_edit' ) ) {
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
	}
}
