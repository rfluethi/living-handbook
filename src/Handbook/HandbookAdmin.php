<?php
/**
 * Admin UI for a handbook's frontend access configuration.
 *
 * Adds fields to the `handbook_set` term add and edit screens so an editor can
 * set the visibility (public, members, restricted) and, for restricted
 * handbooks, the allowed roles and users. The roles and users fields are only
 * shown when the visibility is restricted (toggled by an enqueued script on the
 * term screens). The term list table shows an Access column with the configured
 * visibility.
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Handbook;

use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backend fields for the per-handbook access configuration.
 */
final class HandbookAdmin {

	/**
	 * Registers the hooks with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( Handbooks::TAXONOMY . '_add_form_fields', array( $this, 'render_add_fields' ) );
		add_action( Handbooks::TAXONOMY . '_edit_form_fields', array( $this, 'render_edit_fields' ) );
		add_action( 'created_' . Handbooks::TAXONOMY, array( $this, 'save' ) );
		add_action( 'edited_' . Handbooks::TAXONOMY, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_toggle' ) );
		add_filter( 'manage_edit-' . Handbooks::TAXONOMY . '_columns', array( $this, 'access_column' ) );
		add_filter( 'manage_' . Handbooks::TAXONOMY . '_custom_column', array( $this, 'access_column_value' ), 10, 3 );
	}

	/**
	 * Enqueues the visibility toggle script on the handbook term add and edit
	 * screens only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_toggle( string $hook ): void {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( null === $screen || Handbooks::TAXONOMY !== $screen->taxonomy ) {
			return;
		}
		wp_enqueue_script(
			'living-handbook-access',
			LIVING_HANDBOOK_URL . 'assets/js/handbook-access.js',
			array(),
			LIVING_HANDBOOK_VERSION,
			true
		);
	}

	/**
	 * Renders the access fields on the "add term" screen.
	 *
	 * @return void
	 */
	public function render_add_fields(): void {
		wp_nonce_field( 'living_handbook_access', 'living_handbook_access_nonce' );
		?>
		<div class="form-field">
			<label for="living_handbook_visibility"><?php esc_html_e( 'Frontend visibility', 'living-handbook' ); ?></label>
			<?php $this->visibility_select( Handbooks::VISIBILITY_MEMBERS ); ?>
		</div>
		<div class="form-field js-lh-restricted">
			<label><?php esc_html_e( 'Allowed roles (restricted only)', 'living-handbook' ); ?></label>
			<?php $this->roles_checkboxes( array() ); ?>
		</div>
		<div class="form-field js-lh-restricted">
			<label for="living_handbook_users_raw"><?php esc_html_e( 'Allowed users (restricted only)', 'living-handbook' ); ?></label>
			<input type="text" name="living_handbook_users_raw" id="living_handbook_users_raw" value="">
			<p class="description"><?php esc_html_e( 'Comma-separated user logins or IDs.', 'living-handbook' ); ?></p>
		</div>
		<div class="form-field">
			<label for="living_handbook_comments"><?php esc_html_e( 'Comments', 'living-handbook' ); ?></label>
			<?php $this->comments_select( Handbooks::COMMENTS_INHERIT ); ?>
			<p class="description"><?php echo esc_html( self::comments_hint() ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the access fields on the "edit term" screen.
	 *
	 * @param WP_Term $term The term being edited.
	 * @return void
	 */
	public function render_edit_fields( WP_Term $term ): void {
		$visibility = (string) get_term_meta( $term->term_id, Handbooks::META_VISIBILITY, true );
		if ( '' === $visibility ) {
			$visibility = Handbooks::VISIBILITY_MEMBERS;
		}
		$roles  = (array) get_term_meta( $term->term_id, Handbooks::META_ROLES, true );
		$users  = array_map( 'intval', (array) get_term_meta( $term->term_id, Handbooks::META_USERS, true ) );
		$logins = array();
		foreach ( $users as $user_id ) {
			$user = get_userdata( $user_id );
			if ( false !== $user ) {
				$logins[] = $user->user_login;
			}
		}

		wp_nonce_field( 'living_handbook_access', 'living_handbook_access_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="living_handbook_visibility"><?php esc_html_e( 'Frontend visibility', 'living-handbook' ); ?></label></th>
			<td><?php $this->visibility_select( $visibility ); ?></td>
		</tr>
		<tr class="form-field js-lh-restricted">
			<th scope="row"><?php esc_html_e( 'Allowed roles (restricted only)', 'living-handbook' ); ?></th>
			<td><?php $this->roles_checkboxes( $roles ); ?></td>
		</tr>
		<tr class="form-field js-lh-restricted">
			<th scope="row"><label for="living_handbook_users_raw"><?php esc_html_e( 'Allowed users (restricted only)', 'living-handbook' ); ?></label></th>
			<td>
				<input type="text" name="living_handbook_users_raw" id="living_handbook_users_raw" value="<?php echo esc_attr( implode( ', ', $logins ) ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Comma-separated user logins or IDs.', 'living-handbook' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="living_handbook_comments"><?php esc_html_e( 'Comments', 'living-handbook' ); ?></label></th>
			<td>
				<?php $this->comments_select( Handbooks::comments_mode( (int) $term->term_id ) ); ?>
				<p class="description"><?php echo esc_html( self::comments_hint() ); ?></p>
			</td>
		</tr>
		<?php
		self::version_fields( $term );
	}

	/**
	 * The version fields of a handbook (E-0084): the current version, the
	 * edition by hand or from the GitHub tag, and the repository.
	 *
	 * @param WP_Term $term Handbook.
	 * @return void
	 */
	private static function version_fields( WP_Term $term ): void {
		$term_id = (int) $term->term_id;
		$source  = HandbookVersion::source( $term_id );
		$repos   = HandbookVersion::repositories( $term_id );
		$chosen  = (string) get_term_meta( $term_id, HandbookVersion::META_REPO, true );
		$tag     = (string) get_term_meta( $term_id, HandbookVersion::META_TAG, true );
		$error   = (string) get_term_meta( $term_id, HandbookVersion::META_ERROR, true );
		$github  = HandbookVersion::SOURCE_GITHUB === $source && array() !== $repos;
		?>
		<tr class="form-field">
			<th scope="row"><label for="living_handbook_version_edition"><?php esc_html_e( 'Version', 'living-handbook' ); ?></label></th>
			<td>
				<p><strong><?php echo esc_html( HandbookVersion::get( $term_id ) ); ?></strong></p>
				<p>
					<label for="living_handbook_version_edition"><?php esc_html_e( 'Edition', 'living-handbook' ); ?></label>
					<input type="text" name="living_handbook_version_edition" id="living_handbook_version_edition" value="<?php echo esc_attr( HandbookVersion::edition( $term_id ) ); ?>" class="small-text" pattern="\d{1,6}(\.\d{1,6}){0,2}" inputmode="decimal"<?php disabled( $github ); ?>>
				</p>
				<p class="description"><?php esc_html_e( 'The version is the edition and a count that goes up by one on every day the published content of the handbook changes. A new edition starts the count again at 0. Readers see the version on the entry page, below every page and in the exports, without a date.', 'living-handbook' ); ?></p>
				<?php if ( array() !== $repos ) : ?>
					<p>
						<label for="living_handbook_version_source"><?php esc_html_e( 'Edition comes', 'living-handbook' ); ?></label>
						<select name="living_handbook_version_source" id="living_handbook_version_source">
							<option value="<?php echo esc_attr( HandbookVersion::SOURCE_MANUAL ); ?>"<?php selected( $source, HandbookVersion::SOURCE_MANUAL ); ?>><?php esc_html_e( 'by hand', 'living-handbook' ); ?></option>
							<option value="<?php echo esc_attr( HandbookVersion::SOURCE_GITHUB ); ?>"<?php selected( $source, HandbookVersion::SOURCE_GITHUB ); ?>><?php esc_html_e( 'from the GitHub tag', 'living-handbook' ); ?></option>
						</select>
						<?php if ( count( $repos ) > 1 ) : ?>
							<label for="living_handbook_version_repo" class="screen-reader-text"><?php esc_html_e( 'Repository', 'living-handbook' ); ?></label>
							<select name="living_handbook_version_repo" id="living_handbook_version_repo">
								<?php foreach ( array_keys( $repos ) as $repo ) : ?>
									<option value="<?php echo esc_attr( $repo ); ?>"<?php selected( $chosen, $repo ); ?>><?php echo esc_html( $repo ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</p>
					<p class="description"><?php esc_html_e( 'From the GitHub tag: the highest tag that names a version, such as v2.1, becomes the edition. If the pages follow such a tag, it counts directly; otherwise the scheduled sync asks GitHub once per pass.', 'living-handbook' ); ?></p>
					<?php if ( $github && '' !== $tag ) : ?>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: name of the tag on GitHub. */
									__( 'The edition comes from the tag %s.', 'living-handbook' ),
									$tag
								)
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( $github && '' !== $error ) : ?>
						<div class="notice notice-warning inline"><p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: why the tags could not be read. */
									__( 'The tag could not be read: %s The edition stays as it was.', 'living-handbook' ),
									$error
								)
							);
							?>
						</p></div>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Adds the Access column to the handbook list table.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function access_column( array $columns ): array {
		$columns['lh_access']  = __( 'Access', 'living-handbook' );
		$columns['lh_version'] = __( 'Version', 'living-handbook' );
		return $columns;
	}

	/**
	 * Returns the Access column value for a handbook.
	 *
	 * @param string $content Existing content.
	 * @param string $column  Column key.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function access_column_value( string $content, string $column, int $term_id ): string {
		if ( 'lh_version' === $column ) {
			return esc_html( HandbookVersion::get( $term_id ) );
		}
		if ( 'lh_access' !== $column ) {
			return $content;
		}

		$visibility = (string) get_term_meta( $term_id, Handbooks::META_VISIBILITY, true );
		if ( '' === $visibility ) {
			$visibility = Handbooks::VISIBILITY_MEMBERS;
		}
		if ( Handbooks::VISIBILITY_PUBLIC === $visibility ) {
			return esc_html__( 'Public', 'living-handbook' );
		}
		if ( Handbooks::VISIBILITY_MEMBERS === $visibility ) {
			return esc_html__( 'All members', 'living-handbook' );
		}

		$names  = wp_roles()->get_names();
		$labels = array();
		foreach ( (array) get_term_meta( $term_id, Handbooks::META_ROLES, true ) as $role ) {
			if ( isset( $names[ $role ] ) ) {
				$labels[] = translate_user_role( $names[ $role ] );
			}
		}
		$users = array_filter( array_map( 'intval', (array) get_term_meta( $term_id, Handbooks::META_USERS, true ) ) );

		$extra = array();
		if ( ! empty( $labels ) ) {
			$extra[] = implode( ', ', $labels );
		}
		if ( ! empty( $users ) ) {
			/* translators: %d: number of allowed users. */
			$extra[] = sprintf( _n( '%d user', '%d users', count( $users ), 'living-handbook' ), count( $users ) );
		}

		if ( empty( $extra ) ) {
			return esc_html__( 'Restricted', 'living-handbook' );
		}
		return esc_html(
			sprintf(
				/* translators: %s: the allowed roles and the number of allowed users, comma separated. */
				__( 'Restricted: %s', 'living-handbook' ),
				implode( ', ', $extra )
			)
		);
	}

	/**
	 * Render the visibility select control.
	 *
	 * @param string $current Currently selected value.
	 * @return void
	 */
	private function visibility_select( string $current ): void {
		$options = array(
			Handbooks::VISIBILITY_PUBLIC     => __( 'Public (no login)', 'living-handbook' ),
			Handbooks::VISIBILITY_MEMBERS    => __( 'All members (logged in)', 'living-handbook' ),
			Handbooks::VISIBILITY_RESTRICTED => __( 'Restricted (roles and/or users)', 'living-handbook' ),
		);

		echo '<select name="living_handbook_visibility" id="living_handbook_visibility">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Render the comments select control.
	 *
	 * @param string $current Currently selected value.
	 * @return void
	 */
	private function comments_select( string $current ): void {
		$options = array(
			Handbooks::COMMENTS_INHERIT => __( 'Each page decides (default)', 'living-handbook' ),
			Handbooks::COMMENTS_OPEN    => __( 'Open on every page of this handbook', 'living-handbook' ),
			Handbooks::COMMENTS_CLOSED  => __( 'Closed on every page of this handbook', 'living-handbook' ),
		);

		echo '<select name="living_handbook_comments" id="living_handbook_comments">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * The one sentence a person needs beside that select.
	 *
	 * @return string
	 */
	private static function comments_hint(): string {
		return __( 'Open and Closed override the setting on each page. Closing hides the comment form; comments already written stay readable and are not deleted.', 'living-handbook' );
	}

	/**
	 * Render one checkbox per role.
	 *
	 * @param string[] $selected Selected role slugs.
	 * @return void
	 */
	private function roles_checkboxes( array $selected ): void {
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			printf(
				'<label style="display:block"><input type="checkbox" name="living_handbook_roles[]" value="%s" %s> %s</label>',
				esc_attr( $slug ),
				checked( in_array( $slug, $selected, true ), true, false ),
				esc_html( translate_user_role( $name ) )
			);
		}
	}

	/**
	 * Saves the access configuration of a handbook term.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save( int $term_id ): void {
		if ( ! isset( $_POST['living_handbook_access_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['living_handbook_access_nonce'] ) ), 'living_handbook_access' ) ) {
			return;
		}
		if ( ! Handbooks::can_edit_access( false, '', $term_id ) ) {
			return;
		}

		$visibility = isset( $_POST['living_handbook_visibility'] )
			? sanitize_key( wp_unslash( $_POST['living_handbook_visibility'] ) )
			: Handbooks::VISIBILITY_MEMBERS;
		$allowed    = array( Handbooks::VISIBILITY_PUBLIC, Handbooks::VISIBILITY_MEMBERS, Handbooks::VISIBILITY_RESTRICTED );
		if ( ! in_array( $visibility, $allowed, true ) ) {
			$visibility = Handbooks::VISIBILITY_MEMBERS;
		}
		update_term_meta( $term_id, Handbooks::META_VISIBILITY, $visibility );

		// Sanitize the whole submitted array on the way in, then keep only the
		// roles this site actually has.
		$roles = array();
		if ( isset( $_POST['living_handbook_roles'] ) && is_array( $_POST['living_handbook_roles'] ) ) {
			$submitted = array_map( 'sanitize_key', wp_unslash( $_POST['living_handbook_roles'] ) );
			$valid     = array_keys( wp_roles()->get_names() );
			foreach ( $submitted as $role ) {
				if ( in_array( $role, $valid, true ) ) {
					$roles[] = $role;
				}
			}
		}
		update_term_meta( $term_id, Handbooks::META_ROLES, $roles );

		$users = array();
		if ( isset( $_POST['living_handbook_users_raw'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST['living_handbook_users_raw'] ) );
			foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $token ) {
				$user = ctype_digit( $token ) ? get_userdata( (int) $token ) : get_user_by( 'login', $token );
				if ( false !== $user ) {
					$users[] = (int) $user->ID;
				}
			}
		}
		update_term_meta( $term_id, Handbooks::META_USERS, array_values( array_unique( $users ) ) );

		$comments = isset( $_POST['living_handbook_comments'] )
			? sanitize_key( wp_unslash( $_POST['living_handbook_comments'] ) )
			: Handbooks::COMMENTS_INHERIT;
		if ( ! in_array( $comments, array( Handbooks::COMMENTS_OPEN, Handbooks::COMMENTS_CLOSED ), true ) ) {
			$comments = Handbooks::COMMENTS_INHERIT;
		}
		update_term_meta( $term_id, Handbooks::META_COMMENTS, $comments );

		self::save_version( $term_id );
	}

	/**
	 * Saves the version fields. The nonce and the right are checked in save().
	 *
	 * @param int $term_id Handbook.
	 * @return void
	 */
	private static function save_version( int $term_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in save().
		$source = isset( $_POST['living_handbook_version_source'] ) && HandbookVersion::SOURCE_GITHUB === sanitize_key( wp_unslash( $_POST['living_handbook_version_source'] ) )
			? HandbookVersion::SOURCE_GITHUB
			: HandbookVersion::SOURCE_MANUAL;
		$repos  = HandbookVersion::repositories( $term_id );
		if ( array() === $repos ) {
			$source = HandbookVersion::SOURCE_MANUAL;
		}
		$was = HandbookVersion::source( $term_id );
		update_term_meta( $term_id, HandbookVersion::META_SOURCE, $source );

		if ( HandbookVersion::SOURCE_GITHUB === $source ) {
			$repo = isset( $_POST['living_handbook_version_repo'] ) ? sanitize_text_field( wp_unslash( $_POST['living_handbook_version_repo'] ) ) : '';
			if ( ! isset( $repos[ $repo ] ) ) {
				$repo = (string) array_key_first( $repos );
			}
			$changed = get_term_meta( $term_id, HandbookVersion::META_REPO, true ) !== $repo;
			update_term_meta( $term_id, HandbookVersion::META_REPO, $repo );
			// Switched on or to another repository: ask now, so the edit screen
			// shows the result instead of waiting for the next sync pass.
			if ( $changed || HandbookVersion::SOURCE_GITHUB !== $was ) {
				HandbookVersion::refresh_tag( $term_id );
			}
			return;
		}

		delete_term_meta( $term_id, HandbookVersion::META_ERROR );
		if ( isset( $_POST['living_handbook_version_edition'] ) ) {
			$edition = trim( sanitize_text_field( wp_unslash( $_POST['living_handbook_version_edition'] ) ) );
			if ( HandbookVersion::valid_edition( $edition ) ) {
				HandbookVersion::set_edition( $term_id, $edition );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
