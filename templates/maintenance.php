<?php
/**
 * Built-in maintenance / coming-soon page.
 *
 * Copy this file to your theme as `maintenance-lite.php` to override it. The
 * `$maintenance_lite` array holds: mode, status, site_name, headline,
 * message (already filtered with wp_kses_post), logo_url, background_color,
 * text_color, noindex, end_time (Unix time or 0) and end_label.
 *
 * Everything is inline: no external requests, no fonts, no tracking.
 *
 * @package Saizul\MaintenanceLite
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $maintenance_lite ) || ! is_array( $maintenance_lite ) ) {
	return;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php echo esc_attr( get_option( 'blog_charset', 'UTF-8' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( $maintenance_lite['noindex'] ) : ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<title><?php echo esc_html( $maintenance_lite['headline'] . ' - ' . $maintenance_lite['site_name'] ); ?></title>
<style>
	html, body { height: 100%; margin: 0; }
	body {
		display: flex; align-items: center; justify-content: center;
		background: <?php echo esc_html( $maintenance_lite['background_color'] ); ?>;
		color: <?php echo esc_html( $maintenance_lite['text_color'] ); ?>;
		font: 18px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
		text-align: center;
	}
	main { max-width: 40rem; padding: 2rem; }
	img { max-width: 220px; max-height: 120px; height: auto; margin-bottom: 1.5rem; }
	h1 { font-size: 2.2rem; line-height: 1.2; margin: 0 0 1rem; }
	a { color: inherit; }
	.mlite-countdown { font-size: 1.6rem; font-variant-numeric: tabular-nums; margin-top: 1.5rem; letter-spacing: .04em; }
	.mlite-until { opacity: .8; font-size: .95rem; }
</style>
</head>
<body class="maintenance-lite maintenance-lite--<?php echo esc_attr( $maintenance_lite['mode'] ); ?>">
<main>
	<?php if ( '' !== $maintenance_lite['logo_url'] ) : ?>
		<img src="<?php echo esc_url( $maintenance_lite['logo_url'] ); ?>" alt="<?php echo esc_attr( $maintenance_lite['site_name'] ); ?>">
	<?php endif; ?>
	<h1><?php echo esc_html( $maintenance_lite['headline'] ); ?></h1>
	<div class="mlite-message"><?php echo wp_kses_post( wpautop( $maintenance_lite['message'] ) ); ?></div>
	<?php if ( $maintenance_lite['end_time'] > 0 ) : ?>
		<div class="mlite-countdown" data-end="<?php echo esc_attr( (string) ( (int) $maintenance_lite['end_time'] * 1000 ) ); ?>" aria-live="polite"></div>
		<p class="mlite-until">
			<?php
			/* translators: %s: date and time when the site is expected back. */
			echo esc_html( sprintf( __( 'Expected back by %s', 'wp-maintenance-lite' ), $maintenance_lite['end_label'] ) );
			?>
		</p>
		<script>
		( function () {
			var el = document.querySelector( '.mlite-countdown' );
			if ( ! el ) { return; }
			var end = parseInt( el.getAttribute( 'data-end' ), 10 );
			function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }
			function tick() {
				var left = Math.max( 0, Math.floor( ( end - Date.now() ) / 1000 ) );
				var d = Math.floor( left / 86400 ), h = Math.floor( left % 86400 / 3600 ), m = Math.floor( left % 3600 / 60 ), s = left % 60;
				el.textContent = ( d > 0 ? d + 'd ' : '' ) + pad( h ) + ':' + pad( m ) + ':' + pad( s );
				if ( left > 0 ) { window.setTimeout( tick, 1000 ); }
			}
			tick();
		}() );
		</script>
	<?php endif; ?>
</main>
</body>
</html>
