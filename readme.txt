=== Maintenance Lite ===
Contributors: saizul
Tags: maintenance mode, coming soon, under construction, 503, maintenance
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight maintenance and coming-soon mode without upsells. Real 503 with Retry-After, bypass by role, IP or secret link, countdown and WP-CLI.

== Description ==

Maintenance Lite puts your site behind a simple maintenance or coming-soon page and does it the way search engines expect. No page builder, no subscriber lists, no external services, no upsells.

**SEO safe**

* Maintenance mode answers with HTTP 503 and a Retry-After header, so search engines keep your rankings and come back later.
* Coming-soon mode answers with HTTP 200 and can add noindex, for sites that are not launched yet.
* The page is sent with no-cache headers so caches do not keep it.

**Who can see the site**

* Logged-in users with the roles you choose (Administrator by default).
* Visitors from IP addresses or CIDR ranges on the allowlist (IPv4 and IPv6).
* Anyone with the secret preview link. The link sets a cookie for 7 days; regenerate it to lock out everyone who has the old link.
* wp-login.php, wp-admin, admin-ajax.php, wp-cron.php, WP-CLI and the REST API for logged-in users always work, so you cannot lock yourself out.

**Page**

* Headline, message (basic HTML), logo, background and text colours.
* Optional end time with a live countdown and an option to switch off automatically.
* Replace the whole page with a maintenance-lite.php file in your theme.
* No external fonts, scripts or images unless you set a logo URL.

**For developers**

* WP-CLI: wp maintenance-lite on, off, status.
* Filters for allowed paths, the client IP behind a proxy, the template, the page data, Retry-After and the final decision.
* Emergency switch: define( 'WP_MAINTENANCE_LITE_DISABLE', true ); in wp-config.php.

No tracking, no remote requests. One option per site, removed on uninstall (on every site of a network).

Development happens on GitHub: https://github.com/saizul/wp-maintenance-lite. Contributions are welcome.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/, or install the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin. Nothing changes until you switch the mode on.
3. Go to Settings > Maintenance Lite, check "On", choose the mode and save.
4. Open the site in a private window to see what visitors see.

== Frequently Asked Questions ==

= Will search engines drop my pages while the site is in maintenance? =

Not for short maintenance. The 503 status with Retry-After tells crawlers the outage is temporary. For a long outage (days), use the coming-soon mode only for sites that are not launched yet.

= I am logged in but still see the maintenance page. =

Your role is not in "Roles that see the site". wp-admin always works, so go to Settings > Maintenance Lite and add your role.

= I am locked out. =

wp-login.php and wp-admin are never blocked. If something else is wrong, add define( 'WP_MAINTENANCE_LITE_DISABLE', true ); to wp-config.php, or run wp maintenance-lite off.

= My caching plugin or CDN still shows the maintenance page (or the real site). =

Purge the cache after switching the mode on or off. The page itself is sent with no-cache headers. Configure the cache to skip requests with the wp_maintenance_lite_preview cookie if you use the preview link.

= The IP allowlist does not work behind Cloudflare or a load balancer. =

The plugin only trusts REMOTE_ADDR. Use the maintenance_lite_client_ip filter to read the real visitor address from your proxy's header, after checking the request came from the proxy.

= Can I use my own design? =

Yes. Copy templates/maintenance.php from the plugin to your theme as maintenance-lite.php and edit it.

= What is removed when I delete the plugin? =

The wp_maintenance_lite_settings option, on every site of a network.

== Screenshots ==

1. Settings > Maintenance Lite: status, mode and access rules.
2. The built-in maintenance page with countdown.
3. The admin bar indicator while maintenance mode is on.

== Changelog ==

= 0.1.0 =
* First release: maintenance (503 + Retry-After) and coming-soon (200 + noindex) modes, role, IP and preview-link bypass, end time with countdown and auto-off, theme template override, admin bar indicator, WP-CLI command, developer filters.

== Upgrade Notice ==

= 0.1.0 =
First release.
