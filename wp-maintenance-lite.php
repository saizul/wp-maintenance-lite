<?php
/**
 * Plugin Name:       Maintenance Lite
 * Plugin URI:        https://github.com/saizul/wp-maintenance-lite
 * Description:       Lightweight maintenance and coming-soon mode without upsells. Sends a real 503 with Retry-After, bypass by role, IP or secret preview link, optional countdown and WP-CLI.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Saizul Amin
 * Author URI:        https://saizul.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-maintenance-lite
 * Domain Path:       /languages
 *
 * @package Saizul\MaintenanceLite
 */

defined( 'ABSPATH' ) || exit;

if ( PHP_VERSION_ID < 70400 ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Maintenance Lite requires PHP 7.4 or newer.', 'wp-maintenance-lite' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'WP_MAINTENANCE_LITE_FILE', __FILE__ );
define( 'WP_MAINTENANCE_LITE_VERSION', '0.1.0' );

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	// PSR-4 autoloader for installs without Composer (the release zip).
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'Saizul\\MaintenanceLite\\';
			if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
				return;
			}
			$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

\Saizul\MaintenanceLite\Plugin::boot( __FILE__ );
