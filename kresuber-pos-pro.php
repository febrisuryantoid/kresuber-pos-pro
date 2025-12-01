<?php
/**
 * Plugin Name:       Kresuber POS Pro
 * Plugin URI:        https://toko.kresuber.co.id/
 * Description:       Enterprise-grade POS System for WooCommerce. Features: Multi-theme, Analytics Dashboard, Offline-first.
 * Version:           1.7.0
 * Author:            Febri Suryanto
 * Author URI:        https://febrisuryanto.com/
 * License:           GPL-2.0+
 * Text Domain:       kresuber-pos-pro
 * Domain Path:       /languages
 *
 * @package           Kresuber_POS_Pro
 */

namespace Kresuber\POS_Pro;

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'KRESUBER_POS_PRO_VERSION', '1.7.0' );
define( 'KRESUBER_POS_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'KRESUBER_POS_PRO_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader
spl_autoload_register( function ( $class ) {
    $prefix = 'Kresuber\\POS_Pro\\';
    $base_dir = KRESUBER_POS_PRO_PATH . 'includes/';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) return;
    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) require $file;
} );

class Main {
    private static $instance = null;
    public static function instance() { if ( is_null( self::$instance ) ) self::$instance = new self(); return self::$instance; }

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_i18n' ] );
        $this->init_hooks();
    }

    public function load_i18n() {
        load_plugin_textdomain( 'kresuber-pos-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    private function init_hooks() {
        $admin = new Admin\Admin();
        add_action( 'admin_menu', [ $admin, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_styles' ] );
        add_action( 'admin_init', [ $admin, 'register_settings' ] );

        $api = new API\RestController();
        add_action( 'rest_api_init', [ $api, 'register_routes' ] );

        $ui = new Frontend\UI();
        add_action( 'init', [ $ui, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $ui, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $ui, 'load_pos_app' ] );
    }
}

register_activation_hook( __FILE__, [ 'Kresuber\\POS_Pro\\Core\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Kresuber\\POS_Pro\\Core\\Deactivator', 'deactivate' ] );

function kresuber_pos_pro_init() { return Main::instance(); }
kresuber_pos_pro_init();
