<?php
/**
 * The plugin settings page (sync frequency and uninstall behaviour).
 *
 * @package LivingHandbook
 */

declare( strict_types=1 );

namespace LivingHandbook\Setup;

use LivingHandbook\Frontend\Appearance;
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
	 * The settings group prefix. Every tab registers into a group of its own,
	 * and that is not cosmetic: options.php walks the group of the submitted
	 * form and calls update_option() for every option in it, with null for the
	 * ones the form did not send (wp-admin/options.php, the loop over
	 * $allowed_options). One group across five tabs would therefore empty the
	 * four tabs that were not on screen, on every save.
	 */
	private const OPTION_GROUP = 'living_handbook_settings';

	/**
	 * The tabs, in the order they are shown: slug => label callback key. The
	 * first one is the default.
	 *
	 * @return array<string, string>
	 */
	public static function tabs(): array {
		return array(
			'sync'       => __( 'GitHub sync', 'living-handbook' ),
			'appearance' => __( 'Appearance', 'living-handbook' ),
			'feedback'   => __( 'Feedback', 'living-handbook' ),
			'access'     => __( 'Access', 'living-handbook' ),
			'uninstall'  => __( 'Uninstall', 'living-handbook' ),
		);
	}

	/**
	 * The settings group of one tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function group( string $tab ): string {
		return self::OPTION_GROUP . '_' . $tab;
	}

	/**
	 * The section and field page of one tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private static function tab_page( string $tab ): string {
		return self::PAGE_SLUG . '_' . $tab;
	}

	/**
	 * The tab currently being shown, defaulting to the first one.
	 *
	 * @return string
	 */
	public static function current_tab(): string {
		$tabs = self::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which tab to display, not acting on it; the value is checked against the known tabs.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	}

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
	 * Page shown to a logged-in user who may not read a handbook.
	 *
	 * 0 means the built-in message. Setting a page lets a site explain access in
	 * its own words and design, for example with a contact form or the name of
	 * the team that grants access.
	 */
	public const OPTION_DENIED_PAGE = 'living_handbook_denied_page';

	/**
	 * The screen the colour picker assets belong on, so they load there and
	 * nowhere else.
	 *
	 * @var string
	 */
	private string $color_picker_hook = '';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
			add_action( 'admin_print_footer_scripts-' . $hook, array( $this, 'print_color_picker_script' ) );
			$this->color_picker_hook = $hook;
		}
	}

	/**
	 * Load the colour picker WordPress already ships on the settings screen.
	 *
	 * @param string $hook Current admin screen.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->color_picker_hook || $hook !== $this->color_picker_hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Turn the colour fields into pickers, with the theme's own palette as the
	 * swatches underneath.
	 *
	 * Written as a footer script rather than a file because it is four lines and
	 * carries the palette with it. Without JavaScript the fields stay plain text
	 * inputs that accept a hex colour, which is why they are text inputs in the
	 * markup: a type="color" input has no empty state, and empty is what "the
	 * theme decides" looks like.
	 *
	 * @return void
	 */
	public function print_color_picker_script(): void {
		if ( ! wp_script_is( 'wp-color-picker', 'enqueued' ) ) {
			return;
		}

		$palette = array_keys( Appearance::theme_palette() );

		printf(
			'<script>jQuery(function($){$(".living-handbook-color").wpColorPicker({palettes:%s});});</script>',
			wp_json_encode( array_values( $palette ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every entry passed sanitize_hex_color(), and wp_json_encode() escapes the result for a script context.
		);
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
		$doc     = 'https://github.com/rfluethi/living-handbook/blob/main/docs/technical/en/customization.md';
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
			self::group( 'sync' ),
			GitSync::OPTION_SCHEDULE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_schedule' ),
				'default'           => 'weekly',
			)
		);
		register_setting(
			self::group( 'uninstall' ),
			GitSync::OPTION_UNINSTALL,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_uninstall' ),
				'default'           => 0,
			)
		);
		register_setting(
			self::group( 'appearance' ),
			self::OPTION_CUSTOM_CSS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_css' ),
				'default'           => '',
			)
		);
		register_setting(
			self::group( 'feedback' ),
			self::OPTION_PUBLIC_FEEDBACK,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_public_feedback' ),
				'default'           => 0,
			)
		);
		register_setting(
			self::group( 'access' ),
			self::OPTION_DENIED_PAGE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_denied_page' ),
				'default'           => 0,
			)
		);
		register_setting(
			self::group( 'appearance' ),
			Appearance::OPTION_COLORS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Appearance::class, 'sanitize_colors' ),
				'default'           => array(),
			)
		);
		register_setting(
			self::group( 'appearance' ),
			Appearance::OPTION_BASE_SIZE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( Appearance::class, 'sanitize_size' ),
				'default'           => 100,
			)
		);

		add_settings_section( 'living_handbook_sync_section', '', '__return_null', self::tab_page( 'sync' ) );
		add_settings_field(
			GitSync::OPTION_SCHEDULE,
			__( 'Automatic sync', 'living-handbook' ),
			array( $this, 'render_schedule_field' ),
			self::tab_page( 'sync' ),
			'living_handbook_sync_section',
			array( 'label_for' => GitSync::OPTION_SCHEDULE )
		);

		add_settings_section( 'living_handbook_appearance_section', '', array( $this, 'render_appearance_intro' ), self::tab_page( 'appearance' ) );

		add_settings_field(
			Appearance::OPTION_BASE_SIZE,
			__( 'Text size', 'living-handbook' ),
			array( $this, 'render_base_size_field' ),
			self::tab_page( 'appearance' ),
			'living_handbook_appearance_section',
			array( 'label_for' => Appearance::OPTION_BASE_SIZE )
		);

		foreach ( Appearance::fields() as $key => $field ) {
			add_settings_field(
				Appearance::OPTION_COLORS . '_' . $key,
				$field['label'],
				array( $this, 'render_color_field' ),
				self::tab_page( 'appearance' ),
				'living_handbook_appearance_section',
				array(
					'label_for'   => Appearance::OPTION_COLORS . '_' . $key,
					'key'         => $key,
					'description' => $field['description'],
				)
			);
		}

		add_settings_field(
			self::OPTION_CUSTOM_CSS,
			__( 'Custom CSS', 'living-handbook' ),
			array( $this, 'render_css_field' ),
			self::tab_page( 'appearance' ),
			'living_handbook_appearance_section',
			array( 'label_for' => self::OPTION_CUSTOM_CSS )
		);

		add_settings_section( 'living_handbook_feedback_section', '', '__return_null', self::tab_page( 'feedback' ) );
		add_settings_field(
			self::OPTION_PUBLIC_FEEDBACK,
			__( 'Public feedback', 'living-handbook' ),
			array( $this, 'render_public_feedback_field' ),
			self::tab_page( 'feedback' ),
			'living_handbook_feedback_section'
		);

		add_settings_section( 'living_handbook_access_section', '', '__return_null', self::tab_page( 'access' ) );
		add_settings_field(
			self::OPTION_DENIED_PAGE,
			__( 'No-access page', 'living-handbook' ),
			array( $this, 'render_denied_page_field' ),
			self::tab_page( 'access' ),
			'living_handbook_access_section',
			array( 'label_for' => self::OPTION_DENIED_PAGE )
		);

		add_settings_section( 'living_handbook_uninstall_section', '', '__return_null', self::tab_page( 'uninstall' ) );
		add_settings_field(
			GitSync::OPTION_UNINSTALL,
			__( 'When the plugin is deleted', 'living-handbook' ),
			array( $this, 'render_uninstall_field' ),
			self::tab_page( 'uninstall' ),
			'living_handbook_uninstall_section'
		);
	}

	/**
	 * Keep the no-access page a published page of this site, or 0 for the
	 * built-in message.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_denied_page( $value ): int {
		$page_id = absint( $value );
		if ( 0 === $page_id ) {
			return 0;
		}
		return 'publish' === get_post_status( $page_id ) ? $page_id : 0;
	}

	/**
	 * Render the no-access page selector.
	 *
	 * @return void
	 */
	public function render_denied_page_field(): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The sniff treats wp_dropdown_pages() as a printing function and flags its arguments. With echo => false it returns the markup instead, and it is escaped with wp_kses() below.
		$dropdown = wp_dropdown_pages(
			array(
				'name'              => self::OPTION_DENIED_PAGE,
				'id'                => self::OPTION_DENIED_PAGE,
				'selected'          => (int) get_option( self::OPTION_DENIED_PAGE, 0 ),
				'show_option_none'  => __( 'Use the built-in message', 'living-handbook' ),
				'option_none_value' => '0',
				'echo'              => false,
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_kses(
			(string) $dropdown,
			array(
				'select' => array(
					'name'  => array(),
					'id'    => array(),
					'class' => array(),
				),
				'option' => array(
					'value'    => array(),
					'selected' => array(),
					'class'    => array(),
				),
			)
		);
		echo '<p class="description">'
			. esc_html__( 'Where a signed-in visitor lands when they open a handbook they may not read. Leave this on the built-in message unless you want to explain in your own words who grants access.', 'living-handbook' )
			. '</p>';
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

		// CSS never needs a "<", so removing it prevents closing the style tag or
		// injecting a script.
		$value = str_replace( '<', '', $value );

		// @import pulls a whole stylesheet from wherever it points, and every
		// visitor of every handbook page then contacts that host. Same for
		// url(https://...): a background image is a request with a referrer. Only
		// an administrator can write in this field, so this is not a hole someone
		// else can use, but a handbook is exactly the kind of page whose readers
		// should not be announced to a third party, and a pasted snippet is how
		// that happens by accident. Local references (relative paths, data: and
		// the site's own host) are left alone.
		$value = (string) preg_replace( '/@import\b[^;]*;?/i', '', $value );
		$value = (string) preg_replace_callback(
			'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
			static function ( array $matches ): string {
				$target = trim( $matches[2] );
				if ( '' === $target ) {
					return '';
				}
				if ( 0 === stripos( $target, 'data:' ) ) {
					return $matches[0];
				}
				$host = wp_parse_url( $target, PHP_URL_HOST );
				if ( null === $host || '' === $host ) {
					return $matches[0];
				}
				$own = wp_parse_url( home_url(), PHP_URL_HOST );

				return is_string( $own ) && strtolower( $host ) === strtolower( $own ) ? $matches[0] : '';
			},
			$value
		);

		return trim( $value );
	}

	/**
	 * Explain what the appearance fields are for before showing them.
	 *
	 * @return void
	 */
	public function render_appearance_intro(): void {
		echo '<p class="description" style="max-width:46em">'
			. esc_html__( 'Leave the colour fields empty and the handbook follows your theme, which is what it is built to do. Fill one in only where the theme gets it wrong. The swatches under each picker are your theme\'s own palette.', 'living-handbook' )
			. '</p>';
	}

	/**
	 * Render the text size field.
	 *
	 * A number in percent rather than a slider: it works without JavaScript, it
	 * says what it does, and the same value can be typed again on the next site.
	 *
	 * @return void
	 */
	public function render_base_size_field(): void {
		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$d" min="%3$d" max="%4$d" step="5" class="small-text"> %5$s',
			esc_attr( Appearance::OPTION_BASE_SIZE ),
			(int) Appearance::base_size(),
			(int) Appearance::SIZE_MIN,
			(int) Appearance::SIZE_MAX,
			esc_html__( 'percent', 'living-handbook' )
		);
		echo '<p class="description">'
			. esc_html__( 'The size of the handbook\'s own text: navigation, table of contents, badges, cards and the page details. It does not touch the text of a page itself, which belongs to your theme. 100 percent is 16 pixels, the size the plugin was designed at. Raise it if your theme sets larger text than that and the handbook looks small beside it.', 'living-handbook' )
			. '</p>';
	}

	/**
	 * Render one colour field.
	 *
	 * @param array<string, mixed> $args Field arguments, carrying the colour key.
	 * @return void
	 */
	public function render_color_field( array $args ): void {
		$key = isset( $args['key'] ) && is_string( $args['key'] ) ? $args['key'] : '';
		if ( '' === $key ) {
			return;
		}

		$colors = Appearance::colors();
		$value  = isset( $colors[ $key ] ) ? $colors[ $key ] : '';

		printf(
			'<input type="text" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="living-handbook-color regular-text" placeholder="%5$s" data-default-color="">',
			esc_attr( Appearance::OPTION_COLORS . '_' . $key ),
			esc_attr( Appearance::OPTION_COLORS ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr__( 'Empty: the theme decides', 'living-handbook' )
		);

		if ( isset( $args['description'] ) && is_string( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
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
		$current = self::current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'living-handbook' ); ?></h1>
			<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Settings sections', 'living-handbook' ); ?>">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<a href="<?php echo esc_url( self::tab_url( $slug ) ); ?>" class="nav-tab<?php echo $slug === $current ? ' nav-tab-active' : ''; ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				// The group is this tab's own. One group across the tabs would
				// empty every option that is not on screen, because options.php
				// writes null for the ones the form did not send.
				settings_fields( self::group( $current ) );
				do_settings_sections( self::tab_page( $current ) );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The address of one tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_url( string $tab ): string {
		return add_query_arg(
			array(
				'post_type' => Handbook::POST_TYPE,
				'page'      => self::PAGE_SLUG,
				'tab'       => $tab,
			),
			admin_url( 'edit.php' )
		);
	}
}
