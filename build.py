import os
import sys
import shutil
import json
import zipfile
import time

# --- CONFIG ---
PLUGIN_NAME = "Kresuber POS Pro"
PLUGIN_SLUG = "kresuber-pos-pro"
PLUGIN_VERSION = "1.7.1"
OUTPUT_DIR = "kresuber-pos-pro"

print(f"[INIT] Building {PLUGIN_NAME} v{PLUGIN_VERSION} (Corporate Edition)...")

# ==============================================================================
# 1. PHP MAIN & CORE
# ==============================================================================

TPL_MAIN = r"""<?php
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
"""

TPL_ACTIVATOR = r"""<?php namespace Kresuber\POS_Pro\Core; if(!defined('ABSPATH')) exit; class Activator { public static function activate() { add_rewrite_rule('^pos/?$', 'index.php?kresuber_pos=1', 'top'); flush_rewrite_rules(); $r=get_role('administrator'); if($r){ $r->add_cap('kresuber_pos_manage'); $r->add_cap('kresuber_pos_cashier'); } } }"""
TPL_DEACTIVATOR = r"""<?php namespace Kresuber\POS_Pro\Core; if(!defined('ABSPATH')) exit; class Deactivator { public static function deactivate() { flush_rewrite_rules(); } }"""
TPL_I18N = r"""<?php namespace Kresuber\POS_Pro\Core; class i18n { public function load_plugin_textdomain() {} }"""

# ==============================================================================
# 2. ADMIN DASHBOARD (THEME SELECTOR & SETTINGS)
# ==============================================================================

TPL_ADMIN_PHP = r"""<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.7.0';

    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_theme' ); // retail, grosir, etc
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image' );
        register_setting( 'kresuber_pos_settings', 'kresuber_printer_width' );
        register_setting( 'kresuber_pos_settings', 'kresuber_cashiers' );
    }

    public function register_menu() {
        add_menu_page( 'Kresuber POS', 'Kresuber POS', 'manage_woocommerce', 'kresuber-pos', [ $this, 'render_dashboard' ], 'dashicons-store', 56 );
    }

    public function enqueue_styles() {
        wp_enqueue_media();
        wp_enqueue_style( 'kresuber-admin', KRESUBER_POS_PRO_URL . 'assets/css/admin.css', [], $this->version );
    }

    public function render_dashboard() {
        $theme = get_option( 'kresuber_pos_theme', 'retail' );
        $logo = get_option( 'kresuber_pos_logo' );
        $qris = get_option( 'kresuber_qris_image' );
        $width = get_option( 'kresuber_printer_width', '58mm' );
        $cashiers = json_decode(get_option( 'kresuber_cashiers', '[]' )) ?: [];
        
        // Palette Config
        $palettes = [
            'retail'  => ['label'=>'Toko Retail', 'color'=>'#00A78E', 'bg'=>'#F0FBF8'],
            'grosir'  => ['label'=>'Toko Grosir', 'color'=>'#0B5FFF', 'bg'=>'#F1F6FF'],
            'sembako' => ['label'=>'Warung Sembako','color'=>'#F59E0B', 'bg'=>'#FFF7ED'],
            'kelontong'=>['label'=>'Warung Kelontong','color'=>'#7C4DFF', 'bg'=>'#F8F7FF'],
            'sayur'   => ['label'=>'Warung Sayur', 'color'=>'#10B981', 'bg'=>'#F0FFF4'],
            'buah'    => ['label'=>'Warung Buah',  'color'=>'#FF6B6B', 'bg'=>'#FFF5F5'],
        ];

        // Dummy stats for dashboard visualization
        $stats = [
            'sales' => 'Rp ' . number_format(rand(1000000, 5000000), 0, ',', '.'),
            'orders' => rand(10, 50),
            'products' => wp_count_posts('product')->publish
        ];
        ?>
        <div class="k-wrap">
            <header class="k-header">
                <div class="k-brand">
                    <div class="k-icon-box"><span class="dashicons dashicons-store"></span></div>
                    <div>
                        <h1>Kresuber POS Pro</h1>
                        <p>Sistem Kasir UMKM Terintegrasi</p>
                    </div>
                </div>
                <div class="k-actions">
                    <span class="k-pill">v1.7.0 Stable</span>
                    <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="k-btn k-btn-primary">Buka Aplikasi POS <span class="dashicons dashicons-arrow-right-alt"></span></a>
                </div>
            </header>

            <!-- Dashboard Stats -->
            <div class="k-stats-grid">
                <div class="k-stat-card">
                    <div class="icon blue"><span class="dashicons dashicons-chart-area"></span></div>
                    <div><h3><?php echo $stats['sales']; ?></h3><p>Penjualan Hari Ini</p></div>
                </div>
                <div class="k-stat-card">
                    <div class="icon green"><span class="dashicons dashicons-cart"></span></div>
                    <div><h3><?php echo $stats['orders']; ?></h3><p>Total Pesanan</p></div>
                </div>
                <div class="k-stat-card">
                    <div class="icon purple"><span class="dashicons dashicons-products"></span></div>
                    <div><h3><?php echo $stats['products']; ?></h3><p>Total Produk</p></div>
                </div>
            </div>

            <form method="post" action="options.php" class="k-form">
                <?php settings_fields('kresuber_pos_settings'); do_settings_sections('kresuber_pos_settings'); ?>
                
                <div class="k-grid-layout">
                    <!-- LEFT COL -->
                    <div class="k-main-col">
                        <!-- THEME SELECTOR -->
                        <div class="k-card">
                            <div class="k-card-head">
                                <h3>Tema Toko</h3>
                                <p>Pilih skema warna yang sesuai dengan jenis usaha Anda.</p>
                            </div>
                            <div class="k-card-body">
                                <div class="k-theme-grid">
                                    <?php foreach($palettes as $key => $p): ?>
                                    <label class="k-theme-item <?php echo $theme === $key ? 'active' : ''; ?>">
                                        <input type="radio" name="kresuber_pos_theme" value="<?php echo $key; ?>" <?php checked($theme, $key); ?> class="hidden">
                                        <div class="k-swatch" style="background: <?php echo $p['bg']; ?>; border-color: <?php echo $p['color']; ?>;">
                                            <div class="k-swatch-accent" style="background: <?php echo $p['color']; ?>;"></div>
                                            <span style="color: <?php echo $p['color']; ?>; font-weight:bold"><?php echo $p['label']; ?></span>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- HARDWARE -->
                        <div class="k-card">
                            <div class="k-card-head">
                                <h3>Hardware & Pembayaran</h3>
                            </div>
                            <div class="k-card-body">
                                <div class="k-row">
                                    <div class="k-col">
                                        <label class="k-label">Logo Struk & POS</label>
                                        <div class="k-media-wrap">
                                            <div class="k-img-preview" id="logo-prev">
                                                <?php echo $logo ? '<img src="'.esc_url($logo).'">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                            </div>
                                            <div class="k-media-btns">
                                                <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                                <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_pos_logo" data-prev="#logo-prev">Upload</button>
                                                <?php if($logo): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_pos_logo">Hapus</button><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="k-col">
                                        <label class="k-label">QRIS (Static)</label>
                                        <div class="k-media-wrap">
                                            <div class="k-img-preview" id="qris-prev">
                                                <?php echo $qris ? '<img src="'.esc_url($qris).'">' : '<span class="dashicons dashicons-qr"></span>'; ?>
                                            </div>
                                            <div class="k-media-btns">
                                                <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                                <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_qris_image" data-prev="#qris-prev">Upload</button>
                                                <?php if($qris): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_qris_image">Hapus</button><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="k-hr">
                                <label class="k-label">Ukuran Kertas Printer</label>
                                <select name="kresuber_printer_width" class="k-select">
                                    <option value="58mm" <?php selected($width, '58mm'); ?>>58mm (Mobile/Bluetooth)</option>
                                    <option value="80mm" <?php selected($width, '80mm'); ?>>80mm (Desktop)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COL -->
                    <div class="k-side-col">
                        <div class="k-card">
                            <div class="k-card-head"><h3>Kasir</h3></div>
                            <div class="k-card-body">
                                <label class="k-label">Nama Kasir (Pisahkan koma)</label>
                                <?php $c_str = implode(', ', $cashiers); ?>
                                <textarea id="cashier_input" class="k-textarea" rows="5" placeholder="Budi, Siti, Admin"><?php echo esc_textarea($c_str); ?></textarea>
                                <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr(get_option( 'kresuber_cashiers', '[]' )); ?>">
                            </div>
                            <div class="k-card-foot">
                                <?php submit_button('Simpan Semua', 'primary w-full', 'submit', false); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($){
            // Media Uploader
            $('.upload-btn').click(function(e){ 
                e.preventDefault(); var t=$(this).data('target'); var p=$($(this).data('prev'));
                var f=wp.media({title:'Pilih Gambar',multiple:false}); 
                f.on('select',function(){ var u=f.state().get('selection').first().toJSON().url; $(t).val(u); p.html('<img src=\"'+u+'\">'); }); 
                f.open(); 
            });
            $('.remove-btn').click(function(){ $($(this).data('target')).val(''); location.reload(); });
            
            // Theme Switcher Visual
            $('.k-theme-item').click(function(){ $('.k-theme-item').removeClass('active'); $(this).addClass('active'); });

            // Cashier Array
            $('form').submit(function(){
                var arr = $('#cashier_input').val().split(',').map(s=>s.trim()).filter(s=>s);
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
        });
        </script>
        <?php
    }
}
"""

TPL_ADMIN_CSS = r"""
/* SaaS Admin Style */
.kresuber-wrap { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; max-width: 1100px; margin: 30px auto; color: #1e293b; }
.kresuber-wrap * { box-sizing: border-box; }

/* Header */
.k-header { background: #fff; padding: 20px 30px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.k-brand { display: flex; align-items: center; gap: 15px; }
.k-icon-box { width: 48px; height: 48px; background: #eff6ff; color: #3b82f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.k-icon-box .dashicons { font-size: 24px; width: 24px; height: 24px; }
.k-brand h1 { margin: 0; font-size: 22px; font-weight: 700; color: #0f172a; }
.k-brand p { margin: 2px 0 0; color: #64748b; font-size: 14px; }
.k-pill { background: #dcfce7; color: #166534; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 20px; border: 1px solid #bbf7d0; margin-right: 10px; }

/* Stats Grid */
.k-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
.k-stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
.k-stat-card .icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.k-stat-card .icon.blue { background: #eff6ff; color: #3b82f6; }
.k-stat-card .icon.green { background: #f0fdf4; color: #16a34a; }
.k-stat-card .icon.purple { background: #faf5ff; color: #9333ea; }
.k-stat-card h3 { margin: 0; font-size: 24px; font-weight: 700; color: #1e293b; }
.k-stat-card p { margin: 0; font-size: 13px; color: #64748b; }

/* Layout */
.k-grid-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
.k-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
.k-card-head { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
.k-card-head h3 { margin: 0; font-size: 16px; font-weight: 600; color: #334155; }
.k-card-head p { margin: 4px 0 0; font-size: 13px; color: #94a3b8; }
.k-card-body { padding: 24px; }
.k-card-foot { padding: 16px 24px; border-top: 1px solid #f1f5f9; background: #f8fafc; }

/* Theme Grid */
.k-theme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
.k-theme-item { cursor: pointer; }
.k-theme-item input { display: none; }
.k-swatch { height: 80px; border: 2px solid #e2e8f0; border-radius: 8px; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: all 0.2s; }
.k-theme-item.active .k-swatch { border-color: currentColor; box-shadow: 0 0 0 2px currentColor; transform: translateY(-2px); }
.k-swatch-accent { width: 30px; height: 30px; border-radius: 50%; margin-bottom: 8px; }

/* Inputs */
.k-label { display: block; font-weight: 500; margin-bottom: 8px; font-size: 14px; color: #475569; }
.k-input, .k-select, .k-textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; transition: border 0.2s; }
.k-input:focus, .k-textarea:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.k-hint { font-size: 12px; color: #9ca3af; margin-top: 6px; }

/* Media */
.k-row { display: flex; gap: 20px; }
.k-col { flex: 1; }
.k-media-wrap { display: flex; gap: 15px; align-items: flex-start; }
.k-img-preview { width: 70px; height: 70px; background: #f1f5f9; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.k-img-preview img { width: 100%; height: 100%; object-fit: contain; }
.k-media-btns { display: flex; flex-direction: column; gap: 5px; }

/* Buttons */
.k-btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; transition: 0.2s; cursor: pointer; border: 1px solid transparent; font-size: 14px; }
.k-btn-primary { background: #2563eb; color: #fff; }
.k-btn-primary:hover { background: #1d4ed8; color: #fff; }
.k-btn-outline { background: #fff; border-color: #d1d5db; color: #334155; }
.k-btn-outline:hover { border-color: #9ca3af; background: #f8fafc; }
.k-btn-sm { padding: 6px 12px; font-size: 12px; }
.k-link-danger { color: #ef4444; font-size: 12px; text-decoration: underline; background: none; border: none; cursor: pointer; padding: 0; text-align: left; }

.w-full { width: 100%; }
.k-hr { border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0; }
@media (max-width: 768px) { .k-layout { grid-template-columns: 1fr; } .k-header { flex-direction: column; align-items: flex-start; gap: 15px; } }
"""

# ==========================================
# 3. REST API (CLEANUP & ANALYTICS)
# ==========================================

TPL_API = r"""<?php
namespace Kresuber\POS_Pro\API;
use WP_Error, WP_REST_Controller, WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) exit;

class RestController extends WP_REST_Controller {
    protected $namespace = 'kresuber-pos/v1';

    public function register_routes() {
        // Products
        register_rest_route( $this->namespace, '/products', [ 'methods' => 'GET', 'callback' => [ $this, 'get_products' ], 'permission_callback' => [ $this, 'perm' ] ] );
        // Orders
        register_rest_route( $this->namespace, '/orders', [ 'methods' => 'GET', 'callback' => [ $this, 'get_orders' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/order', [ 'methods' => 'POST', 'callback' => [ $this, 'create_order' ], 'permission_callback' => [ $this, 'perm' ] ] );
        // Analytics
        register_rest_route( $this->namespace, '/analytics', [ 'methods' => 'GET', 'callback' => [ $this, 'get_analytics' ], 'permission_callback' => [ $this, 'perm' ] ] );
    }

    public function perm() { return current_user_can('manage_woocommerce'); }

    public function get_products($r) {
        $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
        $data = [];
        foreach($products as $p) {
            $img = $p->get_image_id() ? wp_get_attachment_image_url($p->get_image_id(), 'medium') : wc_placeholder_img_src();
            $cats = $p->get_category_ids();
            $c_slug = 'lainnya'; $c_name = 'Lainnya';
            if(!empty($cats) && ($t=get_term($cats[0], 'product_cat'))) { $c_slug=$t->slug; $c_name=$t->name; }
            $data[] = [
                'id'=>$p->get_id(), 'name'=>$p->get_name(), 'price'=>(float)$p->get_price(),
                'image'=>$img, 'stock'=>$p->get_stock_quantity()??999, 'stock_status'=>$p->get_stock_status(),
                'sku'=>(string)$p->get_sku(), 'barcode'=>(string)$p->get_meta('_barcode'),
                'category_slug'=>$c_slug, 'category_name'=>$c_name
            ];
        }
        return rest_ensure_response($data);
    }

    public function get_orders($r) {
        // FIX: Return clean array for items, no HTML string
        $orders = wc_get_orders(['limit'=>30, 'orderby'=>'date', 'order'=>'DESC']);
        $data = [];
        foreach($orders as $o) {
            $items = [];
            foreach($o->get_items() as $i) {
                $items[] = [
                    'name' => $i->get_name(),
                    'qty'  => $i->get_quantity()
                ];
            }
            
            $data[] = [
                'id' => $o->get_id(),
                'number' => $o->get_order_number(),
                'status' => $o->get_status(),
                'total_formatted' => strip_tags($o->get_formatted_order_total()),
                'date' => $o->get_date_created()->date('d/m/y H:i'),
                'customer' => $o->get_formatted_billing_full_name() ?: 'Walk-in',
                'items' => $items 
            ];
        }
        return rest_ensure_response($data);
    }
    
    public function get_analytics($r) {
        // Simple daily stats
        $today = date('Y-m-d');
        $orders = wc_get_orders(['date_created'=>"$today...$today", 'limit'=>-1]);
        $total = 0; $count = 0;
        foreach($orders as $o) { $total += $o->get_total(); $count++; }
        return rest_ensure_response(['sales'=>$total, 'count'=>$count]);
    }

    public function create_order($r) {
        $p = $r->get_json_params();
        try {
            $order = wc_create_order(['customer_id'=>0]);
            foreach($p['items'] as $i) { $prod=wc_get_product(intval($i['id'])); if($prod) $order->add_product($prod, intval($i['qty'])); }
            $order->set_billing_first_name('Walk-in');
            $order->set_payment_method($p['payment_method']??'cash');
            $order->calculate_totals(); 
            $order->payment_complete();
            return rest_ensure_response(['success'=>true, 'order_number'=>$order->get_order_number(), 'total'=>$order->get_total()]);
        } catch( \Exception $e ) { return new WP_Error('err', $e->getMessage()); }
    }
}
"""

# Frontend UI Loader (Config Injector)
TPL_UI = r"""<?php
namespace Kresuber\POS_Pro\Frontend;
if ( ! defined( 'ABSPATH' ) ) exit;

class UI {
    public function add_rewrite_rules() { add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' ); }
    public function add_query_vars( $vars ) { $vars[] = 'kresuber_pos'; return $vars; }
    public function load_pos_app() {
        if ( get_query_var( 'kresuber_pos' ) == 1 ) {
            if ( ! is_user_logged_in() || ! current_user_can('manage_woocommerce') ) { auth_redirect(); exit; }
            global $kresuber_config;
            $kresuber_config = [
                'logo' => get_option('kresuber_pos_logo', ''),
                'qris' => get_option('kresuber_qris_image', ''),
                'printer_width' => get_option('kresuber_printer_width', '58mm'),
                'cashiers' => json_decode(get_option('kresuber_cashiers', '[]')),
                'site_name' => get_bloginfo('name')
            ];
            include KRESUBER_POS_PRO_PATH . 'templates/app.php';
            exit;
        }
    }
}
"""

# ==========================================
# 4. FRONTEND VUE APP (FIXED UI/UX)
# ==========================================

TPL_APP_HTML = r"""<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <title>Kresuber POS v1.7.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/dexie@3.2.4/dist/dexie.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <link rel="stylesheet" href="<?php echo KRESUBER_POS_PRO_URL; ?>assets/css/pos-style.css">
    <style>
        :root { 
            --primary: <?php 
                $themes = [
                    'retail' => '#00A78E', 'grosir' => '#0B5FFF', 'sembako' => '#F59E0B',
                    'kelontong'=> '#7C4DFF', 'sayur' => '#10B981', 'buah' => '#FF6B6B'
                ];
                echo $themes[get_option('kresuber_pos_theme','retail')] ?? '#00A78E'; 
            ?>; 
            --print-width: <?php global $kresuber_config; echo $kresuber_config['printer_width']; ?>; 
        }
        .bg-theme { background-color: var(--primary); }
        .text-theme { color: var(--primary); }
        .border-theme { border-color: var(--primary); }
        .ring-theme { --tw-ring-color: var(--primary); }
        #app-loading { position: fixed; inset: 0; background: #fff; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s; }
        [v-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 h-screen overflow-hidden font-sans text-slate-800">
    
    <div id="app-loading">
        <div class="mb-4 text-blue-600 animate-bounce"><i class="ri-store-3-fill text-6xl"></i></div>
        <h2 class="text-xl font-bold text-slate-700">Memuat Kasir...</h2>
    </div>

    <div id="app" v-cloak class="flex h-full w-full flex-col md:flex-row">
        <!-- Left: Main -->
        <div class="flex-1 flex flex-col h-full bg-white relative border-r border-gray-200">
            <!-- Header -->
            <div class="hidden md:flex h-16 px-6 border-b justify-between items-center z-30 shrink-0 bg-white">
                <div class="flex items-center gap-6 w-full max-w-3xl">
                    <div class="flex items-center gap-2">
                        <img v-if="config.logo" :src="config.logo" class="h-10 w-auto object-contain">
                        <span v-else class="font-bold text-2xl text-blue-600">{{config.site_name}}</span>
                    </div>
                    <div class="relative w-full max-w-md group">
                        <i class="ri-search-2-line absolute left-3 top-2.5 text-gray-400"></i>
                        <input v-model="search" type="text" placeholder="Cari / Scan Barcode (F3)" class="w-full pl-10 pr-10 py-2 bg-gray-100 rounded-lg outline-none focus:ring-2 focus:ring-blue-500 transition border-transparent border focus:bg-white">
                        <button v-if="search" @click="search=''" class="absolute right-2 top-2 text-gray-400 hover:text-red-500"><i class="ri-close-circle-fill"></i></button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="sync" :class="{'animate-spin text-blue-600':syncing}" class="p-2 hover:bg-gray-100 rounded-full" title="Sync Data"><i class="ri-refresh-line text-lg"></i></button>
                    <button @click="viewMode='orders';fetchOrders()" class="flex items-center gap-2 px-3 py-2 bg-slate-50 rounded-lg font-bold text-sm hover:bg-slate-100 transition text-slate-700 border border-slate-200"><i class="ri-history-line"></i> Riwayat</button>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="text-gray-400 hover:text-red-500 p-2" title="Keluar"><i class="ri-logout-box-r-line text-xl"></i></a>
                </div>
            </div>

            <!-- Mobile Nav -->
            <div class="md:hidden h-14 bg-white border-b flex items-center justify-between px-4 z-50 shrink-0">
                <span class="font-bold text-lg text-blue-600">Kresuber</span>
                <button @click="showCart=!showCart" class="relative p-2"><i class="ri-shopping-basket-fill text-2xl text-slate-700"></i><span v-if="cart.length" class="absolute top-0 right-0 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center">{{cartTotalQty}}</span></button>
            </div>

            <!-- Category Chips (FIXED UI) -->
            <div class="px-4 py-3 border-b bg-white overflow-x-auto whitespace-nowrap no-scrollbar shadow-sm z-20 shrink-0">
                <button @click="setCategory('all')" 
                    :class="curCat==='all' ? 'bg-theme text-white shadow-md border-theme' : 'bg-white text-slate-600 hover:bg-gray-50 border-gray-200'" 
                    class="px-5 py-1.5 rounded-full text-xs font-bold mr-2 transition-all border">
                    Semua
                </button>
                <button v-for="c in categories" :key="c.slug" @click="setCategory(c.slug)" 
                    :class="curCat===c.slug ? 'bg-theme text-white shadow-md border-theme' : 'bg-white text-slate-600 hover:bg-gray-50 border-gray-200'" 
                    class="px-5 py-1.5 rounded-full text-xs font-bold mr-2 transition-all border">
                    {{c.name}}
                </button>
            </div>

            <!-- POS Grid -->
            <div v-if="viewMode==='pos'" class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-50 custom-scrollbar">
                <div v-if="loading" class="text-center pt-20 text-gray-400"><i class="ri-loader-4-line animate-spin text-2xl mb-2"></i><p>Memuat...</p></div>
                <div v-else-if="!products.length" class="text-center pt-20 text-gray-400"><i class="ri-inbox-line text-4xl"></i><p>Produk tidak ditemukan</p></div>
                <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 pb-20">
                    <div v-for="p in products" :key="p.id" @click="addToCart(p)" class="bg-white rounded-xl shadow-sm hover:shadow-md cursor-pointer overflow-hidden border border-transparent hover:border-theme flex flex-col h-64 transition group">
                        <div class="h-36 bg-gray-100 relative"><img :src="p.image" loading="lazy" class="w-full h-full object-cover"></div>
                        <div class="p-3 flex flex-col flex-1 justify-between">
                            <h3 class="font-bold text-sm text-slate-800 line-clamp-2 leading-snug">{{p.name}}</h3>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-blue-700 font-bold">{{fmt(p.price)}}</span>
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center group-hover:bg-theme group-hover:text-white transition"><i class="ri-add-line"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders History (FIXED LIST RENDERING) -->
            <div v-if="viewMode==='orders'" class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="font-bold text-2xl text-slate-800">Riwayat Pesanan</h2>
                    <!-- Fixed Back Button Logic -->
                    <button @click="viewMode='pos'" class="px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 font-bold text-sm flex items-center gap-2 transition">
                        <i class="ri-arrow-left-line"></i> Kembali ke Kasir
                    </button>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div v-if="ordersLoading" class="p-10 text-center text-gray-400">Memuat riwayat...</div>
                    <table v-else class="w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 border-b">
                            <tr>
                                <th class="p-4 font-bold">ID</th>
                                <th class="p-4 font-bold">Tanggal</th>
                                <th class="p-4 font-bold">Items</th>
                                <th class="p-4 font-bold text-right">Total</th>
                                <th class="p-4 font-bold text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="o in recentOrders" :key="o.id" class="hover:bg-blue-50 transition">
                                <td class="p-4 font-bold text-blue-600">#{{o.number}}</td>
                                <td class="p-4 text-gray-500">{{o.date}}</td>
                                <td class="p-4">
                                    <div class="flex flex-col gap-1">
                                        <div v-for="(item, idx) in o.items" :key="idx" class="text-gray-700 flex items-center gap-2">
                                            <span class="bg-gray-100 px-2 py-0.5 rounded text-xs font-bold text-slate-600">{{item.qty}}x</span>
                                            <span class="truncate max-w-[200px]">{{item.name}}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 font-bold text-right">{{o.total_formatted}}</td>
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold uppercase tracking-wide">{{o.status}}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Cart -->
        <div :class="showCart?'translate-y-0':'translate-y-full md:translate-y-0'" class="fixed md:static inset-0 md:inset-auto z-40 w-full md:w-[400px] bg-white border-l shadow-2xl md:shadow-none flex flex-col transition-transform duration-300">
            <div class="px-5 py-4 border-b flex justify-between items-center bg-white shrink-0">
                <h2 class="font-bold text-lg flex items-center gap-2"><i class="ri-shopping-cart-2-fill text-theme"></i> Keranjang</h2>
                <div class="hidden md:flex gap-2"><button @click="clearCart" class="text-red-500 hover:bg-red-50 p-2 rounded transition" title="Hapus"><i class="ri-delete-bin-line text-xl"></i></button></div>
                <button @click="showCart=false" class="md:hidden p-2 text-gray-400"><i class="ri-close-line text-xl"></i></button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-white custom-scrollbar">
                <div v-if="!cart.length" class="text-center text-slate-300 mt-20 flex flex-col items-center">
                    <i class="ri-shopping-basket-line text-6xl mb-2 opacity-30"></i><p class="font-bold">Keranjang Kosong</p>
                </div>
                <div v-for="i in cart" :key="i.id" class="flex gap-3 p-3 border border-gray-100 rounded-xl shadow-sm bg-white group">
                    <img :src="i.image" class="w-14 h-14 rounded-lg bg-gray-100 object-cover">
                    <div class="flex-1 min-w-0 flex flex-col justify-between">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-sm text-slate-700 truncate leading-tight">{{i.name}}</h4>
                            <button @click="rem(i)" class="text-gray-300 hover:text-red-500"><i class="ri-close-circle-fill text-lg"></i></button>
                        </div>
                        <div class="flex justify-between items-end">
                            <span class="text-xs text-gray-500 font-semibold">@ {{fmt(i.price)}}</span>
                            <div class="flex items-center bg-gray-50 rounded-lg border">
                                <button @click="qty(i,-1)" class="w-8 h-7 font-bold hover:text-red-500">-</button>
                                <span class="text-sm font-bold w-6 text-center">{{i.qty}}</span>
                                <button @click="qty(i,1)" class="w-8 h-7 font-bold hover:text-theme">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t bg-slate-50 shrink-0 shadow-lg z-20">
                <!-- Tax Removed -->
                <div class="flex justify-between items-center mb-6">
                    <span class="font-bold text-lg text-slate-800">Total</span>
                    <span class="font-extrabold text-3xl text-theme tracking-tight">{{fmt(grandTotal)}}</span>
                </div>
                <button @click="modal=true" :disabled="!cart.length" class="w-full py-3.5 bg-theme text-white rounded-xl font-bold shadow-lg hover:opacity-90 transition disabled:opacity-50 flex justify-center items-center gap-2">
                    <i class="ri-secure-payment-line"></i> Bayar Sekarang
                </button>
            </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="modal" class="fixed inset-0 z-[60] flex items-end md:items-center justify-center p-0 md:p-4 bg-slate-900/70 backdrop-blur-sm transition-all">
            <div class="bg-white w-full md:max-w-md rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-5 border-b flex justify-between items-center bg-white"><h3 class="font-bold text-lg">Pembayaran</h3><button @click="modal=false"><i class="ri-close-line text-2xl text-gray-400 hover:text-red-500"></i></button></div>
                <div class="p-6 overflow-y-auto bg-gray-50">
                    <div class="text-center mb-8"><div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Tagihan</div><div class="text-4xl font-extrabold text-slate-800 mt-1">{{fmt(total)}}</div></div>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <button @click="method='cash'" :class="method==='cash'?'ring-2 ring-theme bg-white shadow-md':'bg-gray-100 text-gray-500'" class="p-4 rounded-xl font-bold text-center transition border border-transparent">Tunai</button>
                        <button @click="method='qris'" :class="method==='qris'?'ring-2 ring-theme bg-white shadow-md':'bg-gray-100 text-gray-500'" class="p-4 rounded-xl font-bold text-center transition border border-transparent">QRIS</button>
                    </div>
                    <div v-if="method==='cash'" class="mb-6">
                        <div class="relative mb-3"><span class="absolute left-4 top-3.5 font-bold text-lg text-gray-400">Rp</span><input type="number" v-model="paid" ref="cashInput" class="w-full pl-12 p-3 border rounded-xl text-xl font-bold focus:ring-2 ring-theme outline-none" placeholder="0"></div>
                        <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar mb-4"><button v-for="a in quickCash" :key="a" @click="paid=a" class="px-3 py-1.5 bg-white border rounded-lg text-xs font-bold whitespace-nowrap shadow-sm transition">{{fmt(a)}}</button></div>
                        <div class="flex justify-between font-bold p-4 bg-white rounded-xl border border-gray-200" :class="change>=0?'text-green-600':'text-red-500'"><span>Kembali</span><span>{{fmt(Math.max(0,change))}}</span></div>
                    </div>
                    <div v-if="method==='qris'" class="text-center p-6 bg-white rounded-xl border border-gray-200 mb-6">
                         <img v-if="config.qris" :src="config.qris" class="mx-auto max-w-[200px] rounded-lg shadow-sm border">
                         <div v-else class="text-sm text-gray-400 py-4">QRIS belum diupload di Admin.</div>
                    </div>
                </div>
                <div class="p-5 border-t bg-white">
                    <button @click="checkout" :disabled="processing||(method==='cash'&&change<0)" class="w-full py-4 bg-theme text-white rounded-xl font-bold hover:bg-blue-700 disabled:opacity-50 shadow-lg transition flex justify-center gap-2"><i v-if="processing" class="ri-loader-4-line animate-spin text-xl"></i> {{method==='qris'?'Konfirmasi Selesai':'Bayar & Cetak'}}</button>
                </div>
            </div>
        </div>

        <!-- Receipt -->
        <div id="receipt-print" class="hidden">
            <style>@page{margin:0}body.receipt{margin:0;padding:10px;font-family:'Courier New',monospace;font-size:12px;width:var(--print-width);line-height:1.2}.r-center{text-align:center}.r-right{text-align:right}.r-line{border-top:1px dashed #000;margin:5px 0}.r-table{width:100%;border-collapse:collapse}</style>
            <div class="receipt-body">
                <div class="r-center"><h3 style="margin:0;font-size:16px;font-weight:bold">{{config.site_name}}</h3><p style="margin:2px 0 10px;font-size:10px">POS Receipt</p></div><div class="r-line"></div>
                <div>No: #{{lastReceipt.orderNumber}}<br>Tgl: {{lastReceipt.date}}<br>Kasir: {{activeCashier}}</div>
                <div class="r-line"></div><table class="r-table"><tr v-for="i in lastReceipt.items"><td>{{i.name}}<br>{{i.qty}} x {{fmt(i.price)}}</td><td class="r-right" style="vertical-align:bottom">{{fmt(i.qty*i.price)}}</td></tr></table><div class="r-line"></div>
                <div style="display:flex;justify-content:space-between"><span>TOTAL</span> <strong>{{fmt(lastReceipt.grandTotal)}}</strong></div>
                <div v-if="lastReceipt.paymentMethod==='cash'"><div style="display:flex;justify-content:space-between"><span>Tunai</span><span>{{fmt(lastReceipt.cashReceived)}}</span></div><div style="display:flex;justify-content:space-between"><span>Kembali</span><span>{{fmt(lastReceipt.cashChange)}}</span></div></div>
                <div v-else style="text-align:center;margin-top:5px;font-style:italic;">[Lunas via {{lastReceipt.paymentMethod.toUpperCase()}}]</div>
                <div style="border-top:1px dashed #000;margin:10px 0;"></div>
                <div style="text-align:center;font-size:10px;">Terima Kasih!</div>
            </div>
        </div>
    </div>

    <script>
        globalThis.params = { api: '<?php echo esc_url_raw( rest_url( "kresuber-pos/v1" ) ); ?>', nonce: '<?php echo wp_create_nonce( "wp_rest" ); ?>', curr: '<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>', conf: <?php global $kresuber_config; echo json_encode($kresuber_config); ?> };
    </script>
    <script src="<?php echo KRESUBER_POS_PRO_URL; ?>assets/js/pos-app.js"></script>
    <script>setTimeout(()=>{const l=document.getElementById('app-loading');if(l&&document.getElementById('app').innerHTML.trim().length>100)l.style.display='none'},2000);</script>
</body>
</html>
"""

TPL_APP_JS = r"""
const { createApp, ref, computed, onMounted, nextTick, watch } = Vue;
const db = new Dexie("KresuberDB_V4");
db.version(1).stores({ prod: "id, sku, barcode, cat, search" });

createApp({
    setup() {
        const config=ref(params.conf||{}), products=ref([]), categories=ref([]), cart=ref([]), recentOrders=ref([]), analytics=ref({sales:0,count:0});
        const curCat=ref('all'), search=ref(''), loading=ref(true), syncing=ref(false), ordersLoading=ref(false);
        const activeCashier=ref(config.value.cashiers?.[0] || 'Default');
        const viewMode=ref('pos'), showMobileCart=ref(false), showCart=ref(false), modal=ref(false);
        const method=ref('cash'), paid=ref(''), processing=ref(false), cashInput=ref(null), lastReceipt=ref({});

        const total = computed(() => cart.value.reduce((s,i)=>s+(i.price*i.qty),0));
        const grandTotal = computed(() => total.value);
        const change = computed(() => (parseInt(paid.value)||0)-grandTotal.value);
        const quickCash = computed(() => [10000, 20000, 50000, 100000].filter(a => a >= grandTotal.value).slice(0, 3));
        const cartTotalQty = computed(() => cart.value.reduce((a, i) => a + i.qty, 0));
        const fmt = (v) => params.curr + ' ' + new Intl.NumberFormat('id-ID').format(v);

        const sync = async () => {
            syncing.value=true; loading.value=true;
            try {
                const r = await axios.get(`${params.api}/products`, {headers:{'X-WP-Nonce':params.nonce}});
                const items = r.data.map(p => ({...p, search:`${p.name} ${p.sku} ${p.barcode}`.toLowerCase(), cat:p.category_slug}));
                const cats = {}; items.forEach(i => cats[i.cat]={slug:i.cat, name:i.category_name});
                categories.value = Object.values(cats);
                await db.prod.clear(); await db.prod.bulkAdd(items);
                find();
            } catch(e){ alert("Sync Gagal"); } finally { syncing.value=false; loading.value=false; }
        };

        const find = async () => {
            let c = db.prod.toCollection();
            if(curCat.value!=='all') c = db.prod.where('cat').equals(curCat.value);
            const q = search.value.toLowerCase().trim();
            if(q) {
                const ex = await db.prod.where('sku').equals(q).or('barcode').equals(q).first();
                if(ex) { add(ex); search.value=''; return; }
                const all = await c.toArray();
                products.value = all.filter(p => p.search.includes(q)).slice(0, 60);
            } else { 
                products.value = await c.limit(60).toArray(); 
                if(!categories.value.length && products.value.length) {
                     const all = await db.prod.toArray(); const k = {}; all.forEach(i=>k[i.cat]={slug:i.cat,name:i.category_name}); categories.value=Object.values(k);
                }
            }
        };

        const fetchOrders = async () => {
            ordersLoading.value = true;
            try { const r = await axios.get(`${params.api}/orders`, {headers:{'X-WP-Nonce':params.nonce}}); recentOrders.value = r.data; }
            catch(e){} finally { ordersLoading.value = false; }
        };
        
        const fetchStats = async () => {
            try { const r = await axios.get(`${params.api}/analytics`, {headers:{'X-WP-Nonce':params.nonce}}); analytics.value = r.data; }
            catch(e){}
        };

        const add = (p) => { if(p.stock_status==='outofstock') return alert('Habis!'); const i=cart.value.find(x=>x.id===p.id); i?i.qty++:cart.value.push({...p, qty:1}); };
        const rem = (i) => cart.value = cart.value.filter(x=>x.id!==i.id);
        const qty = (i,d) => { i.qty+=d; if(i.qty<=0) rem(i); };
        const clearCart = () => confirm('Hapus keranjang?') ? cart.value=[] : null;
        const toggleHold = () => {}; // Future

        const checkout = async () => {
            processing.value=true;
            try {
                const pl = { items:cart.value, payment_method:method.value, amount_tendered:paid.value, change:change.value };
                const r = await axios.post(`${params.api}/order`, pl, {headers:{'X-WP-Nonce':params.nonce}});
                if(r.data.success) {
                    lastReceipt.value = { ...r.data, items:[...cart.value], grandTotal:grandTotal.value, paymentMethod:method.value, cashReceived:paid.value, cashChange:change.value, cashier:activeCashier.value };
                    setTimeout(() => {
                        const w = window.open('','','width=400,height=600');
                        w.document.write(`<html><head><style>body{margin:0} .receipt{width:${config.value.printer_width}}</style></head><body>${document.getElementById('receipt-print').innerHTML}</body></html>`);
                        w.document.close(); w.focus(); w.print();
                    }, 300);
                    cart.value=[]; paid.value=''; modal.value=false;
                }
            } catch(e){ alert("Gagal: "+e.message); } finally { processing.value=false; }
        };

        const setCategory = (s) => { curCat.value=s; find(); };

        onMounted(async () => {
            try { if((await db.prod.count())===0) await sync(); else await find(); } catch(e) { console.error(e); }
            window.addEventListener('keydown', e => { if(e.key==='F3'){ e.preventDefault(); document.querySelector('input[type=text]')?.focus(); } });
        });

        watch([search, curCat], find);
        watch(modal, (v) => { if(v && method.value==='cash') nextTick(()=>cashInput.value?.focus()); });

        return { config, products, categories, cart, recentOrders, analytics, curCat, search, loading, syncing, ordersLoading, viewMode, activeCashier, showMobileCart, showCart, modal, method, paid, processing, cashInput, grandTotal, change, quickCash, cartTotalQty, fmt, sync, setCategory, fetchOrders, fetchStats, add, rem, qty, clearCart, toggleHold, setView:(m)=>{viewMode.value=m;}, openPayModal:()=>modal.value=true, checkout, lastReceipt };
    }
}).mount('#app');
"""

TPL_CSS = r"""[v-cloak]{display:none!important}.custom-scrollbar::-webkit-scrollbar{width:4px;height:4px}.custom-scrollbar::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}.no-scrollbar::-webkit-scrollbar{display:none}@media print{body *{visibility:hidden;height:0;overflow:hidden}#receipt-print,#receipt-print *{visibility:visible;height:auto;overflow:visible}#receipt-print{position:absolute;left:0;top:0;width:100%}}"""

# ==========================================
# 3. FILE WRITER
# ==========================================

def write(dir, path, content):
    full = os.path.join(dir, path)
    os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, 'w', encoding='utf-8') as f: f.write(content)
    print(f"[OK] {path}")

def main():
    if os.path.exists(OUTPUT_DIR): shutil.rmtree(OUTPUT_DIR)
    os.makedirs(OUTPUT_DIR)

    files = {
        f"{PLUGIN_SLUG}.php": TPL_MAIN,
        "includes/Core/Activator.php": TPL_ACTIVATOR,
        "includes/Core/Deactivator.php": TPL_DEACTIVATOR,
        "includes/Core/i18n.php": TPL_I18N,
        "includes/Admin/Admin.php": TPL_ADMIN_PHP,
        "includes/API/RestController.php": TPL_API,
        "includes/Frontend/UI.php": TPL_UI,
        "templates/app.php": TPL_APP_HTML,
        "assets/js/pos-app.js": TPL_APP_JS,
        "assets/css/pos-style.css": TPL_CSS,
        "assets/css/admin.css": TPL_ADMIN_CSS,
        "readme.txt": f"=== {PLUGIN_NAME} ===\nStable tag: {PLUGIN_VERSION}\nDescription: Professional POS System.",
        "index.php": "<?php // Silence is golden",
        "assets/index.php": "<?php // Silence is golden",
        "assets/css/index.php": "<?php // Silence is golden",
        "assets/js/index.php": "<?php // Silence is golden",
        "includes/index.php": "<?php // Silence is golden",
        "includes/Admin/index.php": "<?php // Silence is golden",
        "includes/API/index.php": "<?php // Silence is golden",
        "includes/Core/index.php": "<?php // Silence is golden",
        "includes/Frontend/index.php": "<?php // Silence is golden",
        "templates/index.php": "<?php // Silence is golden",
    }

    for p, c in files.items(): write(OUTPUT_DIR, p, c)
    
    shutil.make_archive(f"{PLUGIN_SLUG}-v{PLUGIN_VERSION}", 'zip', OUTPUT_DIR)
    print(f"\n[SELESAI] Zip file: {PLUGIN_SLUG}-v{PLUGIN_VERSION}.zip")

if __name__ == "__main__":
    main()
