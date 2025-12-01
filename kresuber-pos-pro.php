<?php
/**
 * Plugin Name:       Kresuber POS Pro
 * Plugin URI:        https://toko.kresuber.co.id/
 * Description:       Sistem Kasir (POS) Modern v1.7.0. Sinkronisasi penuh WooCommerce, HPOS Ready, dan UI Responsif.
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

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Constants
define( 'KRESUBER_POS_PRO_VERSION', '1.7.0' );
define( 'KRESUBER_POS_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'KRESUBER_POS_PRO_URL', plugin_dir_url( __FILE__ ) );

// Autoloader (Diperbaiki untuk Windows & Stabilitas)
spl_autoload_register( function ( $class ) {
    $prefix = 'Kresuber\\POS_Pro\\';
    $base_dir = KRESUBER_POS_PRO_PATH . 'includes/';
    
    // Apakah class menggunakan prefix plugin ini?
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    
    // Ambil nama relatif class
    $relative_class = substr( $class, $len );
    
    // Mapping ke file path
    // Menggunakan DIRECTORY_SEPARATOR agar aman di Windows (D:\...) maupun Linux
    $file = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
    
    // Jika file ada, require. Jika tidak, jangan error dulu (biarkan autoload lain mencoba)
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Main Class Plugin
 */
class Main {
    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'load_i18n' ] );
        add_action( 'plugins_loaded', [ $this, 'init_updater' ] ); // Init Updater
        $this->init_hooks();
        
        // Auto-fix Rewrite Rules jika belum ada
        add_action( 'admin_init', [ $this, 'check_rewrite_rules' ] );
    }

    public function load_i18n() {
        load_plugin_textdomain( 
            'kresuber-pos-pro', 
            false, 
            dirname( plugin_basename( __FILE__ ) ) . '/languages/' 
        );
    }

    public function init_updater() {
        // Inisialisasi GitHub Updater
        // Pastikan Anda membuat Release/Tag di GitHub (misal v1.7.1) agar terdeteksi
        if ( class_exists( 'Kresuber\\POS_Pro\\Core\\Updater' ) ) {
            new Core\Updater( __FILE__, 'febrisuryantoid/kresuber-pos-pro' );
        }
    }

    private function init_hooks() {
        // 1. Admin Area
        // Cek dulu apakah class ada sebelum dipanggil untuk menghindari Fatal Error
        if ( class_exists( 'Kresuber\\POS_Pro\\Admin\\Admin' ) ) {
            $admin = new Admin\Admin();
            add_action( 'admin_menu', [ $admin, 'register_menu' ] );
            add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_styles' ] );
            add_action( 'admin_init', [ $admin, 'register_settings' ] );
        }

        // 2. REST API (Backend Logic)
        if ( class_exists( 'Kresuber\\POS_Pro\\API\\RestController' ) ) {
            $api = new API\RestController();
            add_action( 'rest_api_init', [ $api, 'register_routes' ] );
        }

        // 3. Frontend (Aplikasi POS)
        if ( class_exists( 'Kresuber\\POS_Pro\\Frontend\\UI' ) ) {
            $ui = new Frontend\UI();
            add_action( 'init', [ $ui, 'add_rewrite_rules' ] );
            add_filter( 'query_vars', [ $ui, 'add_query_vars' ] );
            add_action( 'template_redirect', [ $ui, 'load_pos_app' ], 1 );
        }
    }

    public function check_rewrite_rules() {
        $rules = get_option( 'rewrite_rules' );
        if ( ! isset( $rules['^pos/?$'] ) ) {
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        }
    }
}

// Activation Hooks
register_activation_hook( __FILE__, [ 'Kresuber\\POS_Pro\\Core\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Kresuber\\POS_Pro\\Core\\Deactivator', 'deactivate' ] );

// Initialize
function kresuber_pos_pro_init() {
    return Main::instance();
}
kresuber_pos_pro_init();