<?php
declare(strict_types=1);

namespace Saizul\MaintenanceLite\Tests\Unit;

use RuntimeException;
use Saizul\MaintenanceLite\Decision;
use Saizul\MaintenanceLite\Frontend;
use Saizul\MaintenanceLite\Plugin;
use Saizul\MaintenanceLite\Settings;
use Saizul\MaintenanceLite\Tests\WpTestCase;
use Saizul\MaintenanceLite\Token;
use WP_Error;
use WpStubs;

final class FrontendTest extends WpTestCase {

	/** @var string[] */
	private $headers = array();

	/** @var array<int,array{0:string,1:string,2:int}> */
	private $cookies = array();

	/** @var Plugin */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();
		$this->headers = array();
		$this->cookies = array();
		$this->plugin  = new Plugin( dirname( __DIR__, 2 ) . '/wp-maintenance-lite.php' );
	}

	private function frontend(): Frontend {
		return new Frontend(
			$this->plugin,
			function ( string $line ): void {
				$this->headers[] = $line;
			},
			function ( string $name, string $value, int $expires ): void {
				$this->cookies[] = array( $name, $value, $expires );
			},
			static function (): void {
				throw new RuntimeException( 'terminated' );
			}
		);
	}

	public function test_register_hooks(): void {
		$frontend = $this->frontend();
		$frontend->register();

		self::assertSame( 1, has_filter( 'init', array( $frontend, 'handle_preview_link' ) ) );
		self::assertSame( 0, has_filter( 'template_redirect', array( $frontend, 'maybe_block' ) ) );
		self::assertSame( 200, has_filter( 'rest_authentication_errors', array( $frontend, 'rest_gate' ) ) );
	}

	public function test_headers_for_maintenance(): void {
		$headers = Frontend::headers_for( new Decision( true, 'blocked', 503, 120 ), self::settings() );

		self::assertSame( array( 'Content-Type: text/html; charset=UTF-8', 'Retry-After: 120' ), $headers );
	}

	public function test_retry_after_filter(): void {
		add_filter(
			'maintenance_lite_retry_after',
			static function () {
				return 0;
			}
		);

		self::assertSame( array( 'Content-Type: text/html; charset=UTF-8' ), Frontend::headers_for( new Decision( true, 'blocked', 503, 120 ), self::settings() ) );
	}

	public function test_headers_for_coming_soon_with_and_without_noindex(): void {
		$decision = new Decision( true, 'blocked', 200, 0 );

		self::assertContains( 'X-Robots-Tag: noindex, nofollow', Frontend::headers_for( $decision, self::settings( array( 'mode' => Settings::MODE_COMING_SOON ) ) ) );
		self::assertNotContains( 'X-Robots-Tag: noindex, nofollow', Frontend::headers_for( $decision, self::settings( array( 'mode' => Settings::MODE_COMING_SOON, 'noindex' => false ) ) ) );
	}

	public function test_preview_link_sets_cookie_and_redirects_to_clean_url(): void {
		self::store();
		$_SERVER['REQUEST_URI'] = '/about/?' . Token::QUERY . '=' . self::TOKEN . '&x=1';
		$_GET[ Token::QUERY ]   = self::TOKEN;

		try {
			$this->frontend()->handle_preview_link();
			self::fail( 'Not terminated.' );
		} catch ( RuntimeException $e ) {
			self::assertSame( 'terminated', $e->getMessage() );
		}

		self::assertSame( Token::COOKIE, $this->cookies[0][0] );
		self::assertSame( Token::cookie_value( self::TOKEN ), $this->cookies[0][1] );
		self::assertGreaterThan( time() + Token::LIFETIME - 5, $this->cookies[0][2] );
		self::assertSame( '/about/?x=1', WpStubs::$redirect );
	}

	public function test_wrong_preview_token_does_nothing(): void {
		self::store();
		$_GET[ Token::QUERY ] = str_repeat( 'x', 32 );
		$this->frontend()->handle_preview_link();

		self::assertSame( array(), $this->cookies );
		self::assertSame( '', WpStubs::$redirect );
	}

	public function test_preview_link_ignored_while_mode_is_off(): void {
		self::store( array( 'enabled' => false ) );
		$_GET[ Token::QUERY ] = self::TOKEN;
		$this->frontend()->handle_preview_link();

		self::assertSame( array(), $this->cookies );
	}

	public function test_preview_cookie_lasts_until_after_a_late_end_time(): void {
		$end = time() + 30 * DAY_IN_SECONDS;
		self::store( array( 'end_time' => $end ) );
		$_GET[ Token::QUERY ] = self::TOKEN;
		WpStubs::$admin       = true;
		$this->frontend()->handle_preview_link();

		self::assertSame( $end + DAY_IN_SECONDS, $this->cookies[0][2] );
		self::assertSame( '', WpStubs::$redirect );
	}

	public function test_rest_gate_blocks_anonymous_requests_with_503(): void {
		self::store( array( 'end_time' => time() + 300 ) );
		$result = $this->frontend()->rest_gate( null );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'maintenance_lite_unavailable', $result->get_error_code() );
		self::assertSame( array( 'status' => 503 ), $result->get_error_data() );
		self::assertMatchesRegularExpression( '/^Retry-After: (29\d|300)$/', $this->headers[0] );
	}

	public function test_rest_gate_allows_logged_in_users_and_keeps_earlier_results(): void {
		self::store();
		WpStubs::$logged_in = true;
		WpStubs::$roles     = array( 'subscriber' );

		self::assertNull( $this->frontend()->rest_gate( null ) );
		self::assertTrue( $this->frontend()->rest_gate( true ) );

		$error = new WP_Error( 'other' );
		self::assertSame( $error, $this->frontend()->rest_gate( $error ) );
	}

	public function test_rest_gate_allows_everything_when_off(): void {
		self::store( array( 'enabled' => false ) );

		self::assertNull( $this->frontend()->rest_gate( null ) );
	}

	public function test_maybe_block_renders_the_page_with_status_and_headers(): void {
		self::store( array( 'headline' => 'Back <soon>', 'message' => '<strong>Hello</strong>' ) );

		ob_start();
		try {
			$this->frontend()->maybe_block();
			self::fail( 'Not terminated.' );
		} catch ( RuntimeException $e ) {
			self::assertSame( 'terminated', $e->getMessage() );
		}
		$html = (string) ob_get_clean();

		self::assertSame( array( '503' ), WpStubs::calls( 'status_header' ) );
		self::assertSame( array( '1' ), WpStubs::calls( 'nocache_headers' ) );
		self::assertContains( 'Retry-After: 3600', $this->headers );
		self::assertContains( 'maintenance_lite_before_render', WpStubs::calls( 'do_action' ) );
		self::assertStringContainsString( '<h1>Back &lt;soon&gt;</h1>', $html );
		self::assertStringContainsString( '<strong>Hello</strong>', $html );
		self::assertStringContainsString( 'name="robots" content="noindex, nofollow"', $html );
		self::assertStringNotContainsString( 'mlite-countdown"', $html );
	}

	public function test_page_shows_countdown_and_logo(): void {
		$end = time() + 3600;
		self::store( array( 'end_time' => $end, 'logo_url' => 'https://example.org/logo.png', 'mode' => Settings::MODE_COMING_SOON, 'noindex' => false ) );

		ob_start();
		try {
			$this->frontend()->maybe_block();
		} catch ( RuntimeException $e ) {
			// Terminated.
		}
		$html = (string) ob_get_clean();

		self::assertSame( array( '200' ), WpStubs::calls( 'status_header' ) );
		self::assertStringContainsString( 'data-end="' . ( $end * 1000 ) . '"', $html );
		self::assertStringContainsString( 'src="https://example.org/logo.png"', $html );
		self::assertStringContainsString( '<h1>Coming soon</h1>', $html );
		self::assertStringNotContainsString( 'name="robots"', $html );
	}

	public function test_maybe_block_does_nothing_for_bypassed_users(): void {
		self::store();
		WpStubs::$logged_in = true;
		WpStubs::$roles     = array( 'administrator' );
		$this->frontend()->maybe_block();

		self::assertSame( array(), WpStubs::calls( 'status_header' ) );
	}

	public function test_theme_template_override_and_filter(): void {
		$file = tempnam( sys_get_temp_dir(), 'mlite' );
		WpStubs::$theme_template = $file;
		self::assertSame( $file, $this->frontend()->template() );

		add_filter(
			'maintenance_lite_template',
			static function () {
				return '/does/not/exist.php';
			}
		);
		self::assertStringEndsWith( '/templates/maintenance.php', $this->frontend()->template() );
		unlink( $file );
	}

	public function test_view_filter(): void {
		add_filter(
			'maintenance_lite_view',
			static function ( array $view ) {
				$view['headline'] = 'Filtered';
				return $view;
			}
		);

		self::assertSame( 'Filtered', Frontend::view( new Decision( true, 'blocked', 503, 60 ), self::settings() )['headline'] );
	}
}
