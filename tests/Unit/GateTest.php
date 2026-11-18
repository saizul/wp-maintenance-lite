<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests\Unit;

use Saizul\MaintenanceLite\Gate;
use Saizul\MaintenanceLite\Settings;
use Saizul\MaintenanceLite\Tests\WpTestCase;
use Saizul\MaintenanceLite\Token;

final class GateTest extends WpTestCase {

	const NOW = 1800000000;

	private function decide( array $settings, array $request = array(), array $paths = array() ) {
		return ( new Gate( $paths ) )->decide( self::settings( $settings ), self::request( $request ), self::NOW );
	}

	public function test_disabled_lets_everyone_through(): void {
		$decision = $this->decide( array( 'enabled' => false ) );

		self::assertFalse( $decision->block );
		self::assertSame( 'disabled', $decision->reason );
	}

	public function test_anonymous_visitor_gets_503_with_fallback_retry_after(): void {
		$decision = $this->decide( array( 'retry_after' => 900 ) );

		self::assertTrue( $decision->block );
		self::assertSame( 503, $decision->status );
		self::assertSame( 900, $decision->retry_after );
	}

	public function test_retry_after_counts_down_to_the_end_time(): void {
		$decision = $this->decide( array( 'end_time' => self::NOW + 7200 ) );

		self::assertSame( 7200, $decision->retry_after );
	}

	public function test_retry_after_falls_back_once_the_end_time_has_passed(): void {
		$decision = $this->decide( array( 'end_time' => self::NOW - 10, 'retry_after' => 600 ) );

		self::assertTrue( $decision->block );
		self::assertSame( 600, $decision->retry_after );
	}

	public function test_auto_disable_after_the_end_time(): void {
		self::assertSame( 'ended', $this->decide( array( 'end_time' => self::NOW, 'auto_disable' => true ) )->reason );
		self::assertTrue( $this->decide( array( 'end_time' => self::NOW + 1, 'auto_disable' => true ) )->block );
	}

	public function test_coming_soon_uses_200_without_retry_after(): void {
		$decision = $this->decide( array( 'mode' => Settings::MODE_COMING_SOON, 'end_time' => self::NOW + 60 ) );

		self::assertTrue( $decision->block );
		self::assertSame( 200, $decision->status );
		self::assertSame( 0, $decision->retry_after );
	}

	public function test_coming_soon_refuses_anonymous_rest_with_503(): void {
		$decision = $this->decide( array( 'mode' => Settings::MODE_COMING_SOON ), array( 'is_rest' => true ) );

		self::assertTrue( $decision->block );
		self::assertSame( 503, $decision->status );
	}

	public function test_bypass_role(): void {
		$decision = $this->decide(
			array( 'bypass_roles' => array( 'administrator', 'editor' ) ),
			array( 'logged_in' => true, 'roles' => array( 'editor' ) )
		);

		self::assertFalse( $decision->block );
		self::assertSame( 'role', $decision->reason );
	}

	public function test_logged_in_user_without_bypass_role_is_blocked(): void {
		$decision = $this->decide(
			array( 'bypass_roles' => array( 'administrator' ) ),
			array( 'logged_in' => true, 'roles' => array( 'subscriber' ) )
		);

		self::assertTrue( $decision->block );
	}

	public function test_roles_are_ignored_when_not_logged_in(): void {
		self::assertTrue( $this->decide( array(), array( 'logged_in' => false, 'roles' => array( 'administrator' ) ) )->block );
	}

	public function test_no_bypass_roles_blocks_even_administrators(): void {
		$decision = $this->decide( array( 'bypass_roles' => array() ), array( 'logged_in' => true, 'roles' => array( 'administrator' ) ) );

		self::assertTrue( $decision->block );
	}

	/**
	 * @dataProvider ip_cases
	 */
	public function test_ip_allowlist( string $ip, array $allowlist, bool $allowed ): void {
		$decision = $this->decide( array( 'ip_allowlist' => $allowlist ), array( 'ip' => $ip ) );

		self::assertSame( ! $allowed, $decision->block );
		if ( $allowed ) {
			self::assertSame( 'ip', $decision->reason );
		}
	}

	/** @return array<string,array{0:string,1:string[],2:bool}> */
	public function ip_cases(): array {
		return array(
			'exact v4'          => array( '203.0.113.7', array( '203.0.113.7' ), true ),
			'other v4'          => array( '203.0.113.8', array( '203.0.113.7' ), false ),
			'v4 range'          => array( '203.0.113.200', array( '203.0.113.0/24' ), true ),
			'outside v4 range'  => array( '203.0.114.1', array( '203.0.113.0/24' ), false ),
			'v6 range'          => array( '2001:db8:1::5', array( '2001:db8::/32' ), true ),
			'outside v6 range'  => array( '2001:db9::1', array( '2001:db8::/32' ), false ),
			'v4 against v6'     => array( '203.0.113.7', array( '::/0' ), false ),
			'empty client ip'   => array( '', array( '0.0.0.0/0' ), false ),
			'match all v4'      => array( '192.0.2.1', array( '0.0.0.0/0' ), true ),
			'second entry'      => array( '192.0.2.1', array( '10.0.0.0/8', '192.0.2.1' ), true ),
		);
	}

	public function test_valid_preview_cookie(): void {
		$decision = $this->decide( array(), array( 'cookie' => Token::cookie_value( self::TOKEN ) ) );

		self::assertFalse( $decision->block );
		self::assertSame( 'preview', $decision->reason );
	}

	public function test_cookie_of_an_old_token_is_rejected(): void {
		$cookie = Token::cookie_value( self::TOKEN );

		self::assertTrue( $this->decide( array( 'token' => str_repeat( 'Z', 32 ) ), array( 'cookie' => $cookie ) )->block );
	}

	public function test_raw_token_in_cookie_is_rejected(): void {
		self::assertTrue( $this->decide( array(), array( 'cookie' => self::TOKEN ) )->block );
	}

	public function test_preview_query_argument(): void {
		self::assertSame( 'preview', $this->decide( array(), array( 'preview' => self::TOKEN ) )->reason );
		self::assertTrue( $this->decide( array(), array( 'preview' => 'wrong' ) )->block );
	}

	public function test_no_preview_without_a_valid_stored_token(): void {
		self::assertTrue( $this->decide( array( 'token' => '' ), array( 'preview' => '', 'cookie' => '' ) )->block );
		self::assertTrue( $this->decide( array( 'token' => 'short' ), array( 'preview' => 'short' ) )->block );
	}

	/**
	 * @dataProvider allowed_requests
	 */
	public function test_always_allowed_requests( array $request, string $reason ): void {
		$decision = $this->decide( array( 'bypass_roles' => array() ), $request );

		self::assertFalse( $decision->block );
		self::assertSame( 'path:' . $reason, $decision->reason );
	}

	/** @return array<string,array{0:array<string,mixed>,1:string}> */
	public function allowed_requests(): array {
		return array(
			'wp-login.php script' => array( array( 'script' => 'wp-login.php', 'path' => '/wp-login.php' ), 'login' ),
			'wp-login.php path'   => array( array( 'path' => '/wp-login.php' ), 'login' ),
			'wp-admin flag'       => array( array( 'is_admin' => true, 'path' => '/wp-admin/index.php' ), 'admin' ),
			'wp-admin path'       => array( array( 'path' => '/wp-admin/' ), 'admin' ),
			'admin-ajax'          => array( array( 'is_ajax' => true, 'is_admin' => true ), 'ajax' ),
			'admin-ajax script'   => array( array( 'script' => 'admin-ajax.php' ), 'ajax' ),
			'wp-cron'             => array( array( 'is_cron' => true ), 'cron' ),
			'wp-cron script'      => array( array( 'script' => 'wp-cron.php' ), 'cron' ),
			'wp-cli'              => array( array( 'is_cli' => true ), 'cli' ),
			'rest logged in'      => array( array( 'is_rest' => true, 'logged_in' => true, 'roles' => array( 'subscriber' ) ), 'rest' ),
		);
	}

	public function test_anonymous_rest_is_blocked(): void {
		self::assertTrue( $this->decide( array(), array( 'is_rest' => true, 'path' => '/wp-json/wp/v2/posts' ) )->block );
	}

	public function test_paths_that_only_look_like_core_files_are_blocked(): void {
		self::assertTrue( $this->decide( array(), array( 'path' => '/wp-login.php-fake' ) )->block );
		self::assertTrue( $this->decide( array(), array( 'path' => '/wp-administrator' ) )->block );
	}

	public function test_extra_allowed_paths_match_whole_segments(): void {
		$paths = array( '/my-login/', 'webhooks/stripe', '', '/' );

		self::assertSame( 'path:custom', $this->decide( array(), array( 'path' => '/my-login' ), $paths )->reason );
		self::assertSame( 'path:custom', $this->decide( array(), array( 'path' => '/webhooks/stripe/123' ), $paths )->reason );
		self::assertTrue( $this->decide( array(), array( 'path' => '/my-login-page' ), $paths )->block );
		self::assertTrue( $this->decide( array(), array( 'path' => '/' ), $paths )->block );
	}

	public function test_missing_settings_keys_use_defaults(): void {
		$decision = ( new Gate() )->decide( array( 'enabled' => true ), self::request(), self::NOW );

		self::assertTrue( $decision->block );
		self::assertSame( 3600, $decision->retry_after );
	}
}
