<?php
/**
 * Plugin Name: Zooraz
 * Description: WooCommerce ecommerce event tracking via Cloudflare Zaraz.
 * Version:     1.0.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License:     GPL-2.0+
 * Text Domain: zooraz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ZOORAZ_VERSION', '1.0.0' );
define( 'ZOORAZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZOORAZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Zooraz</strong> requires WooCommerce to be installed and active.</p></div>';
        } );
        return;
    }

    require_once ZOORAZ_PLUGIN_DIR . 'includes/class-tracker.php';
    new Zooraz_Tracker();
} );
