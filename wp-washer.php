<?php
/**
 * Plugin Name:       WP Washer
 * Plugin URI:        https://yellowhoster.com
 * Description:       Advanced media metadata management, bulk cleanup, and database optimization for WordPress. Safe, performant, and WooCommerce compatible.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Yellowhoster SMC Limited
 * Author URI:        https://yellowhoster.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-washer
 * Domain Path:       /languages
 */

// Prevent direct file execution.
defined( 'ABSPATH' ) || die( 'No direct script access allowed.' );

// ─── Constants ───────────────────────────────────────────────────────────────
define( 'WP_WASHER_VERSION',  '1.0.0' );
define( 'WP_WASHER_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WP_WASHER_URL',      plugin_dir_url( __FILE__ ) );
define( 'WP_WASHER_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP_WASHER_UPLOADS',  wp_upload_dir()['basedir'] );

// ─── Autoloader ──────────────────────────────────────────────────────────────
spl_autoload_register( function ( $class ) {
    $prefix = 'WP_Washer_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }
    $name     = strtolower( str_replace( array( $prefix, '_' ), array( '', '-' ), $class ) );
    $filepath = WP_WASHER_DIR . 'includes/class-' . $name . '.php';
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    }
} );

// ─── Activation / Deactivation ───────────────────────────────────────────────
register_activation_hook( __FILE__, array( 'WP_Washer_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Washer_Activator', 'deactivate' ) );

// ─── Initialise plugin on plugins_loaded ─────────────────────────────────────
add_action( 'plugins_loaded', 'wp_washer_init' );

/**
 * Bootstrap the plugin.
 */
function wp_washer_init() {
    // Load text domain for translations.
    load_plugin_textdomain( 'wp-washer', false, dirname( WP_WASHER_BASENAME ) . '/languages' );

    // Boot admin interface (only in WP admin).
    if ( is_admin() ) {
        new WP_Washer_Admin();
        new WP_Washer_Ajax_Handler();
    }

    // Auto-delete hooks always active so deletion works even via WP-CLI / REST.
    new WP_Washer_Auto_Delete();
}
