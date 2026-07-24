<?php
/**
 * The plugin settings page (sync frequency and uninstall behaviour).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Setup;

use LivingHandbook\Git\GitSync;
use LivingHandbook\PostType\Handbook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin's single settings page with the Settings API, so the form
 * posts to options.php: a browser reload does not resubmit, the options are
 * validated by their sanitize callbacks, and settings_errors works. The
 * scheduling itself and the option names live in GitSync; this class only owns
 * the page and the fields, and re-applies the cron schedule when the frequency
 * changes.
 */
final class Settings {

	/**
	 * The settings group the fields register into.
	 */
	private const OPTION_GROUP = 'living_handbook_settings';

	/**
	 * The settings page slug (kept equal to the previous page so the submenu
	 * order and the sync-error notice still target it).
	 */
	public const PAGE_SLUG = 'living-handbook-sync';

	/**
	 * Option holding the site's custom CSS for the handbook frontend, so a site
	 * can style the handbook from the plugin instead of the theme; it is removed
	 * when the plugin is uninstalled.
	 */
	public const OPTION_CUSTOM_CSS = 'living_handbook_custom_css';

	/**
	 * Option that lets logged-out visitors vote on public pages. Off by default:
	 * anonymous voting has no per-person limit (see Feedback), so a site opts in
	 * deliberately. No cookie, no IP and nothing else personal is stored either
	 * way.
	 */
	public const OPTION_PUBLIC_FEEDBACK = 'living_handbook_public_feedback';

	/**
	 * Whether anonymous voting on public pages is switched on.
	 *
	 * @return bool
	 */
	public static function public_feedback_enabled(): bool {
		return (bool) get_option( self::OPTION_PUBLIC_FEEDBACK, false );
	}

	/**
	 * Hook registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Re-apply the cron schedule whenever the frequency option changes or is
		// first written.
		add_action( 'update_option_' . GitSync::OPTION_SCHEDULE, array( $this, 'reschedule' ) );
		add_action( 'add_option_' . GitSync::OPTION_SCHEDULE, array( $this, 'reschedule' ) );
	}

	/**
	 * Add the settings submenu under the handbook menu.
	 *
	 * @return void
	 */
	public function add_page(): void {
		$hook = add_submenu_page(
			'edit.php?post_type=' . Handbook::POST_TYPE,
			__( 'Settings', 'living-handbook' ),
			__( 'Settings', 'living-handbook' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
		if ( is_string( $hook ) && '' !== $hook ) {
			add_action( 'load-' . $hook, array( $this, 'register_help' ) );
		}
	}

	/**
	 * Add a contextual Help tab explaining the Custom CSS field, with two
	 * examples and a link to the customization documentation.
	 *
	 * @return void
	 */
	public function register_help(): void {
		$screen = get_current_screen();
		if ( null === $screen ) {
			return;
		}
		$doc     = 'https://github.com/rfluethi/living-handbook/blob/main/docs/customization.md';
		$content = '<p>' . esc_html__( 'Add CSS that loads on the handbook pages only. It is stored with the plugin and removed when you delete the plugin, unlike CSS kept in the theme.', 'living-handbook' ) . '</p>'
			. '<p>' . esc_html__( 'Target the plugin classes and the --lh-* custom properties. For example:', 'living-handbook' ) . '</p>'
			. '<pre>.living-handbook-entry, .living-handbook-nav { --lh-accent: #b30000; }' . "\n" . '.living-handbook-card__dot { display: none; }</pre>'
			. '<p>' . sprintf(
				/* translators: %s: link to the customization documentation. */
				esc_html__( 'The full reference of variables and class names is in the %s.', 'living-handbook' ),
				'<a href="' . esc_url( $doc ) . '" target="_blank" rel="noreferrer noopener">' . esc_html__( 'customization documentation', 'living-handbook' ) . '</a>'
			) . '</p>';
		$screen->add_help_tab(
			array(
				'id'      => 'living_handbook_css_help',
				'title'   => __( 'Custom CSS', 'living-handbook' ),
				'content' => $content,
			)
		);
	}

	/**
	 * Register the settings, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			GitSync::OPTION_SCHEDULE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_schedule' ),
				'default'           => 'weekly',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			GitSync::OPTION_UNINSTALL,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_uninstall' ),
				'default'           => 0,
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_CUSTOM_CSS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_css' ),
				'default'           => '',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_PUBLIC_FEEDBACK,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_public_feedback' ),
				'default'           => 0,
			)
		);

		add_settings_section( 'living_handbook_sync_section', __( 'GitHub sync', 'living-handbook' ), '__return_null', self::PAGE_SLUG );
		add_settings_field(
			GitSync::OPTION_SCHEDULE,
			__( 'Automatic sync', 'living-handbook' ),
			array( $this, 'render_schedule_field' ),
			self::PAGE_SLUG,
			'living_handbook_sync_section',
			array( 'label_for' => GitSync::OPTION_SCHEDULE )
		);

		add_settings_section( 'living_handbook_appearance_section', __( 'Appearance', 'living-handbook' ), '__return_null', self::PAGE_SLUG );
		add_settings_field(
			self::OPTION_CUSTOM_CSS,
			__( 'Custom CSS', 'living-handbook' ),
			array( $this, 'render_css_field' ),
			self::PAGE_SLUG,
			'living_handbook_appearance_section',
			array( 'label_for' => self::OPTION_CUSTOM_CSS )
		);

		add_settings_section( 'living_handbook_feedback_section', __( 'Feedback', 'living-handbook' ), '__return_null', self::PAGE_SLUG );
		add_settings_field(
			self::OPTION_PUBLIC_FEEDBACK,
			__( 'Public feedback', 'living-handbook' ),
			array( $this, 'render_public_feedback_field' ),
			self::PAGE_SLUG,
			'living_handbook_feedback_section'
		);

		add_settings_section( 'living_handbook_uninstall_section', __( 'Uninstall', 'living-handbook' ), '__return_null', self::PAGE_SLUG );
		add_settings_field(
			GitSync::OPTION_UNINSTALL,
			__( 'When the plugin is deleted', 'living-handbook' ),
			array( $this, 'render_uninstall_field' ),
			self::PAGE_SLUG,
			'living_handbook_uninstall_section'
		);
	}

	/**
	 * Validate the sync frequency against the known choices.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_schedule( $value ): string {
		$value   = is_string( $value ) ? $value : '';
		$choices = array_keys( GitSync::schedule_choices() );
		return in_array( $value, $choices, true ) ? $value : 'weekly';
	}

	/**
	 * Normalise the uninstall checkbox to 0 or 1. An unchecked box is submitted
	 * as null, which becomes 0.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_uninstall( $value ): int {
		return empty( $value ) ? 0 : 1;
	}

	/**
	 * Normalise the public-feedback checkbox to 0 or 1.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_public_feedback( $value ): int {
		return empty( $value ) ? 0 : 1;
	}

	/**
	 * Sanitize the custom CSS. CSS never needs a "<", so removing it prevents
	 * closing the style tag or injecting a script: the value cannot break out of
	 * the style block it is printed in.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_css( $value ): string {
		$value = is_string( $value ) ? $value : '';
		return trim( str_replace( '<', '', $value ) );
	}

	/**
	 * Render the custom CSS field.
	 *
	 * @return void
	 */
	public function render_css_field(): void {
		$css = (string) get_option( self::OPTION_CUSTOM_CSS, '' );
		printf(
			'<textarea id="%1$s" name="%1$s" rows="10" class="large-text code" spellcheck="false">%2$s</textarea>',
			esc_attr( self::OPTION_CUSTOM_CSS ),
			esc_textarea( $css )
		);
		echo '<p class="description">' . esc_html__( 'CSS added on the handbook pages only, stored with the plugin. See the Help tab (top right) for examples.', 'living-handbook' ) . '</p>';
	}

	/**
	 * Render the public-feedback field.
	 *
	 * @return void
	 */
	public function render_public_feedback_field(): void {
		$on = self::public_feedback_enabled();
		echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_PUBLIC_FEEDBACK ) . '" value="1" ' . checked( $on, true, false ) . '> ' . esc_html__( 'Show "Was this helpful?" to logged-out visitors on public pages', 'living-handbook' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- checked() returns a safe attribute string; the surrounding text is escaped.
		echo '<p class="description">' . esc_html__( 'Off by default. When on, anyone can vote on a public page. To keep it privacy-friendly, votes are not tied to a person: no cookie, no IP and nothing else personal is stored, so the same visitor can vote again after reloading. On internal pages only logged-in users vote, one vote each, regardless of this setting.', 'living-handbook' ) . '</p>';
	}

	/**
	 * Re-apply the cron schedule after the frequency option changes.
	 *
	 * @return void
	 */
	public function reschedule(): void {
		GitSync::reschedule();
	}

	/**
	 * Render the sync frequency field.
	 *
	 * @return void
	 */
	public function render_schedule_field(): void {
		$current = (string) get_option( GitSync::OPTION_SCHEDULE, 'weekly' );
		echo '<select id="' . esc_attr( GitSync::OPTION_SCHEDULE ) . '" name="' . esc_attr( GitSync::OPTION_SCHEDULE ) . '">';
		foreach ( GitSync::schedule_choices() as $key => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $key, $current, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a safe attribute string.
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'How often WordPress pulls GitHub pages in the background, in batches. GitHub pages are always synced when saved and via Sync now, regardless of this setting. The background sync runs on WordPress cron, which fires on site visits.', 'living-handbook' ) . '</p>';

		$next = GitSync::next_scheduled();
		if ( $next > 0 ) {
			echo '<p class="description">' . esc_html__( 'Next scheduled sync:', 'living-handbook' ) . ' ' . esc_html( wp_date( 'Y-m-d H:i', $next ) ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'No background sync scheduled.', 'living-handbook' ) . '</p>';
		}
	}

	/**
	 * Render the uninstall behaviour field.
	 *
	 * @return void
	 */
	public function render_uninstall_field(): void {
		$remove = (bool) get_option( GitSync::OPTION_UNINSTALL, false );
		echo '<label><input type="checkbox" name="' . esc_attr( GitSync::OPTION_UNINSTALL ) . '" value="1" ' . checked( $remove, true, false ) . '> ' . esc_html__( 'Also delete all handbook pages, handbooks and their data', 'living-handbook' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- checked() returns a safe attribute string; the surrounding text is escaped.
		echo '<p class="description">' . esc_html__( 'Off by default: your content is kept when the plugin is deleted, only the plugin settings and caches are removed. Turn this on to remove everything the plugin created.', 'living-handbook' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'This also removes templates you edited in the Site Editor.', 'living-handbook' ) . '</p>';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'living-handbook' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
