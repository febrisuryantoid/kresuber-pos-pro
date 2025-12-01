<?php
/**
 * Plugin Name:       Kresuber POS Pro
 * Plugin URI:        https://toko.kresuber.co.id/
 * Description:       Sistem Point of Sale (POS) canggih berbasis React/Vue untuk WooCommerce. Mendukung barcode scanner, thermal printer, dan mode offline (IndexedDB).
 * Version:           2.0.1
 * Author:            Febri Suryanto
 * Author URI:        https://febrisuryanto.com/
 * License:           GPL-2.0+
 * Text Domain:       kresuber-pos-pro
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 *
 * @package           Kresuber_POS_Pro
 */

namespace Kresuber\POS_Pro;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'KRESUBER_POS_PRO_VERSION', '2.0.1' );
define( 'KRESUBER_POS_PRO_FILE', __FILE__ );
define( 'KRESUBER_POS_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'KRESUBER_POS_PRO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader PSR-4 Standard
 * Menghandle namespace: Kresuber\POS_Pro\ -> includes/
 */
spl_autoload_register( function ( $class ) {
	$prefix   = 'Kresuber\\POS_Pro\\';
	$base_dir = KRESUBER_POS_PRO_PATH . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Main Instance Class
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
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		// Init Localization
		$i18n = new Core\i18n();
		add_action( 'plugins_loaded', [ $i18n, 'load_plugin_textdomain' ] );

		// Init API Routes
		$api = new API\RestController();
		add_action( 'rest_api_init', [ $api, 'register_routes' ] );
	}

	private function define_admin_hooks() {
		$admin = new Admin\Admin();
		
        // Menu & Scripts
        add_action( 'admin_menu', [ $admin, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_styles' ] );
        
        // [FIX] PENTING: Hook ini yang memperbaiki error "daftar putih pilihan"
        add_action( 'admin_init', [ $admin, 'register_settings' ] );
	}

	private function define_public_hooks() {
		$ui = new Frontend\UI();
		add_action( 'init', [ $ui, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $ui, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $ui, 'load_pos_app' ] );
	}
}

// Lifecycle Hooks
register_activation_hook( __FILE__, [ 'Kresuber\\POS_Pro\\Core\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Kresuber\\POS_Pro\\Core\\Deactivator', 'deactivate' ] );

// Initialize Plugin
function kresuber_pos_pro_init() {
	return Main::instance();
}
kresuber_pos_pro_init();