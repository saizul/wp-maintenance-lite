<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests;

use PHPUnit\Framework\TestCase;
use Saizul\MaintenanceLite\Plugin;
use Saizul\MaintenanceLite\Request;
use Saizul\MaintenanceLite\Settings;
use WP_CLI;
use WpStubs;

/**
 * Base class: resets the WordPress and WP-CLI stubs before every test.
 */
abstract class WpTestCase extends TestCase {

	const TOKEN = 'abcdefghijklmnopqrstuvwxyz012345';

	protected function setUp(): void {
		WpStubs::reset();
		WP_CLI::reset();
		Plugin::reset();
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Settings with maintenance mode on, merged with overrides.
	 *
	 * @param array<string,mixed> $overrides Overrides.
	 * @return array<string,mixed>
	 */
	protected static function settings( array $overrides = array() ): array {
		return array_merge(
			Settings::defaults(),
			array(
				'enabled' => true,
				'token'   => self::TOKEN,
			),
			$overrides
		);
	}

	/**
	 * Store settings in the option stub.
	 *
	 * @param array<string,mixed> $overrides Overrides.
	 */
	protected static function store( array $overrides = array() ): void {
		WpStubs::$options[ Settings::OPTION ] = self::settings( $overrides );
	}

	/**
	 * Anonymous front-end request, merged with overrides.
	 *
	 * @param array<string,mixed> $data Properties.
	 */
	protected static function request( array $data = array() ): Request {
		return new Request( array_merge( array( 'ip' => '198.51.100.10' ), $data ) );
	}
}
