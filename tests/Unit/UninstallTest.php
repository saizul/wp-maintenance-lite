<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests\Unit;

use Saizul\MaintenanceLite\Settings;
use Saizul\MaintenanceLite\Tests\WpTestCase;
use WpStubs;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UninstallTest extends WpTestCase {

	public function test_deletes_the_option(): void {
		WpStubs::$options[ Settings::OPTION ] = array( 'enabled' => true );
		WpStubs::$options['blogname']         = 'Kept';
		define( 'WP_UNINSTALL_PLUGIN', 'wp-maintenance-lite/wp-maintenance-lite.php' );
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		self::assertArrayNotHasKey( Settings::OPTION, WpStubs::$options );
		self::assertSame( 'Kept', WpStubs::$options['blogname'] );
	}

	public function test_deletes_the_option_on_every_site(): void {
		WpStubs::$registry['multisite']      = true;
		WpStubs::$registry['sites']          = array( 1, 2 );
		WpStubs::$registry['site_options:1'] = array( Settings::OPTION => array() );
		WpStubs::$registry['site_options:2'] = array( Settings::OPTION => array(), 'other' => 1 );
		define( 'WP_UNINSTALL_PLUGIN', 'wp-maintenance-lite/wp-maintenance-lite.php' );
		require dirname( __DIR__, 2 ) . '/uninstall.php';

		self::assertSame( array( '1', '2' ), WpStubs::calls( 'switch_to_blog' ) );
		self::assertSame( array(), WpStubs::$registry['site_options:1'] );
		self::assertSame( array( 'other' => 1 ), WpStubs::$registry['site_options:2'] );
	}
}
