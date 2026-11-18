<?php
/**
 * WP-CLI command.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite\Cli;

use Saizul\MaintenanceLite\Plugin;
use Saizul\MaintenanceLite\Sanitizer;
use Saizul\MaintenanceLite\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Switch maintenance mode on or off and check its status.
 *
 * ## EXAMPLES
 *
 *     wp maintenance-lite on
 *     wp maintenance-lite on --mode=coming_soon
 *     wp maintenance-lite on --until="2026-12-01 09:30"
 *     wp maintenance-lite off
 *     wp maintenance-lite status --format=json
 */
final class Command {

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
	 * Switch maintenance mode on.
	 *
	 * ## OPTIONS
	 *
	 * [--mode=<mode>]
	 * : Page mode. Keeps the saved mode when left out.
	 * ---
	 * options:
	 *   - maintenance
	 *   - coming_soon
	 * ---
	 *
	 * [--until=<datetime>]
	 * : Expected end in the site time zone ("2026-12-01 09:30"), or "none" to clear it.
	 *
	 * [--auto-off]
	 * : Switch off automatically at the end time.
	 *
	 * ## EXAMPLES
	 *
	 *     wp maintenance-lite on
	 *     wp maintenance-lite on --until="2026-12-01 09:30" --auto-off
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function on( $args, $assoc_args ): void {
		$settings            = $this->plugin->settings();
		$settings['enabled'] = true;

		if ( isset( $assoc_args['mode'] ) ) {
			if ( ! in_array( $assoc_args['mode'], array( Settings::MODE_MAINTENANCE, Settings::MODE_COMING_SOON ), true ) ) {
				\WP_CLI::error( 'Unknown mode. Use "maintenance" or "coming_soon".' );
			}
			$settings['mode'] = $assoc_args['mode'];
		}
		if ( isset( $assoc_args['until'] ) ) {
			if ( 'none' === $assoc_args['until'] ) {
				$settings['end_time'] = 0;
			} else {
				$end = Sanitizer::time( (string) $assoc_args['until'], wp_timezone() );
				if ( $end <= time() ) {
					\WP_CLI::error( 'The end time must be a future date such as "2026-12-01 09:30" (site time zone).' );
				}
				$settings['end_time'] = $end;
			}
		}
		if ( isset( $assoc_args['auto-off'] ) ) {
			$settings['auto_disable'] = true;
		}

		Settings::save( Sanitizer::sanitize( $settings, $settings, Settings::role_slugs(), wp_timezone() ) );

		$mode = Settings::MODE_COMING_SOON === $settings['mode'] ? 'Coming soon' : 'Maintenance';
		\WP_CLI::success( $mode . ' mode is on.' );
		if ( Plugin::disabled_by_constant() ) {
			\WP_CLI::warning( 'WP_MAINTENANCE_LITE_DISABLE is set, so visitors still see the normal site.' );
		}
	}

	/**
	 * Switch maintenance mode off.
	 *
	 * ## EXAMPLES
	 *
	 *     wp maintenance-lite off
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function off( $args, $assoc_args ): void {
		$settings = $this->plugin->settings();
		if ( ! $settings['enabled'] ) {
			\WP_CLI::success( 'Maintenance mode is already off.' );
			return;
		}
		$settings['enabled'] = false;
		Settings::save( Sanitizer::sanitize( $settings, $settings, Settings::role_slugs(), wp_timezone() ) );
		\WP_CLI::success( 'Maintenance mode is off.' );
	}

	/**
	 * Show whether maintenance mode is on.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp maintenance-lite status
	 *     wp maintenance-lite status --format=json
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function status( $args, $assoc_args ): void {
		$settings = $this->plugin->settings();
		$end      = (int) $settings['end_time'];
		$status   = array(
			'active'               => $this->plugin->is_active(),
			'enabled'              => (bool) $settings['enabled'],
			'mode'                 => (string) $settings['mode'],
			'http_status'          => Settings::MODE_COMING_SOON === $settings['mode'] ? 200 : 503,
			'end_time'             => $end > 0 ? gmdate( 'c', $end ) : null,
			'auto_off'             => (bool) $settings['auto_disable'],
			'bypass_roles'         => array_values( (array) $settings['bypass_roles'] ),
			'ip_allowlist'         => array_values( (array) $settings['ip_allowlist'] ),
			'preview_url'          => $this->plugin->preview_url(),
			'disabled_by_constant' => Plugin::disabled_by_constant(),
		);

		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			\WP_CLI::line( (string) wp_json_encode( $status ) );
			return;
		}

		$rows = array();
		foreach ( $status as $key => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
			} elseif ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			} elseif ( null === $value ) {
				$value = '-';
			}
			$rows[] = array(
				'field' => $key,
				'value' => (string) $value,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
	}
}
