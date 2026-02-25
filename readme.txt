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
VB Sitemap Generator creates XML sitemaps dynamically with caching and without unnecessary overhead.

Features:
* Sitemap index: /sitemap.xml
* Sharded main sitemaps: /sitemap-main-*.xml
* Image sitemap: /sitemap-images-*.xml
* Includes images in main sitemap entries
* Publish-only URLs
* Respects noindex
* Uses post_modified_gmt for lastmod
* Robots.txt integration

The plugin follows modern XML sitemap standards and avoids deprecated elements like changefreq and priority.

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`, or install via the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Open `/sitemap.xml` to verify output.

== Frequently Asked Questions ==

= Where is the sitemap? =
By default: `/sitemap.xml`.

= Does it write files to disk? =
No. Sitemaps are generated dynamically and cached. Static file generation may be added in future versions.

== Changelog ==

= 1.0.0 - 2026-02-24 =
* Initial release.
* Sitemap index (/sitemap.xml)
* Sharded main sitemap (/sitemap-main-*.xml)
* Image sitemap support
* Robots.txt integration

== Upgrade Notice ==

= 1.0.0 =
Initial release.
