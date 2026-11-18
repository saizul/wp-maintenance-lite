<?php
/**
 * Settings sanitiser.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Turns untrusted input (settings form, WP-CLI, `update_option()`) into a
 * complete, valid settings array. Unknown keys are dropped.
 */
final class Sanitizer {

	const MAX_IPS         = 100;
	const MAX_HEADLINE    = 200;
	const MAX_MESSAGE     = 5000;
	const MIN_RETRY_AFTER = 60;
	const MAX_RETRY_AFTER = 604800;

	/**
	 * Sanitise settings.
	 *
	 * @param mixed               $input    Raw input.
	 * @param array<string,mixed> $previous Current settings (keeps the token).
	 * @param string[]            $roles    Role slugs that exist on the site.
	 * @param DateTimeZone        $timezone Site time zone, for the end time field.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input, array $previous, array $roles, DateTimeZone $timezone ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = Settings::defaults();
		$out      = array();

		$out['enabled']      = self::bool( $input['enabled'] ?? false );
		$out['mode']         = in_array( $input['mode'] ?? '', array( Settings::MODE_MAINTENANCE, Settings::MODE_COMING_SOON ), true ) ? $input['mode'] : Settings::MODE_MAINTENANCE;
		$out['noindex']      = self::bool( $input['noindex'] ?? false );
		$out['bypass_roles'] = self::roles( $input['bypass_roles'] ?? array(), $roles );
		$out['ip_allowlist'] = self::ips( $input['ip_allowlist'] ?? array() );
		$out['end_time']     = self::time( $input['end_time'] ?? 0, $timezone );
		$out['auto_disable'] = self::bool( $input['auto_disable'] ?? false );
		$out['retry_after']  = self::retry_after( $input['retry_after'] ?? $defaults['retry_after'] );

		$out['headline'] = is_scalar( $input['headline'] ?? null ) ? self::cut( sanitize_text_field( (string) $input['headline'] ), self::MAX_HEADLINE ) : '';
		$out['message']  = is_scalar( $input['message'] ?? null ) ? self::cut( trim( wp_kses_post( (string) $input['message'] ) ), self::MAX_MESSAGE ) : '';
		$out['logo_url'] = is_scalar( $input['logo_url'] ?? null ) ? esc_url_raw( trim( (string) $input['logo_url'] ), array( 'http', 'https' ) ) : '';

		$out['background_color'] = self::color( $input['background_color'] ?? '', $defaults['background_color'] );
		$out['text_color']       = self::color( $input['text_color'] ?? '', $defaults['text_color'] );

		if ( Token::is_valid( $input['token'] ?? null ) ) {
			$out['token'] = $input['token'];
		} elseif ( Token::is_valid( $previous['token'] ?? null ) ) {
			$out['token'] = $previous['token'];
		} else {
			$out['token'] = Token::generate();
		}

		return $out;
	}

	/**
	 * Checkbox-like value to bool.
	 *
	 * @param mixed $value Value.
	 */
	public static function bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		return is_string( $value ) && in_array( strtolower( trim( $value ) ), array( '1', 'on', 'yes', 'true' ), true );
	}

	/**
	 * Keep role slugs that exist, in the order of the site's roles.
	 *
	 * @param mixed    $value Submitted roles.
	 * @param string[] $roles Existing role slugs.
	 * @return string[]
	 */
	public static function roles( $value, array $roles ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$value = array_map( 'strval', array_filter( $value, 'is_scalar' ) );

		return array_values( array_intersect( $roles, $value ) );
	}

	/**
	 * Valid, unique IP addresses and CIDR ranges from a list or a text area.
	 *
	 * @param mixed $value Lines, or an array.
	 * @return string[]
	 */
	public static function ips( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}
			$entry = Ip::normalize_entry( $entry );
			if ( null !== $entry && ! in_array( $entry, $out, true ) ) {
				$out[] = $entry;
			}
			if ( count( $out ) >= self::MAX_IPS ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * End time as a Unix timestamp. Accepts a timestamp or the value of a
	 * `datetime-local` field (`2026-12-01T09:30`) in the site time zone.
	 *
	 * @param mixed        $value    Value.
	 * @param DateTimeZone $timezone Site time zone.
	 */
	public static function time( $value, DateTimeZone $timezone ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}
		if ( ! is_string( $value ) ) {
			return 0;
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return 0;
		}
		if ( ctype_digit( $value ) ) {
			return (int) $value;
		}
		foreach ( array( 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s' ) as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, $timezone );
			if ( false !== $date && $date->format( $format ) === $value ) {
				return max( 0, $date->getTimestamp() );
			}
		}

		return 0;
	}

	/**
	 * Retry-After seconds, clamped to one minute ... one week.
	 *
	 * @param mixed $value Value.
	 */
	public static function retry_after( $value ): int {
		$value = is_numeric( $value ) ? (int) $value : Settings::defaults()['retry_after'];

		return max( self::MIN_RETRY_AFTER, min( self::MAX_RETRY_AFTER, $value ) );
	}

	/**
	 * Hex colour or the default.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Default colour.
	 */
	public static function color( $value, string $fallback ): string {
		$color = is_string( $value ) ? sanitize_hex_color( trim( $value ) ) : null;

		return is_string( $color ) && '' !== $color ? strtolower( $color ) : $fallback;
	}

	/**
	 * Limit a string to a number of characters.
	 *
	 * @param string $text   Text.
	 * @param int    $length Maximum length.
	 */
	private static function cut( string $text, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
	}
}
