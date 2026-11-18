<?php
/**
 * Admin bar indicator.
 *
 * @package Saizul\MaintenanceLite
 */

namespace Saizul\MaintenanceLite\Admin;

use Saizul\MaintenanceLite\Plugin;
use Saizul\MaintenanceLite\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shows "Maintenance mode on" in the admin bar while the mode is active.
 */
final class AdminBar {

	const NODE = 'wp-maintenance-lite';

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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_bar_menu', array( $this, 'add_node' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'style' ) );
	}

	/**
	 * Whether the current user should see the indicator.
	 */
	private function visible(): bool {
		return is_user_logged_in() && current_user_can( Plugin::capability() ) && $this->plugin->is_active();
	}

	/**
	 * Add the node.
	 *
	 * @param mixed $admin_bar WP_Admin_Bar instance.
	 */
	public function add_node( $admin_bar ): void {
		if ( ! is_object( $admin_bar ) || ! method_exists( $admin_bar, 'add_node' ) || ! $this->visible() ) {
			return;
		}
		$coming_soon = Settings::MODE_COMING_SOON === $this->plugin->settings()['mode'];
		$admin_bar->add_node(
			array(
				'id'     => self::NODE,
				'parent' => 'top-secondary',
				'title'  => esc_html( $coming_soon ? __( 'Coming soon mode on', 'wp-maintenance-lite' ) : __( 'Maintenance mode on', 'wp-maintenance-lite' ) ),
				'href'   => esc_url( SettingsPage::url() ),
				'meta'   => array(
					'class' => 'wp-maintenance-lite-active',
					'title' => esc_attr__( 'Visitors see the maintenance page. Click to change the settings.', 'wp-maintenance-lite' ),
				),
			)
		);
	}

	/**
	 * Colour the node.
	 */
	public function style(): void {
		if ( ! is_admin_bar_showing() || ! $this->visible() ) {
			return;
		}
		wp_add_inline_style( 'admin-bar', '#wpadminbar .wp-maintenance-lite-active > .ab-item{background:#b32d2e;color:#fff}' );
	}
}
