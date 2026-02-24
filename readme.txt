=== VB Sitemap Generator ===
Contributors: verbaste
Tags: sitemap, xml sitemap, seo
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, standards-compliant XML sitemap generator for WordPress.

== Description ==
VB Sitemap Generator creates XML sitemaps dynamically (with caching).

Stage-based development:
* Stage 1: Core URL sitemaps (index + sharded main).
* Stage 2: Images (image sitemap + image entries in main).
* Stage 3: Video sitemap.

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`, or install via WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Open `/sitemap.xml` to verify output.

== Frequently Asked Questions ==
= Where is the sitemap? =
By default: `/sitemap.xml`.

= Does it write files to disk? =
Not initially. Dynamic output + cache. A static file generation option may be added later.

== Changelog ==
= 1.0.0 - 2026-02-24 =
* Stage 1: core sitemaps (/sitemap.xml + sharded /sitemap-main-*.xml).

== Upgrade Notice ==
= 1.0.0 =
Initial release.
