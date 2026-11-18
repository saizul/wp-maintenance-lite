<?php
/**
 * Secret preview link token and cookie.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

defined( 'ABSPATH' ) || exit;

/**
 * The preview link carries the token in the query string. A valid token sets
 * a cookie holding a keyed hash of the token (never the token itself), so
 * regenerating the token invalidates every cookie handed out before.
 */
final class Token {

	const QUERY    = 'maintenance-lite-preview';
	const COOKIE   = 'wp_maintenance_lite_preview';
	const LENGTH   = 32;
	const LIFETIME = 604800; // 7 days.

	/**
	 * A new random token.
	 */
	public static function generate(): string {
		return wp_generate_password( self::LENGTH, false, false );
	}

	/**
	 * Whether a string has the shape of a token.
	 *
	 * @param mixed $token Value.
	 */
	public static function is_valid( $token ): bool {
		return is_string( $token ) && 1 === preg_match( '/^[A-Za-z0-9]{' . self::LENGTH . '}$/', $token );
	}

	/**
	 * Whether a supplied value equals the stored token (constant time).
	 *
	 * @param string $token    Stored token.
	 * @param string $supplied Value from the request.
	 */
	public static function matches( string $token, string $supplied ): bool {
		return self::is_valid( $token ) && '' !== $supplied && hash_equals( $token, $supplied );
	}

	/**
	 * The cookie value for a token.
	 *
	 * @param string $token Stored token.
	 */
	public static function cookie_value( string $token ): string {
		return hash_hmac( 'sha256', 'maintenance-lite-preview|' . $token, wp_salt( 'auth' ) );
	}

	/**
	 * Whether a cookie value belongs to the current token.
	 *
	 * @param string $token  Stored token.
	 * @param string $cookie Cookie value from the request.
	 */
	public static function cookie_matches( string $token, string $cookie ): bool {
		return self::is_valid( $token ) && '' !== $cookie && hash_equals( self::cookie_value( $token ), $cookie );
	}
}
