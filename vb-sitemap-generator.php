<?php
/**
 * Plugin Name:       VB Sitemap Generator
 * Description:       Lightweight, standards-compliant XML sitemap generator for WordPress (URLs and images, dynamic + cache).
 * Version:           1.0.2
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

define( 'VB_SG_VERSION', '1.0.2' );
define( 'VB_SG_PLUGIN_FILE', __FILE__ );
define( 'VB_SG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once VB_SG_PLUGIN_DIR . 'includes/class-vb-sg-plugin.php';
require_once VB_SG_PLUGIN_DIR . 'includes/class-vb-sg-images.php';
require_once VB_SG_PLUGIN_DIR . 'includes/class-vb-sg-sitemaps.php';

register_activation_hook( __FILE__, array( 'VB_SG_Sitemaps', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VB_SG_Sitemaps', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'VB_SG_Plugin', 'init' ) );
