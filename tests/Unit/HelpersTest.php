<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests\Unit;

use Saizul\MaintenanceLite\Ip;
use Saizul\MaintenanceLite\Request;
use Saizul\MaintenanceLite\Tests\WpTestCase;
use Saizul\MaintenanceLite\Token;
use WpStubs;

/**
 * Ip, Token and Request helpers.
 */
final class HelpersTest extends WpTestCase {

	public function test_ip_normalize_entry(): void {
		self::assertSame( '203.0.113.7', Ip::normalize_entry( ' 203.0.113.7 ' ) );
		self::assertSame( '203.0.113.0/24', Ip::normalize_entry( '203.0.113.99/24' ) );
		self::assertSame( '2001:db8::/32', Ip::normalize_entry( '2001:DB8:ffff::/32' ) );
		self::assertSame( '0.0.0.0/0', Ip::normalize_entry( '1.2.3.4/0' ) );
		self::assertNull( Ip::normalize_entry( '' ) );
		self::assertNull( Ip::normalize_entry( '300.1.1.1' ) );
		self::assertNull( Ip::normalize_entry( '10.0.0.0/33' ) );
		self::assertNull( Ip::normalize_entry( '10.0.0.0/-1' ) );
		self::assertNull( Ip::normalize_entry( '10.0.0.0/abc' ) );
		self::assertNull( Ip::normalize_entry( '2001:db8::/129' ) );
		self::assertNull( Ip::normalize_entry( 'example.org' ) );
	}

	public function test_ip_matches_ignores_bad_entries(): void {
		self::assertFalse( Ip::matches( 'not-an-ip', array( '0.0.0.0/0' ) ) );
		self::assertFalse( Ip::matches( '10.1.2.3', array( '', 'garbage', 5 ) ) );
		self::assertTrue( Ip::matches( '10.1.2.3', array( 'garbage', '10.0.0.0/8' ) ) );
		self::assertTrue( Ip::matches( '10.1.2.3', array( '10.1.2.2/31' ) ) );
		self::assertFalse( Ip::matches( '10.1.2.3', array( '10.1.2.0/31' ) ) );
	}

	public function test_token_generate_and_validate(): void {
		$token = Token::generate();

		self::assertTrue( Token::is_valid( $token ) );
		self::assertNotSame( $token, Token::generate() );
		self::assertFalse( Token::is_valid( 'short' ) );
		self::assertFalse( Token::is_valid( str_repeat( 'a', 31 ) . '!' ) );
		self::assertFalse( Token::is_valid( null ) );
	}

	public function test_token_matches(): void {
		self::assertTrue( Token::matches( self::TOKEN, self::TOKEN ) );
		self::assertFalse( Token::matches( self::TOKEN, '' ) );
		self::assertFalse( Token::matches( self::TOKEN, strtoupper( self::TOKEN ) ) );
		self::assertFalse( Token::matches( '', '' ) );
	}

	public function test_cookie_value_is_a_keyed_hash_not_the_token(): void {
		$cookie = Token::cookie_value( self::TOKEN );

		self::assertSame( 64, strlen( $cookie ) );
		self::assertStringNotContainsString( self::TOKEN, $cookie );
		self::assertTrue( Token::cookie_matches( self::TOKEN, $cookie ) );
		self::assertFalse( Token::cookie_matches( str_repeat( 'b', 32 ), $cookie ) );
		self::assertFalse( Token::cookie_matches( self::TOKEN, '' ) );
	}

	public function test_relative_path(): void {
		self::assertSame( '/', Request::relative_path( '/', '/' ) );
		self::assertSame( '/shop/cart', Request::relative_path( '/shop/cart?x=1', '/' ) );
		self::assertSame( '/wp-login.php', Request::relative_path( '/blog/wp-login.php', '/blog/' ) );
		self::assertSame( '/', Request::relative_path( '/blog', '/blog' ) );
		self::assertSame( '/blogger', Request::relative_path( '/blogger', '/blog/' ) );
		self::assertSame( '/wp admin', Request::relative_path( '/wp%20admin', '/' ) );
	}

	public function test_request_from_globals(): void {
		$_SERVER['REQUEST_URI'] = '/blog/wp-login.php?action=lostpassword';
		$_SERVER['SCRIPT_NAME'] = '/blog/wp-login.php';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$_COOKIE[ Token::COOKIE ] = 'cookie-value';
		$_GET[ Token::QUERY ]     = self::TOKEN;
		WpStubs::$registry['home'] = 'https://example.org/blog';
		WpStubs::$logged_in        = true;
		WpStubs::$roles            = array( 'editor' );

		$request = Request::from_globals();

		self::assertSame( '/wp-login.php', $request->path );
		self::assertSame( 'wp-login.php', $request->script );
		self::assertSame( '203.0.113.9', $request->ip );
		self::assertSame( 'cookie-value', $request->cookie );
		self::assertSame( self::TOKEN, $request->preview );
		self::assertTrue( $request->logged_in );
		self::assertSame( array( 'editor' ), $request->roles );
		self::assertFalse( $request->is_rest );
	}

	public function test_client_ip_filter_and_validation(): void {
		add_filter(
			'maintenance_lite_client_ip',
			static function () {
				return '192.0.2.44';
			}
		);
		self::assertSame( '192.0.2.44', Request::from_globals()->ip );

		WpStubs::$filters = array();
		$_SERVER['REMOTE_ADDR'] = 'spoofed, 1.2.3.4';
		self::assertSame( '', Request::from_globals()->ip );
	}

	public function test_request_constructor_ignores_unknown_properties(): void {
		$request = new Request( array( 'nope' => 1, 'ip' => '192.0.2.1' ) );

		self::assertSame( '192.0.2.1', $request->ip );
		self::assertFalse( property_exists( $request, 'nope' ) );
	}
}
