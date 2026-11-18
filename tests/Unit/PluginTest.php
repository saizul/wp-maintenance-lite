<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests\Unit;

use Saizul\MaintenanceLite\Admin\SettingsPage;
use Saizul\MaintenanceLite\Plugin;
use Saizul\MaintenanceLite\Settings;
use Saizul\MaintenanceLite\Tests\WpTestCase;
use Saizul\MaintenanceLite\Token;
use WpStubs;

final class PluginTest extends WpTestCase {

	/** @var Plugin */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin = new Plugin( '/var/www/wp-content/plugins/wp-maintenance-lite/wp-maintenance-lite.php' );
	}

	public function test_boot_registers_front_end_hooks_once(): void {
		$plugin = Plugin::boot( 'x.php' );

		self::assertSame( $plugin, Plugin::boot( 'y.php' ) );
		self::assertSame( $plugin, Plugin::instance() );
		self::assertNotFalse( has_filter( 'template_redirect' ) );
		self::assertNotFalse( has_filter( 'rest_authentication_errors' ) );
		self::assertNotFalse( has_filter( 'admin_bar_menu' ) );
		self::assertFalse( has_filter( 'admin_menu' ) );
	}

	public function test_admin_hooks_only_in_admin(): void {
		WpStubs::$admin = true;
		Plugin::boot( 'x.php' );

		self::assertNotFalse( has_filter( 'admin_menu' ) );
		self::assertNotFalse( has_filter( 'admin_post_' . SettingsPage::REGENERATE ) );
		self::assertNotFalse( has_filter( 'option_page_capability_' . SettingsPage::GROUP ) );
	}

	public function test_load_textdomain(): void {
		$this->plugin->load_textdomain();

		self::assertSame( array( 'wp-maintenance-lite' ), WpStubs::calls( 'load_plugin_textdomain' ) );
	}

	public function test_is_active(): void {
		self::assertFalse( $this->plugin->is_active() );

		self::store();
		self::assertTrue( $this->plugin->is_active() );

		self::store( array( 'end_time' => time() - 1, 'auto_disable' => true ) );
		self::assertFalse( $this->plugin->is_active() );
	}

	public function test_allowed_paths_filter(): void {
		self::store();
		add_filter(
			'maintenance_lite_allowed_paths',
			static function ( array $paths ) {
				$paths[] = '/hooks/payments';
				return $paths;
			}
		);

		self::assertFalse( $this->plugin->decide( self::request( array( 'path' => '/hooks/payments/42' ) ) )->block );
		self::assertTrue( $this->plugin->decide( self::request( array( 'path' => '/shop' ) ) )->block );
	}

	public function test_should_block_filter_can_allow(): void {
		self::store();
		add_filter( 'maintenance_lite_should_block', '__return_false' );
		$decision = $this->plugin->decide( self::request() );

		self::assertFalse( $decision->block );
		self::assertSame( 'filter', $decision->reason );
	}

	public function test_should_block_filter_can_block_with_the_right_status(): void {
		self::store( array( 'bypass_roles' => array( 'administrator' ), 'retry_after' => 120 ) );
		add_filter(
			'maintenance_lite_should_block',
			static function () {
				return true;
			}
		);
		$decision = $this->plugin->decide( self::request( array( 'logged_in' => true, 'roles' => array( 'administrator' ) ) ) );
		self::assertTrue( $decision->block );
		self::assertSame( 503, $decision->status );
		self::assertSame( 120, $decision->retry_after );

		self::store( array( 'mode' => Settings::MODE_COMING_SOON ) );
		self::assertSame( 200, $this->plugin->decide( self::request() )->status );
	}

	public function test_capability_filter(): void {
		self::assertSame( 'manage_options', Plugin::capability() );

		add_filter(
			'maintenance_lite_capability',
			static function () {
				return 'edit_pages';
			}
		);
		self::assertSame( 'edit_pages', Plugin::capability() );

		WpStubs::$filters = array();
		add_filter( 'maintenance_lite_capability', '__return_false' );
		self::assertSame( 'manage_options', Plugin::capability() );
	}

	public function test_preview_url(): void {
		self::assertSame( '', $this->plugin->preview_url() );

		self::store();
		self::assertSame( 'https://example.org/?' . Token::QUERY . '=' . self::TOKEN, $this->plugin->preview_url() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constant_switches_everything_off(): void {
		self::store();
		define( 'WP_MAINTENANCE_LITE_DISABLE', true );

		self::assertFalse( $this->plugin->is_active() );
		self::assertSame( 'constant', $this->plugin->decide( self::request() )->reason );
	}
}
