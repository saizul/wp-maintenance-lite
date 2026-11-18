<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests\Unit;

use Saizul\MaintenanceLite\Cli\Command;
use Saizul\MaintenanceLite\Plugin;
use Saizul\MaintenanceLite\Settings;
use Saizul\MaintenanceLite\Tests\WpTestCase;
use Saizul\MaintenanceLite\Token;
use WP_CLI;
use WpCliHalt;
use WpStubs;

final class CliTest extends WpTestCase {

	/** @var Command */
	private $command;

	protected function setUp(): void {
		parent::setUp();
		$this->command = new Command( new Plugin( '/var/www/wp-content/plugins/wp-maintenance-lite/wp-maintenance-lite.php' ) );
	}

	private function last( string $type ): string {
		$messages = array_values(
			array_filter(
				WP_CLI::$log,
				static function ( array $entry ) use ( $type ): bool {
					return $type === $entry[0];
				}
			)
		);

		return $messages ? (string) end( $messages )[1] : '';
	}

	/** @return array<string,mixed> */
	private function stored(): array {
		return WpStubs::$options[ Settings::OPTION ];
	}

	public function test_on_keeps_other_settings_and_creates_a_token(): void {
		WpStubs::$options[ Settings::OPTION ] = array( 'headline' => 'Kept', 'bypass_roles' => array( 'editor' ) );
		$this->command->on( array(), array() );

		self::assertTrue( $this->stored()['enabled'] );
		self::assertSame( 'Kept', $this->stored()['headline'] );
		self::assertSame( array( 'editor' ), $this->stored()['bypass_roles'] );
		self::assertTrue( Token::is_valid( $this->stored()['token'] ) );
		self::assertSame( 'Maintenance mode is on.', $this->last( 'success' ) );
	}

	public function test_on_with_mode_until_and_auto_off(): void {
		$this->command->on( array(), array( 'mode' => 'coming_soon', 'until' => '2099-01-01 10:00', 'auto-off' => true ) );

		self::assertSame( Settings::MODE_COMING_SOON, $this->stored()['mode'] );
		self::assertSame( gmmktime( 10, 0, 0, 1, 1, 2099 ), $this->stored()['end_time'] );
		self::assertTrue( $this->stored()['auto_disable'] );
		self::assertSame( 'Coming soon mode is on.', $this->last( 'success' ) );

		$this->command->on( array(), array( 'until' => 'none' ) );
		self::assertSame( 0, $this->stored()['end_time'] );
	}

	public function test_on_rejects_bad_mode_and_past_end_time(): void {
		foreach ( array( array( 'mode' => 'party' ), array( 'until' => '2001-01-01 10:00' ), array( 'until' => 'later' ) ) as $assoc ) {
			try {
				$this->command->on( array(), $assoc );
				self::fail( 'No error for ' . json_encode( $assoc ) );
			} catch ( WpCliHalt $halt ) {
				self::assertArrayNotHasKey( Settings::OPTION, WpStubs::$options );
			}
		}
	}

	public function test_off(): void {
		$this->command->off( array(), array() );
		self::assertSame( 'Maintenance mode is already off.', $this->last( 'success' ) );

		self::store( array( 'headline' => 'Kept' ) );
		$this->command->off( array(), array() );
		self::assertFalse( $this->stored()['enabled'] );
		self::assertSame( 'Kept', $this->stored()['headline'] );
		self::assertSame( self::TOKEN, $this->stored()['token'] );
		self::assertSame( 'Maintenance mode is off.', $this->last( 'success' ) );
	}

	public function test_status_json(): void {
		self::store( array( 'ip_allowlist' => array( '203.0.113.0/24' ), 'end_time' => 4102444800 ) );
		$this->command->status( array(), array( 'format' => 'json' ) );
		$status = json_decode( $this->last( 'line' ), true );

		self::assertTrue( $status['active'] );
		self::assertSame( 'maintenance', $status['mode'] );
		self::assertSame( 503, $status['http_status'] );
		self::assertSame( '2100-01-01T00:00:00+00:00', $status['end_time'] );
		self::assertSame( array( '203.0.113.0/24' ), $status['ip_allowlist'] );
		self::assertStringContainsString( self::TOKEN, $status['preview_url'] );
	}

	public function test_status_table(): void {
		$this->command->status( array(), array() );

		self::assertSame( 'table', WP_CLI::$items['format'] );
		$rows = array_column( WP_CLI::$items['items'], 'value', 'field' );
		self::assertSame( 'no', $rows['active'] );
		self::assertSame( '-', $rows['end_time'] );
		self::assertSame( 'administrator', $rows['bypass_roles'] );
	}
}
