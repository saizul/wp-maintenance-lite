<?php
/**
 * Settings > Maintenance Lite screen.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite\Admin;

use Saizul\MaintenanceLite\Plugin;
use Saizul\MaintenanceLite\Request;
use Saizul\MaintenanceLite\Settings;
use Saizul\MaintenanceLite\Token;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen built on the Settings API. The main form posts to
 * options.php (nonce and capability checked by core, values passed through
 * Settings::sanitize()). The "regenerate preview link" form posts to
 * admin-post.php with its own nonce.
 */
final class SettingsPage {

	const SLUG       = 'wp-maintenance-lite';
	const GROUP      = 'wp_maintenance_lite';
	const REGENERATE = 'wp_maintenance_lite_regenerate';

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::REGENERATE, array( $this, 'handle_regenerate' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( Plugin::class, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin->file() ), array( $this, 'action_links' ) );
	}

	/**
	 * URL of the screen.
	 *
	 * @param array<string,string> $args Extra query arguments.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Add Settings > Maintenance Lite.
	 */
	public function add_page(): void {
		add_options_page(
			__( 'Maintenance Lite', 'wp-maintenance-lite' ),
			__( 'Maintenance Lite', 'wp-maintenance-lite' ),
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param mixed $links Links.
	 * @return mixed
	 */
	public function action_links( $links ) {
		if ( ! is_array( $links ) || ! current_user_can( Plugin::capability() ) ) {
			return $links;
		}
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'wp-maintenance-lite' ) . '</a>' );

		return $links;
	}

	/**
	 * Register the setting, sections and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section( 'mlite_status', __( 'Status', 'wp-maintenance-lite' ), '__return_false', self::SLUG );
		add_settings_section( 'mlite_access', __( 'Who can see the site', 'wp-maintenance-lite' ), '__return_false', self::SLUG );
		add_settings_section( 'mlite_schedule', __( 'Schedule', 'wp-maintenance-lite' ), '__return_false', self::SLUG );
		add_settings_section( 'mlite_page', __( 'Page content', 'wp-maintenance-lite' ), array( $this, 'page_section_intro' ), self::SLUG );

		$fields = array(
			'enabled'          => array( __( 'Maintenance mode', 'wp-maintenance-lite' ), 'mlite_status' ),
			'mode'             => array( __( 'Mode', 'wp-maintenance-lite' ), 'mlite_status' ),
			'noindex'          => array( __( 'Search engines', 'wp-maintenance-lite' ), 'mlite_status' ),
			'bypass_roles'     => array( __( 'Roles that see the site', 'wp-maintenance-lite' ), 'mlite_access' ),
			'ip_allowlist'     => array( __( 'IP allowlist', 'wp-maintenance-lite' ), 'mlite_access' ),
			'end_time'         => array( __( 'Expected end', 'wp-maintenance-lite' ), 'mlite_schedule' ),
			'retry_after'      => array( __( 'Retry-After fallback', 'wp-maintenance-lite' ), 'mlite_schedule' ),
			'headline'         => array( __( 'Headline', 'wp-maintenance-lite' ), 'mlite_page' ),
			'message'          => array( __( 'Message', 'wp-maintenance-lite' ), 'mlite_page' ),
			'logo_url'         => array( __( 'Logo URL', 'wp-maintenance-lite' ), 'mlite_page' ),
			'background_color' => array( __( 'Background colour', 'wp-maintenance-lite' ), 'mlite_page' ),
			'text_color'       => array( __( 'Text colour', 'wp-maintenance-lite' ), 'mlite_page' ),
		);
		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'mlite_' . $key,
				$field[0],
				array( $this, 'field' ),
				self::SLUG,
				$field[1],
				array(
					'key'       => $key,
					'label_for' => in_array( $key, array( 'mode', 'bypass_roles', 'enabled', 'noindex' ), true ) ? '' : 'mlite-' . $key,
				)
			);
		}
	}

	/**
	 * Intro of the page section.
	 */
	public function page_section_intro(): void {
		echo '<p>' . esc_html__( 'Leave the headline and message empty to use the default text. Themes can replace the whole page with a maintenance-lite.php template file.', 'wp-maintenance-lite' ) . '</p>';
	}

	/**
	 * Name attribute of a field.
	 *
	 * @param string $key Setting key.
	 */
	private static function name( string $key ): string {
		return Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Print one field.
	 *
	 * @param array<string,string> $args Field arguments.
	 */
	public function field( $args ): void {
		$key      = is_array( $args ) && isset( $args['key'] ) ? (string) $args['key'] : '';
		$settings = $this->plugin->settings();
		$id       = 'mlite-' . $key;

		switch ( $key ) {
			case 'enabled':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>',
					esc_attr( self::name( $key ) ),
					checked( (bool) $settings['enabled'], true, false ),
					esc_html__( 'On: visitors see the maintenance page', 'wp-maintenance-lite' )
				);
				break;

			case 'mode':
				$modes = array(
					Settings::MODE_MAINTENANCE => __( 'Maintenance: HTTP 503 with Retry-After. Search engines keep your rankings and come back later.', 'wp-maintenance-lite' ),
					Settings::MODE_COMING_SOON => __( 'Coming soon: HTTP 200. Use for a site that is not launched yet.', 'wp-maintenance-lite' ),
				);
				echo '<fieldset>';
				foreach ( $modes as $value => $label ) {
					printf(
						'<label><input type="radio" name="%1$s" value="%2$s" %3$s> %4$s</label><br>',
						esc_attr( self::name( $key ) ),
						esc_attr( $value ),
						checked( $settings['mode'], $value, false ),
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;

			case 'noindex':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label><p class="description">%4$s</p>',
					esc_attr( self::name( $key ) ),
					checked( (bool) $settings['noindex'], true, false ),
					esc_html__( 'Ask search engines not to index the coming-soon page (noindex)', 'wp-maintenance-lite' ),
					esc_html__( 'The maintenance page (503) is never indexed, so this only affects coming-soon mode.', 'wp-maintenance-lite' )
				);
				break;

			case 'bypass_roles':
				echo '<fieldset>';
				foreach ( wp_roles()->get_names() as $slug => $name ) {
					printf(
						'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label><br>',
						esc_attr( self::name( $key ) ),
						esc_attr( (string) $slug ),
						checked( in_array( (string) $slug, (array) $settings['bypass_roles'], true ), true, false ),
						esc_html( translate_user_role( (string) $name ) )
					);
				}
				echo '</fieldset><p class="description">' . esc_html__( 'Logged-in users with these roles see the normal site. Everyone can always reach wp-login.php and wp-admin.', 'wp-maintenance-lite' ) . '</p>';
				break;

			case 'ip_allowlist':
				$ip = Request::from_globals()->ip;
				printf(
					'<textarea id="%1$s" name="%2$s" rows="4" cols="40" class="code">%3$s</textarea><p class="description">%4$s</p>',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_textarea( implode( "\n", (array) $settings['ip_allowlist'] ) ),
					esc_html(
						sprintf(
							/* translators: %s: the visitor's IP address. */
							__( 'One IP address or CIDR range per line, for example 203.0.113.7 or 2001:db8::/32. Your address: %s', 'wp-maintenance-lite' ),
							'' !== $ip ? $ip : __( 'unknown', 'wp-maintenance-lite' )
						)
					)
				);
				break;

			case 'end_time':
				$end = (int) $settings['end_time'];
				printf(
					'<input type="datetime-local" id="%1$s" name="%2$s" value="%3$s"> <p class="description">%4$s</p><label><input type="checkbox" name="%5$s" value="1" %6$s> %7$s</label>',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_attr( $end > 0 ? (string) wp_date( 'Y-m-d\TH:i', $end ) : '' ),
					esc_html(
						sprintf(
							/* translators: %s: time zone name. */
							__( 'Optional. In the site time zone (%s). Shows a countdown and sets Retry-After.', 'wp-maintenance-lite' ),
							wp_timezone_string()
						)
					),
					esc_attr( self::name( 'auto_disable' ) ),
					checked( (bool) $settings['auto_disable'], true, false ),
					esc_html__( 'Switch maintenance mode off automatically at this time', 'wp-maintenance-lite' )
				);
				break;

			case 'retry_after':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$d" min="60" max="604800" step="60" class="small-text"> %4$s<p class="description">%5$s</p>',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					(int) $settings['retry_after'],
					esc_html__( 'seconds', 'wp-maintenance-lite' ),
					esc_html__( 'Used when no end time is set, or after it has passed.', 'wp-maintenance-lite' )
				);
				break;

			case 'message':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="5" cols="60" class="large-text">%3$s</textarea><p class="description">%4$s</p>',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_textarea( (string) $settings['message'] ),
					esc_html__( 'Basic HTML such as links and bold text is allowed.', 'wp-maintenance-lite' )
				);
				break;

			case 'background_color':
			case 'text_color':
				printf(
					'<input type="color" id="%1$s" name="%2$s" value="%3$s">',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_attr( (string) $settings[ $key ] )
				);
				break;

			case 'logo_url':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="https://"><p class="description">%4$s</p>',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_attr( (string) $settings['logo_url'] ),
					esc_html__( 'Copy the URL of an image from the Media Library.', 'wp-maintenance-lite' )
				);
				break;

			case 'headline':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" maxlength="200">',
					esc_attr( $id ),
					esc_attr( self::name( $key ) ),
					esc_attr( (string) $settings['headline'] )
				);
				break;
		}
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		$preview = $this->plugin->preview_url();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php if ( Plugin::disabled_by_constant() ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html__( 'WP_MAINTENANCE_LITE_DISABLE is set in wp-config.php, so the maintenance page is never shown.', 'wp-maintenance-lite' ); ?></p></div>
			<?php endif; ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>

			<h2><?php echo esc_html__( 'Secret preview link', 'wp-maintenance-lite' ); ?></h2>
			<p><?php echo esc_html__( 'Anyone who opens this link sees the normal site for 7 days (or until the end time), without logging in. Regenerate it to lock out everyone who has the old link.', 'wp-maintenance-lite' ); ?></p>
			<?php if ( '' !== $preview ) : ?>
				<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $preview ); ?>" onfocus="this.select();" aria-label="<?php echo esc_attr__( 'Secret preview link', 'wp-maintenance-lite' ); ?>"></p>
			<?php else : ?>
				<p><em><?php echo esc_html__( 'Save the settings once to create the link.', 'wp-maintenance-lite' ); ?></em></p>
			<?php endif; ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::REGENERATE ); ?>">
				<?php wp_nonce_field( self::REGENERATE ); ?>
				<?php submit_button( __( 'Regenerate preview link', 'wp-maintenance-lite' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Create a new preview token. Old links and cookies stop working.
	 */
	public function handle_regenerate(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'wp-maintenance-lite' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::REGENERATE );

		$settings          = $this->plugin->settings();
		$settings['token'] = Token::generate();
		Settings::save( $settings );

		wp_safe_redirect( self::url( array( 'mlite-regenerated' => '1' ) ) );
		$this->finish();
	}

	/**
	 * Notice after regenerating.
	 */
	public function notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( ! isset( $_GET['page'], $_GET['mlite-regenerated'] ) || self::SLUG !== $_GET['page'] ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'New preview link created. The old link no longer works.', 'wp-maintenance-lite' ) . '</p></div>';
	}

	/**
	 * End the request after a redirect (overridable in tests through the filter).
	 */
	private function finish(): void {
		/**
		 * Whether to exit after a redirect. Tests return false.
		 *
		 * @param bool $exit Exit.
		 */
		if ( apply_filters( 'maintenance_lite_exit_after_redirect', true ) ) {
			exit;
		}
	}
}
