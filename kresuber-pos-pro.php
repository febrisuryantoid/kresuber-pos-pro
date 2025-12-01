<?php
/**
 * Plugin Name:       Kresuber POS Pro
 * Plugin URI:        https://toko.kresuber.co.id/
 * Description:       Sistem Point of Sale Modern untuk WooCommerce. Akses POS via /pos.
 * Version:           1.0.0-beta
 * Author:            Febri Suryanto
 * Author URI:        https://febrisuryanto.com/
 * License:           GPL-2.0+
 * Text Domain:       kresuber-pos-pro
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package           Kresuber_POS_Pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'KRESUBER_POS_VERSION', '1.0.0-beta' );
define( 'KRESUBER_POS_DIR', plugin_dir_path( __FILE__ ) );
define( 'KRESUBER_POS_URL', plugin_dir_url( __FILE__ ) );

// Autoloader sederhana
require_once KRESUBER_POS_DIR . 'includes/class-kresuber-pos-loader.php';

function run_kresuber_pos() {
    $plugin = new Kresuber_POS_Loader();
    $plugin->run();
}
run_kresuber_pos();