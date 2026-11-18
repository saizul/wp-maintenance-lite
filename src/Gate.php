<?php
/**
 * Request decision logic.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a request sees the maintenance page. No side effects: it
 * only reads the settings, the request snapshot and the current time.
 *
 * Order of the rules:
 *
 * 1. Mode off, or the end time passed with "switch off automatically" on.
 * 2. WP-CLI, wp-login.php, wp-admin, admin-ajax.php, wp-cron.php, extra
 *    allowed paths, and the REST API for logged-in users.
 * 3. Logged-in users with a bypass role.
 * 4. Client IP on the allowlist.
 * 5. Valid preview link or preview cookie.
 * 6. Everyone else: 503 with Retry-After (maintenance) or 200 (coming soon).
 */
final class Gate {

	/**
	 * Extra path prefixes that are never blocked.
	 *
	 * @var string[]
	 */
	private $allowed_paths;

	/**
	 * Constructor.
	 *
	 * @param string[] $allowed_paths Extra path prefixes, for example `/my-login`.
	 */
	public function __construct( array $allowed_paths = array() ) {
		$this->allowed_paths = array();
		foreach ( $allowed_paths as $path ) {
			if ( is_string( $path ) && '' !== trim( $path, '/ ' ) ) {
				$this->allowed_paths[] = '/' . trim( $path, '/ ' );
			}
		}
	}

	/**
	 * Decide.
	 *
	 * @param array<string,mixed> $settings Settings (see Settings::defaults()).
	 * @param Request             $request  Request.
	 * @param int                 $now      Current Unix time.
	 */
	public function decide( array $settings, Request $request, int $now ): Decision {
		$settings = array_merge( Settings::defaults(), $settings );
		$end_time = (int) $settings['end_time'];

		if ( ! $settings['enabled'] ) {
			return Decision::allow( 'disabled' );
		}
		if ( $settings['auto_disable'] && $end_time > 0 && $now >= $end_time ) {
			return Decision::allow( 'ended' );
		}

		$path = $this->allowed_path( $request );
		if ( null !== $path ) {
			return Decision::allow( 'path:' . $path );
		}

		if ( $request->logged_in && array() !== array_intersect( (array) $settings['bypass_roles'], $request->roles ) ) {
			return Decision::allow( 'role' );
		}
		if ( '' !== $request->ip && Ip::matches( $request->ip, (array) $settings['ip_allowlist'] ) ) {
			return Decision::allow( 'ip' );
		}

		$token = (string) $settings['token'];
		if ( Token::matches( $token, $request->preview ) || Token::cookie_matches( $token, $request->cookie ) ) {
			return Decision::allow( 'preview' );
		}

		if ( Settings::MODE_COMING_SOON === $settings['mode'] && ! $request->is_rest ) {
			return new Decision( true, 'blocked', 200, 0 );
		}

		return new Decision( true, 'blocked', 503, self::retry_after( $settings, $now ) );
	}

	/**
	 * Seconds until the end time, or the configured fallback.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param int                 $now      Current Unix time.
	 */
	public static function retry_after( array $settings, int $now ): int {
		$end_time = (int) ( $settings['end_time'] ?? 0 );
		if ( $end_time > $now ) {
			return $end_time - $now;
		}

		return Sanitizer::retry_after( $settings['retry_after'] ?? null );
	}

	/**
	 * Name of the rule that always allows this request, or null.
	 *
	 * @param Request $request Request.
	 */
	private function allowed_path( Request $request ): ?string {
		if ( $request->is_cli ) {
			return 'cli';
		}
		if ( $request->is_cron || 'wp-cron.php' === $request->script ) {
			return 'cron';
		}
		if ( $request->is_ajax || 'admin-ajax.php' === $request->script ) {
			return 'ajax';
		}
		if ( 'wp-login.php' === $request->script || self::starts_with( $request->path, '/wp-login.php' ) ) {
			return 'login';
		}
		if ( $request->is_admin || self::starts_with( $request->path, '/wp-admin' ) ) {
			return 'admin';
		}
		if ( $request->is_rest && $request->logged_in ) {
			return 'rest';
		}
		foreach ( $this->allowed_paths as $prefix ) {
			if ( self::starts_with( $request->path, $prefix ) ) {
				return 'custom';
			}
		}

		return null;
	}

	/**
	 * Whether a path equals a prefix or is below it (`/shop` matches `/shop/cart`, not `/shopping`).
	 *
	 * @param string $path   Path.
	 * @param string $prefix Prefix.
	 */
	private static function starts_with( string $path, string $prefix ): bool {
		return $path === $prefix || 0 === strpos( $path, rtrim( $prefix, '/' ) . '/' );
	}
}
