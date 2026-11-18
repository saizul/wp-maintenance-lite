<?php
/**
 * Plugin bootstrap.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

use Saizul\MaintenanceLite\Admin\AdminBar;
use Saizul\MaintenanceLite\Admin\SettingsPage;
use Saizul\MaintenanceLite\Cli\Command;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the front-end gate, the settings screen, the admin bar and WP-CLI.
 */
final class Plugin {

	const VERSION = '0.1.0';

	/**
	 * Running instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance;

	/**
	 * Main plugin file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Constructor.
	 *
	 * @param string $file Main plugin file.
	 */
	public function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Create the instance and register the hooks.
	 *
	 * @param string $file Main plugin file.
	 */
	public static function boot( string $file ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $file );
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/**
	 * Running instance, if booted.
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Forget the instance (tests).
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Frontend( $this ) )->register();
		( new AdminBar( $this ) )->register();

		if ( is_admin() ) {
			( new SettingsPage( $this ) )->register();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'maintenance-lite', new Command( $this ) );
		}
	}

	/**
	 * Load translations for installs outside WordPress.org.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wp-maintenance-lite', false, dirname( plugin_basename( $this->file ) ) . '/languages' );
	}

	/**
	 * Current settings.
	 *
	 * @return array<string,mixed>
	 */
	public function settings(): array {
		return Settings::get();
	}

	/**
	 * Whether the mode is switched on and has not ended.
	 */
	public function is_active(): bool {
		if ( self::disabled_by_constant() ) {
			return false;
		}
		$decision = ( new Gate() )->decide( $this->settings(), new Request(), time() );

		return 'disabled' !== $decision->reason && 'ended' !== $decision->reason;
	}

	/**
	 * Decide for a request, with the developer filters applied.
	 *
	 * @param Request $request Request.
	 */
	public function decide( Request $request ): Decision {
		if ( self::disabled_by_constant() ) {
			return Decision::allow( 'constant' );
		}

		/**
		 * Filters extra path prefixes that are never blocked, relative to the
		 * home URL. Useful for custom login URLs or payment webhooks.
		 *
		 * @param string[] $paths Path prefixes such as `/my-login`.
		 */
		$paths    = apply_filters( 'maintenance_lite_allowed_paths', array() );
		$settings = $this->settings();
		$decision = ( new Gate( is_array( $paths ) ? $paths : array() ) )->decide( $settings, $request, time() );

		/**
		 * Filters whether the request sees the maintenance page.
		 *
		 * @param bool     $block    Show the page.
		 * @param Decision $decision Decision of the built-in rules.
		 * @param Request  $request  Request snapshot.
		 */
		$block = (bool) apply_filters( 'maintenance_lite_should_block', $decision->block, $decision, $request );
		if ( $block === $decision->block ) {
			return $decision;
		}
		if ( ! $block ) {
			return Decision::allow( 'filter' );
		}
		if ( Settings::MODE_COMING_SOON === $settings['mode'] && ! $request->is_rest ) {
			return new Decision( true, 'filter', 200, 0 );
		}

		return new Decision( true, 'filter', 503, Gate::retry_after( $settings, time() ) );
	}

	/**
	 * Whether `WP_MAINTENANCE_LITE_DISABLE` is set (emergency switch in wp-config.php).
	 */
	public static function disabled_by_constant(): bool {
		return defined( 'WP_MAINTENANCE_LITE_DISABLE' ) && WP_MAINTENANCE_LITE_DISABLE;
	}

	/**
	 * Capability needed to manage the plugin.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability needed to change the settings and see the admin bar notice.
		 *
		 * @param string $capability Capability, `manage_options` by default.
		 */
		$capability = apply_filters( 'maintenance_lite_capability', 'manage_options' );

		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}

	/**
	 * The secret preview URL.
	 */
	public function preview_url(): string {
		$token = (string) $this->settings()['token'];
		if ( ! Token::is_valid( $token ) ) {
			return '';
		}

		return add_query_arg( Token::QUERY, $token, home_url( '/' ) );
	}

	/**
	 * Main plugin file.
	 */
	public function file(): string {
		return $this->file;
	}
}
