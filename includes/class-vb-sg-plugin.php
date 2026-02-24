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

Jasne — od teraz dostajesz pełną zawartość plików (kopiuj/wklej), bez “opisu co zrobić”.

Poniżej masz komplet dla VB Sitemap Generator 1.0.0 (Etap 1: index + sharded main, dynamic + cache, invalidacje, CPT+tax+archives). Obrazy/video jeszcze nie są emitowane (to Etap 2/3), ale architektura i filtry są przygotowane.

vb-sitemap-generator.php
<?php
/**
 * Plugin Name:       VB Sitemap Generator
 * Description:       Lightweight, standards-compliant XML sitemap generator (dynamic + cache). Stage-based: core first, then images, then video.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            VerBaste
 * Author URI:        https://verbaste.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vb-sitemap-generator
 * Domain Path:       /languages
 *
 * @package VB_Sitemap_Generator
 */

defined( 'ABSPATH' ) || exit;

define( 'VB_SG_VERSION', '1.0.0' );
define( 'VB_SG_PLUGIN_FILE', __FILE__ );
define( 'VB_SG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once VB_SG_PLUGIN_DIR . 'includes/class-vb-sg-plugin.php';
require_once VB_SG_PLUGIN_DIR . 'includes/class-vb-sg-sitemaps.php';

register_activation_hook( __FILE__, array( 'VB_SG_Sitemaps', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VB_SG_Sitemaps', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'VB_SG_Plugin', 'init' ) );
includes/class-vb-sg-plugin.php
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
		self::load_textdomain();

		// Stage 1: Core URL sitemaps (index + main shards).
		VB_SG_Sitemaps::init();
	}

	/**
	 * Load i18n files.
	 *
	 * @return void
	 */
	private static function load_textdomain(): void {
		load_plugin_textdomain(
			'vb-sitemap-generator',
			false,
			dirname( plugin_basename( VB_SG_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
