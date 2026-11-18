<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen.
 *
 * The plugin stores a single option per site. Preview cookies expire on
 * their own and are worthless once the token is gone.
 *
 * @package Saizul\MaintenanceLite
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete the options of the current site.
 */
function wp_maintenance_lite_uninstall_site() {
	delete_option( 'wp_maintenance_lite_settings' );
}

if ( is_multisite() ) {
	$wp_maintenance_lite_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wp_maintenance_lite_site_ids as $wp_maintenance_lite_site_id ) {
		switch_to_blog( (int) $wp_maintenance_lite_site_id );
		wp_maintenance_lite_uninstall_site();
		restore_current_blog();
	}
} else {
	wp_maintenance_lite_uninstall_site();
}
