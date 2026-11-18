<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests\Unit;

use AdminBarStub;
use Saizul\MaintenanceLite\Admin\AdminBar;
use Saizul\MaintenanceLite\Admin\SettingsPage;
use Saizul\MaintenanceLite\Plugin;
use Saizul\MaintenanceLite\Settings;
use Saizul\MaintenanceLite\Tests\WpTestCase;
use WpDie;
use WpStubs;

/**
 * Settings screen and admin bar.
 */
final class AdminTest extends WpTestCase {

	/** @var Plugin */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin = new Plugin( '/var/www/wp-content/plugins/wp-maintenance-lite/wp-maintenance-lite.php' );
		add_filter( 'maintenance_lite_exit_after_redirect', '__return_false' );
	}

	public function test_register_settings_uses_the_sanitizer(): void {
		( new SettingsPage( $this->plugin ) )->register_settings();
		$args = WpStubs::$registry['settings'][ Settings::OPTION ];

		self::assertSame( array( Settings::class, 'sanitize' ), $args['sanitize_callback'] );
		self::assertFalse( $args['show_in_rest'] );
		self::assertCount( 12, WpStubs::calls( 'field' ) );
		self::assertCount( 4, WpStubs::calls( 'section' ) );
	}

	public function test_settings_sanitize_callback_keeps_the_stored_token(): void {
		self::store();
		$out = Settings::sanitize( array( 'enabled' => '1', 'bypass_roles' => array( 'editor', 'ghost' ) ) );

		self::assertSame( self::TOKEN, $out['token'] );
		self::assertSame( array( 'editor' ), $out['bypass_roles'] );
	}

	public function test_regenerate_requires_capability(): void {
		self::store();
		WpStubs::$can = false;

		$this->expectException( WpDie::class );
		( new SettingsPage( $this->plugin ) )->handle_regenerate();
	}

	public function test_regenerate_requires_nonce(): void {
		self::store();
		WpStubs::$nonce_ok = false;

		try {
			( new SettingsPage( $this->plugin ) )->handle_regenerate();
			self::fail( 'No nonce check.' );
		} catch ( WpDie $die ) {
			self::assertSame( self::TOKEN, WpStubs::$options[ Settings::OPTION ]['token'] );
		}
	}

	public function test_regenerate_replaces_the_token(): void {
		self::store( array( 'headline' => 'Kept' ) );
		( new SettingsPage( $this->plugin ) )->handle_regenerate();
		$stored = WpStubs::$options[ Settings::OPTION ];

		self::assertSame( array( SettingsPage::REGENERATE ), WpStubs::calls( 'check_admin_referer' ) );
		self::assertNotSame( self::TOKEN, $stored['token'] );
		self::assertSame( 32, strlen( $stored['token'] ) );
		self::assertSame( 'Kept', $stored['headline'] );
		self::assertStringContainsString( 'mlite-regenerated=1', WpStubs::$redirect );
	}

	public function test_fields_escape_stored_values(): void {
		self::store( array( 'headline' => '"><script>x</script>', 'ip_allowlist' => array( '203.0.113.7' ) ) );
		$page = new SettingsPage( $this->plugin );

		ob_start();
		$page->field( array( 'key' => 'headline' ) );
		$page->field( array( 'key' => 'ip_allowlist' ) );
		$page->field( array( 'key' => 'bypass_roles' ) );
		$html = (string) ob_get_clean();

		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringContainsString( '&quot;&gt;&lt;script&gt;', $html );
		self::assertStringContainsString( '203.0.113.7', $html );
		self::assertStringContainsString( 'value="administrator"  checked=\'checked\'', $html );
	}

	public function test_render_requires_capability_and_shows_preview_link(): void {
		self::store();
		$page = new SettingsPage( $this->plugin );

		WpStubs::$can = false;
		ob_start();
		$page->render();
		self::assertSame( '', ob_get_clean() );

		WpStubs::$can = true;
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'value="' . esc_attr( $this->plugin->preview_url() ) . '"', $html );
		self::assertStringContainsString( SettingsPage::REGENERATE, $html );
		self::assertStringContainsString( 'name="_wpnonce"', $html );
	}

	public function test_action_links(): void {
		$links = ( new SettingsPage( $this->plugin ) )->action_links( array( 'deactivate' => 'x' ) );
		self::assertStringContainsString( 'page=wp-maintenance-lite', $links[0] );

		WpStubs::$can = false;
		self::assertSame( array( 'a' ), ( new SettingsPage( $this->plugin ) )->action_links( array( 'a' ) ) );
	}

	public function test_admin_bar_node_only_when_active_and_allowed(): void {
		$bar   = new AdminBarStub();
		$admin = new AdminBar( $this->plugin );
		WpStubs::$logged_in = true;

		$admin->add_node( $bar );
		self::assertSame( array(), $bar->nodes );

		self::store();
		$admin->add_node( $bar );
		self::assertSame( 'Maintenance mode on', $bar->nodes[ AdminBar::NODE ]['title'] );
		self::assertStringContainsString( 'page=wp-maintenance-lite', $bar->nodes[ AdminBar::NODE ]['href'] );

		$admin->style();
		self::assertSame( array( 'admin-bar' ), WpStubs::calls( 'inline_style' ) );

		$bar          = new AdminBarStub();
		WpStubs::$can = false;
		$admin->add_node( $bar );
		self::assertSame( array(), $bar->nodes );
	}

	public function test_admin_bar_coming_soon_label(): void {
		self::store( array( 'mode' => Settings::MODE_COMING_SOON ) );
		WpStubs::$logged_in = true;
		$bar                = new AdminBarStub();
		( new AdminBar( $this->plugin ) )->add_node( $bar );

		self::assertSame( 'Coming soon mode on', $bar->nodes[ AdminBar::NODE ]['title'] );
	}
}
