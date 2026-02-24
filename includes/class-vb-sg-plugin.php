<?php
/**
 * Main plugin bootstrap and initialization class.
 *
 * Registers core hooks, loads dependencies and initializes
 * the sitemap generation engine.
 *
 * @package    VB_Sitemap_Generator
 * @since      1.0.0
 * @author     VerBaste
 * @link       https://verbaste.com
 * @copyright  2026 VerBaste
 * @license    GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

final class VB_SG_Plugin {

	/**
	 * Init plugin hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Stage 1: Core URL sitemaps (index + main shards).
		VB_SG_Sitemaps::init();
	}
}
