<?php
/**
 * Front-end gate: preview cookie, maintenance page and REST API.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the gate's decision to real requests.
 */
final class Frontend {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Sends a header line. Replaced in tests.
	 *
	 * @var callable
	 */
	private $send_header;

	/**
	 * Sets a cookie: ( name, value, expires ). Replaced in tests.
	 *
	 * @var callable
	 */
	private $set_cookie;

	/**
	 * Ends the request. Replaced in tests.
	 *
	 * @var callable
	 */
	private $terminate;

	/**
	 * Constructor.
	 *
	 * @param Plugin        $plugin      Plugin.
	 * @param callable|null $send_header Header sender.
	 * @param callable|null $set_cookie  Cookie setter.
	 * @param callable|null $terminate   Request terminator.
	 */
	public function __construct( Plugin $plugin, ?callable $send_header = null, ?callable $set_cookie = null, ?callable $terminate = null ) {
		$this->plugin      = $plugin;
		$this->send_header = $send_header ?? static function ( string $line ): void {
			if ( ! headers_sent() ) {
				header( $line );
			}
		};
		$this->set_cookie  = $set_cookie ?? static function ( string $name, string $value, int $expires ): void {
			if ( headers_sent() ) {
				return;
			}
			setcookie(
				$name,
				$value,
				array(
					'expires'  => $expires,
					'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		};
		$this->terminate   = $terminate ?? static function (): void {
			exit;
		};
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'handle_preview_link' ), 1 );
		add_action( 'template_redirect', array( $this, 'maybe_block' ), 0 );
		// After core's cookie and nonce check (priority 100), so logged-in means authenticated.
		add_filter( 'rest_authentication_errors', array( $this, 'rest_gate' ), 200 );
	}

	/**
	 * A valid preview link sets the preview cookie and redirects to the clean URL.
	 */
	public function handle_preview_link(): void {
		$request  = Request::from_globals();
		$settings = $this->plugin->settings();
		if ( ! $settings['enabled'] || '' === $request->preview ) {
			return;
		}
		$token = (string) $settings['token'];
		if ( ! Token::matches( $token, $request->preview ) ) {
			return;
		}

		$expires = time() + Token::LIFETIME;
		if ( (int) $settings['end_time'] > time() ) {
			$expires = max( $expires, (int) $settings['end_time'] + DAY_IN_SECONDS );
		}
		( $this->set_cookie )( Token::COOKIE, Token::cookie_value( $token ), $expires );
		$_COOKIE[ Token::COOKIE ] = Token::cookie_value( $token );

		if ( $request->is_admin || $request->is_ajax || $request->is_rest ) {
			return;
		}
		nocache_headers();
		wp_safe_redirect( remove_query_arg( Token::QUERY ) );
		( $this->terminate )();
	}

	/**
	 * Show the maintenance page when the gate says so.
	 */
	public function maybe_block(): void {
		$decision = $this->plugin->decide( Request::from_globals() );
		if ( $decision->block ) {
			$this->render( $decision );
		}
	}

	/**
	 * Refuse REST API requests from visitors who would see the maintenance page.
	 *
	 * @param mixed $result Result of earlier authentication handlers.
	 * @return mixed
	 */
	public function rest_gate( $result ) {
		if ( null !== $result ) {
			return $result;
		}
		$request          = Request::from_globals();
		$request->is_rest = true;
		$decision         = $this->plugin->decide( $request );
		if ( ! $decision->block ) {
			return $result;
		}
		if ( $decision->retry_after > 0 ) {
			( $this->send_header )( 'Retry-After: ' . $decision->retry_after );
		}

		return new WP_Error(
			'maintenance_lite_unavailable',
			__( 'The site is in maintenance mode. Please try again later.', 'wp-maintenance-lite' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Header lines for the maintenance page.
	 *
	 * @param Decision            $decision Decision.
	 * @param array<string,mixed> $settings Settings.
	 * @return string[]
	 */
	public static function headers_for( Decision $decision, array $settings ): array {
		$headers = array( 'Content-Type: text/html; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		if ( 503 === $decision->status ) {
			/**
			 * Filters the Retry-After value in seconds.
			 *
			 * @param int $seconds Seconds until the end time, or the fallback setting.
			 */
			$retry_after = (int) apply_filters( 'maintenance_lite_retry_after', $decision->retry_after );
			if ( $retry_after > 0 ) {
				$headers[] = 'Retry-After: ' . $retry_after;
			}
		}
		if ( Settings::MODE_COMING_SOON === ( $settings['mode'] ?? '' ) && ! empty( $settings['noindex'] ) ) {
			$headers[] = 'X-Robots-Tag: noindex, nofollow';
		}

		return $headers;
	}

	/**
	 * Data handed to the template as `$maintenance_lite`.
	 *
	 * @param Decision            $decision Decision.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	public static function view( Decision $decision, array $settings ): array {
		$coming_soon = Settings::MODE_COMING_SOON === $settings['mode'];
		$headline    = (string) $settings['headline'];
		if ( '' === $headline ) {
			$headline = $coming_soon ? __( 'Coming soon', 'wp-maintenance-lite' ) : __( 'Down for maintenance', 'wp-maintenance-lite' );
		}
		$message = (string) $settings['message'];
		if ( '' === $message ) {
			$message = $coming_soon
				? __( 'We are getting ready. Please check back soon.', 'wp-maintenance-lite' )
				: __( 'We are doing some scheduled maintenance and will be back shortly.', 'wp-maintenance-lite' );
		}
		$end_time = (int) $settings['end_time'];

		$view = array(
			'mode'             => $settings['mode'],
			'status'           => $decision->status,
			'site_name'        => (string) get_bloginfo( 'name' ),
			'headline'         => $headline,
			'message'          => $message,
			'logo_url'         => (string) $settings['logo_url'],
			'background_color' => (string) $settings['background_color'],
			'text_color'       => (string) $settings['text_color'],
			'noindex'          => $coming_soon ? (bool) $settings['noindex'] : true,
			'end_time'         => $end_time > time() ? $end_time : 0,
			'end_label'        => $end_time > time() ? (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $end_time ) : '',
		);

		/**
		 * Filters the data passed to the maintenance template.
		 *
		 * @param array<string,mixed> $view     Template data.
		 * @param Decision            $decision Decision.
		 */
		$filtered = apply_filters( 'maintenance_lite_view', $view, $decision );

		return is_array( $filtered ) ? array_merge( $view, $filtered ) : $view;
	}

	/**
	 * Template file: the theme's `maintenance-lite.php`, a filtered path, or the built-in one.
	 */
	public function template(): string {
		$default = dirname( $this->plugin->file() ) . '/templates/maintenance.php';
		$theme   = locate_template( array( 'maintenance-lite.php' ) );

		/**
		 * Filters the maintenance template path.
		 *
		 * @param string $template Absolute path.
		 */
		$template = apply_filters( 'maintenance_lite_template', '' !== $theme ? $theme : $default );

		return is_string( $template ) && '' !== $template && is_readable( $template ) ? $template : $default;
	}

	/**
	 * Send the headers, print the page and end the request.
	 *
	 * @param Decision $decision Decision.
	 */
	public function render( Decision $decision ): void {
		$settings = $this->plugin->settings();

		/**
		 * Fires before the maintenance page is sent.
		 *
		 * @param Decision $decision Decision.
		 */
		do_action( 'maintenance_lite_before_render', $decision );

		status_header( $decision->status );
		nocache_headers();
		foreach ( self::headers_for( $decision, $settings ) as $line ) {
			( $this->send_header )( $line );
		}

		$maintenance_lite = self::view( $decision, $settings );
		include $this->template();

		( $this->terminate )();
	}
}
