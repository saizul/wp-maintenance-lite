<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests\Unit;

use DateTimeZone;
use Saizul\MaintenanceLite\Sanitizer;
use Saizul\MaintenanceLite\Settings;
use Saizul\MaintenanceLite\Tests\WpTestCase;
use Saizul\MaintenanceLite\Token;

final class SanitizerTest extends WpTestCase {

	const ROLES = array( 'administrator', 'editor', 'subscriber' );

	private function clean( $input, array $previous = array() ): array {
		return Sanitizer::sanitize( $input, $previous, self::ROLES, new DateTimeZone( 'Asia/Dhaka' ) );
	}

	public function test_garbage_input_gives_complete_defaults(): void {
		foreach ( array( null, 'x', 42, array() ) as $input ) {
			$out = $this->clean( $input );

			self::assertSame( array_keys( Settings::defaults() ), array_keys( $out ) );
			self::assertFalse( $out['enabled'] );
			self::assertSame( Settings::MODE_MAINTENANCE, $out['mode'] );
			self::assertSame( array(), $out['bypass_roles'] );
			self::assertTrue( Token::is_valid( $out['token'] ) );
		}
	}

	public function test_unknown_keys_are_dropped(): void {
		self::assertArrayNotHasKey( 'evil', $this->clean( array( 'evil' => 1 ) ) );
	}

	public function test_booleans(): void {
		foreach ( array( '1', 'on', 'yes', 'true', true, 1 ) as $yes ) {
			self::assertTrue( Sanitizer::bool( $yes ), var_export( $yes, true ) );
		}
		foreach ( array( '0', '', 'no', false, 0, 2, null, array( 1 ) ) as $no ) {
			self::assertFalse( Sanitizer::bool( $no ), var_export( $no, true ) );
		}
	}

	public function test_mode_is_whitelisted(): void {
		self::assertSame( Settings::MODE_COMING_SOON, $this->clean( array( 'mode' => 'coming_soon' ) )['mode'] );
		self::assertSame( Settings::MODE_MAINTENANCE, $this->clean( array( 'mode' => 'party' ) )['mode'] );
		self::assertSame( Settings::MODE_MAINTENANCE, $this->clean( array( 'mode' => array( 'coming_soon' ) ) )['mode'] );
	}

	public function test_roles_keep_existing_roles_in_site_order(): void {
		$out = $this->clean( array( 'bypass_roles' => array( 'subscriber', 'ghost', 'administrator', array( 'x' ), 'subscriber' ) ) );

		self::assertSame( array( 'administrator', 'subscriber' ), $out['bypass_roles'] );
		self::assertSame( array(), $this->clean( array( 'bypass_roles' => 'administrator' ) )['bypass_roles'] );
	}

	public function test_ip_allowlist_from_textarea(): void {
		$out = $this->clean(
			array( 'ip_allowlist' => "203.0.113.7\r\n 203.0.113.0/24, nonsense\n2001:DB8::1/128\n10.0.0.1/33\n203.0.113.7\n192.168.1.77/24" )
		);

		self::assertSame( array( '203.0.113.7', '203.0.113.0/24', '2001:db8::1/128', '192.168.1.0/24' ), $out['ip_allowlist'] );
	}

	public function test_ip_allowlist_is_capped(): void {
		$ips = array();
		for ( $i = 0; $i < 150; $i++ ) {
			$ips[] = '10.0.' . intdiv( $i, 250 ) . '.' . ( $i % 250 );
		}

		self::assertCount( Sanitizer::MAX_IPS, $this->clean( array( 'ip_allowlist' => $ips ) )['ip_allowlist'] );
	}

	public function test_end_time_from_datetime_local_in_site_timezone(): void {
		// Asia/Dhaka is UTC+6 without daylight saving.
		$out = $this->clean( array( 'end_time' => '2026-12-01T09:30' ) );

		self::assertSame( gmmktime( 3, 30, 0, 12, 1, 2026 ), $out['end_time'] );
	}

	public function test_end_time_other_formats(): void {
		self::assertSame( 1800000000, $this->clean( array( 'end_time' => 1800000000 ) )['end_time'] );
		self::assertSame( 1800000000, $this->clean( array( 'end_time' => '1800000000' ) )['end_time'] );
		self::assertSame( gmmktime( 3, 30, 15, 12, 1, 2026 ), $this->clean( array( 'end_time' => '2026-12-01 09:30:15' ) )['end_time'] );
		self::assertSame( 0, $this->clean( array( 'end_time' => '' ) )['end_time'] );
		self::assertSame( 0, $this->clean( array( 'end_time' => 'tomorrow' ) )['end_time'] );
		self::assertSame( 0, $this->clean( array( 'end_time' => '2026-13-45T99:99' ) )['end_time'] );
		self::assertSame( 0, $this->clean( array( 'end_time' => -5 ) )['end_time'] );
	}

	public function test_retry_after_is_clamped(): void {
		self::assertSame( 60, $this->clean( array( 'retry_after' => '5' ) )['retry_after'] );
		self::assertSame( 604800, $this->clean( array( 'retry_after' => 99999999 ) )['retry_after'] );
		self::assertSame( 1800, $this->clean( array( 'retry_after' => '1800' ) )['retry_after'] );
		self::assertSame( 3600, $this->clean( array( 'retry_after' => 'soon' ) )['retry_after'] );
	}

	public function test_text_fields(): void {
		$out = $this->clean(
			array(
				'headline' => "  <b>Back</b>\n soon  ",
				'message'  => '<p>See <a href="https://example.org" onclick="x()">status</a></p><script>alert(1)</script>',
			)
		);

		self::assertSame( 'Back soon', $out['headline'] );
		self::assertStringContainsString( '<a href="https://example.org">status</a>', $out['message'] );
		self::assertStringNotContainsString( 'script', $out['message'] );
		self::assertStringNotContainsString( 'onclick', $out['message'] );
		self::assertSame( Sanitizer::MAX_HEADLINE, mb_strlen( $this->clean( array( 'headline' => str_repeat( 'é', 500 ) ) )['headline'] ) );
		self::assertSame( '', $this->clean( array( 'headline' => array( 'x' ) ) )['headline'] );
	}

	public function test_logo_url_allows_http_and_https_only(): void {
		self::assertSame( 'https://example.org/logo.png', $this->clean( array( 'logo_url' => ' https://example.org/logo.png ' ) )['logo_url'] );
		self::assertSame( '', $this->clean( array( 'logo_url' => 'javascript:alert(1)' ) )['logo_url'] );
		self::assertSame( '', $this->clean( array( 'logo_url' => 'data:image/png;base64,AAAA' ) )['logo_url'] );
	}

	public function test_colors(): void {
		$out = $this->clean( array( 'background_color' => '#ABCDEF', 'text_color' => 'red; } body { display:none' ) );

		self::assertSame( '#abcdef', $out['background_color'] );
		self::assertSame( Settings::defaults()['text_color'], $out['text_color'] );
		self::assertSame( '#fff', $this->clean( array( 'text_color' => '#fff' ) )['text_color'] );
	}

	public function test_token_is_kept_from_previous_settings(): void {
		$out = $this->clean( array(), array( 'token' => self::TOKEN ) );

		self::assertSame( self::TOKEN, $out['token'] );
	}

	public function test_valid_token_in_input_wins_invalid_is_ignored(): void {
		$new = str_repeat( 'A', 32 );

		self::assertSame( $new, $this->clean( array( 'token' => $new ), array( 'token' => self::TOKEN ) )['token'] );
		self::assertSame( self::TOKEN, $this->clean( array( 'token' => 'bad token' ), array( 'token' => self::TOKEN ) )['token'] );
	}

	public function test_sanitizing_twice_gives_the_same_result(): void {
		$once = $this->clean(
			array(
				'enabled'      => '1',
				'mode'         => 'coming_soon',
				'noindex'      => '1',
				'bypass_roles' => array( 'editor' ),
				'ip_allowlist' => "203.0.113.0/24\n2001:db8::/32",
				'end_time'     => '2026-12-01T09:30',
				'auto_disable' => '1',
				'retry_after'  => '120',
				'headline'     => 'Hi',
				'message'      => '<strong>Soon</strong>',
				'logo_url'     => 'https://example.org/l.png',
			)
		);

		self::assertSame( $once, $this->clean( $once, $once ) );
	}

	public function test_settings_get_merges_defaults_and_ignores_junk(): void {
		\WpStubs::$options[ Settings::OPTION ] = array( 'enabled' => true, 'junk' => 1 );
		$settings                             = Settings::get();

		self::assertTrue( $settings['enabled'] );
		self::assertArrayNotHasKey( 'junk', $settings );
		self::assertSame( 3600, $settings['retry_after'] );

		\WpStubs::$options[ Settings::OPTION ] = 'broken';
		self::assertSame( Settings::defaults(), Settings::get() );
	}
}
