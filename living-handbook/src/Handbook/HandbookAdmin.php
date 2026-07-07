<?php
/**
 * Admin UI for a handbook's frontend access configuration.
 *
 * Adds fields to the `handbook_set` term add and edit screens so an editor can
 * set the visibility (public, members, restricted) and, for restricted
 * handbooks, the allowed roles and users.
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
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( Handbooks::TAXONOMY . '_add_form_fields', array( $this, 'render_add_fields' ) );
		add_action( Handbooks::TAXONOMY . '_edit_form_fields', array( $this, 'render_edit_fields' ) );
		add_action( 'created_' . Handbooks::TAXONOMY, array( $this, 'save' ) );
		add_action( 'edited_' . Handbooks::TAXONOMY, array( $this, 'save' ) );
	}

	/**
	 * Fields on the "add term" screen.
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
		<div class="form-field">
			<label><?php esc_html_e( 'Allowed roles (restricted only)', 'living-handbook' ); ?></label>
			<?php $this->roles_checkboxes( array() ); ?>
		</div>
		<div class="form-field">
			<label for="living_handbook_users_raw"><?php esc_html_e( 'Allowed users (restricted only)', 'living-handbook' ); ?></label>
			<input type="text" name="living_handbook_users_raw" id="living_handbook_users_raw" value="">
			<p class="description"><?php esc_html_e( 'Comma-separated user logins or IDs.', 'living-handbook' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Fields on the "edit term" screen.
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
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Allowed roles (restricted only)', 'living-handbook' ); ?></th>
			<td><?php $this->roles_checkboxes( $roles ); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="living_handbook_users_raw"><?php esc_html_e( 'Allowed users (restricted only)', 'living-handbook' ); ?></label></th>
			<td>
				<input type="text" name="living_handbook_users_raw" id="living_handbook_users_raw" value="<?php echo esc_attr( implode( ', ', $logins ) ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Comma-separated user logins or IDs.', 'living-handbook' ); ?></p>
			</td>
		</tr>
		<?php
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
	 * Save the access configuration for a handbook term.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save( int $term_id ): void {
		if ( ! isset( $_POST['living_handbook_access_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['living_handbook_access_nonce'] ) ), 'living_handbook_access' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
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

		$roles = array();
		if ( isset( $_POST['living_handbook_roles'] ) && is_array( $_POST['living_handbook_roles'] ) ) {
			$valid = array_keys( wp_roles()->get_names() );
			foreach ( wp_unslash( $_POST['living_handbook_roles'] ) as $role ) {
				$role = sanitize_key( $role );
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
	}
}
