<?php
/**
 * IP address and CIDR range helpers.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

defined( 'ABSPATH' ) || exit;

/**
 * Validates allowlist entries and matches addresses against them (IPv4 and IPv6).
 */
final class Ip {

	/**
	 * Normalise one allowlist entry: a single address or a CIDR range.
	 *
	 * @param string $entry Raw entry, for example `203.0.113.7` or `2001:db8::/32`.
	 * @return string|null Normalised entry, or null when invalid.
	 */
	public static function normalize_entry( string $entry ): ?string {
		$entry = trim( $entry );
		if ( '' === $entry ) {
			return null;
		}

		$bits = null;
		if ( false !== strpos( $entry, '/' ) ) {
			list( $entry, $bits ) = explode( '/', $entry, 2 );
			if ( ! ctype_digit( $bits ) ) {
				return null;
			}
			$bits = (int) $bits;
		}

		$packed = self::pack( $entry );
		if ( null === $packed ) {
			return null;
		}
		$max = strlen( $packed ) * 8;
		if ( null === $bits ) {
			return (string) inet_ntop( $packed );
		}
		if ( $bits < 0 || $bits > $max ) {
			return null;
		}

		return inet_ntop( self::mask( $packed, $bits ) ) . '/' . $bits;
	}

	/**
	 * Whether an address matches one of the entries.
	 *
	 * @param string   $ip      Client address.
	 * @param string[] $entries Normalised entries.
	 */
	public static function matches( string $ip, array $entries ): bool {
		$packed = self::pack( $ip );
		if ( null === $packed ) {
			return false;
		}
		foreach ( $entries as $entry ) {
			if ( ! is_string( $entry ) || '' === $entry ) {
				continue;
			}
			$bits = null;
			$base = $entry;
			if ( false !== strpos( $entry, '/' ) ) {
				list( $base, $bits ) = explode( '/', $entry, 2 );
				$bits                = (int) $bits;
			}
			$range = self::pack( $base );
			if ( null === $range || strlen( $range ) !== strlen( $packed ) ) {
				continue;
			}
			if ( null === $bits ) {
				$bits = strlen( $packed ) * 8;
			}
			if ( self::mask( $packed, $bits ) === self::mask( $range, $bits ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Binary form of an address, or null.
	 *
	 * @param string $ip Address.
	 */
	private static function pack( string $ip ): ?string {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return null;
		}
		$packed = inet_pton( $ip );

		return false === $packed ? null : $packed;
	}

	/**
	 * Keep the first `$bits` bits of a binary address, zero the rest.
	 *
	 * @param string $packed Binary address.
	 * @param int    $bits   Prefix length.
	 */
	private static function mask( string $packed, int $bits ): string {
		$out    = '';
		$length = strlen( $packed );
		for ( $i = 0; $i < $length; $i++ ) {
			$keep = max( 0, min( 8, $bits - ( $i * 8 ) ) );
			$byte = 0 === $keep ? 0 : ( 0xFF << ( 8 - $keep ) ) & 0xFF;
			$out .= chr( ord( $packed[ $i ] ) & $byte );
		}

		return $out;
	}
}
