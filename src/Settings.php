<?php
/**
 * Stored settings.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite;

defined( 'ABSPATH' ) || exit;

/**
 * One option row per site holds every setting.
 */
final class Settings {

	const OPTION = 'wp_maintenance_lite_settings';

	const MODE_MAINTENANCE = 'maintenance';
	const MODE_COMING_SOON = 'coming_soon';

	/**
	 * Default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'          => false,
			'mode'             => self::MODE_MAINTENANCE,
			'noindex'          => true,
			'bypass_roles'     => array( 'administrator' ),
			'ip_allowlist'     => array(),
			'end_time'         => 0,
			'auto_disable'     => false,
			'retry_after'      => 3600,
			'headline'         => '',
			'message'          => '',
			'logo_url'         => '',
			'background_color' => '#1d2327',
			'text_color'       => '#f0f0f1',
			'token'            => '',
		);
	}

	/**
	 * Current settings, merged with the defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
	}

	/**
	 * Store settings that have already been sanitised.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	public static function save( array $settings ): void {
		update_option( self::OPTION, array_merge( self::defaults(), array_intersect_key( $settings, self::defaults() ) ) );
	}

	/**
	 * Slugs of the roles of this site.
	 *
	 * @return string[]
	 */
	public static function role_slugs(): array {
		$roles = wp_roles();

		return array_map( 'strval', array_keys( is_array( $roles->roles ) ? $roles->roles : array() ) );
	}

	/**
	 * Sanitise raw input against the current settings and site roles.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		return Sanitizer::sanitize( $input, self::get(), self::role_slugs(), wp_timezone() );
	}
}
