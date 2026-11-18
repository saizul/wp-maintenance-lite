<?php
/**
 * The facts about the current request that the gate needs.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable snapshot of the request, so the decision logic is a pure function.
 */
final class Request {

	/**
	 * Path relative to the site's home path, with a leading slash, for example `/shop/cart`.
	 *
	 * @var string
	 */
	public $path = '/';

	/**
	 * File name of the entry script, for example `index.php` or `wp-login.php`.
	 *
	 * @var string
	 */
	public $script = 'index.php';

	/**
	 * Request for an admin screen.
	 *
	 * @var bool
	 */
	public $is_admin = false;

	/**
	 * Request to admin-ajax.php.
	 *
	 * @var bool
	 */
	public $is_ajax = false;

	/**
	 * Request to wp-cron.php.
	 *
	 * @var bool
	 */
	public $is_cron = false;

	/**
	 * WP-CLI.
	 *
	 * @var bool
	 */
	public $is_cli = false;

	/**
	 * REST API request.
	 *
	 * @var bool
	 */
	public $is_rest = false;

	/**
	 * Logged-in user.
	 *
	 * @var bool
	 */
	public $logged_in = false;

	/**
	 * Roles of the current user.
	 *
	 * @var string[]
	 */
	public $roles = array();

	/**
	 * Client address.
	 *
	 * @var string
	 */
	public $ip = '';

	/**
	 * Value of the preview cookie.
	 *
	 * @var string
	 */
	public $cookie = '';

	/**
	 * Value of the preview query argument.
	 *
	 * @var string
	 */
	public $preview = '';

	/**
	 * Build a request from an array of properties (tests, filters).
	 *
	 * @param array<string,mixed> $data Properties.
	 */
	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Snapshot of the current request.
	 */
	public static function from_globals(): self {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only checks; the token is compared in constant time.
		$uri     = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$script  = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : 'index.php';
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$cookie  = isset( $_COOKIE[ Token::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ Token::COOKIE ] ) ) : '';
		$preview = isset( $_GET[ Token::QUERY ] ) ? sanitize_text_field( wp_unslash( $_GET[ Token::QUERY ] ) ) : '';
		// phpcs:enable

		/**
		 * Filters the client IP used for the allowlist.
		 *
		 * Only REMOTE_ADDR is trusted by default. Behind a reverse proxy or CDN
		 * you can return the address from a header your proxy sets, after
		 * checking that the request came from the proxy.
		 *
		 * @param string $ip Client address.
		 */
		$ip = (string) apply_filters( 'maintenance_lite_client_ip', $ip );
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '';
		}

		$user = wp_get_current_user();

		return new self(
			array(
				'path'      => self::relative_path( $uri, (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ),
				'script'    => '' === $script ? 'index.php' : $script,
				'is_admin'  => is_admin(),
				'is_ajax'   => wp_doing_ajax(),
				'is_cron'   => wp_doing_cron(),
				'is_cli'    => defined( 'WP_CLI' ) && WP_CLI,
				'is_rest'   => defined( 'REST_REQUEST' ) && REST_REQUEST,
				'logged_in' => is_user_logged_in(),
				'roles'     => is_array( $user->roles ) ? array_values( array_map( 'strval', $user->roles ) ) : array(),
				'ip'        => $ip,
				'cookie'    => $cookie,
				'preview'   => $preview,
			)
		);
	}

	/**
	 * Path of a request URI relative to the home path.
	 *
	 * @param string $uri       Request URI, may include a query string.
	 * @param string $home_path Path of the home URL, for example `/` or `/blog/`.
	 */
	public static function relative_path( string $uri, string $home_path ): string {
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = '/' . ltrim( rawurldecode( $path ), '/' );
		$home = '/' . trim( $home_path, '/' );
		if ( '/' !== $home && ( $path === $home || 0 === strpos( $path, $home . '/' ) ) ) {
			$path = '/' . ltrim( substr( $path, strlen( $home ) ), '/' );
		}

		return $path;
	}
}
