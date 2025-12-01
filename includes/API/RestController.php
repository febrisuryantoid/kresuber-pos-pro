<?php
namespace Kresuber\POS_Pro\API;
use WP_Error, WP_REST_Controller, WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) exit;

class RestController extends WP_REST_Controller {
    protected $namespace = 'kresuber-pos/v1';

    public function register_routes() {
        register_rest_route( $this->namespace, '/products', [ 'methods' => 'GET', 'callback' => [ $this, 'get_products' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/orders', [ 'methods' => 'GET', 'callback' => [ $this, 'get_orders' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/order', [ 'methods' => 'POST', 'callback' => [ $this, 'create_order' ], 'permission_callback' => [ $this, 'perm' ] ] );
    }

    // FIX: Izinkan 'edit_shop_orders' agar Kasir (Non-Admin) bisa akses, tidak hanya 'manage_woocommerce'
    public function perm() { 
        return current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders'); 
    }

    public function get_products($r) {
        global $wpdb;

        // --- OPTIMASI CACHE START ---
        $last_db_mod = $wpdb->get_var("SELECT MAX(post_modified) FROM $wpdb->posts WHERE post_type = 'product' AND post_status = 'publish'");
        $cache_key = 'kresuber_pos_products_data';
        $ver_key   = 'kresuber_pos_products_ver';
        
        $cached_data = get_transient($cache_key);
        $cached_ver  = get_transient($ver_key);

        if ($cached_data && $cached_ver === $last_db_mod && !isset($r['force'])) {
            return rest_ensure_response($cached_data);
        }

        // RESOURCE BOOST: Mencegah timeout saat memuat ribuan produk
        if (function_exists('set_time_limit')) set_time_limit(0);
        if (function_exists('ini_set')) ini_set('memory_limit', '512M');

        $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
        $data = [];
        
        foreach($products as $p) {
            // FIX: Validasi image ID untuk mencegah query lambat
            $img_id = $p->get_image_id();
            $img = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : wc_placeholder_img_src();
            
            // FIX CRITICAL: Validasi get_term agar tidak crash jika kategori error/terhapus
            $cats = $p->get_category_ids();
            $c_slug = 'lainnya'; $c_name = 'Lainnya';
            
            if(!empty($cats)) {
                $t = get_term($cats[0], 'product_cat');
                if ($t && !is_wp_error($t)) { // Tambahan cek !is_wp_error
                    $c_slug = $t->slug;
                    $c_name = $t->name;
                }
            }
            
            $data[] = [
                'id' => $p->get_id(), 
                'name' => $p->get_name(), 
                'price' => (float)$p->get_price(),
                'image' => $img, 
                'stock' => $p->get_stock_quantity() ?? 999, 
                'stock_status' => $p->get_stock_status(),
                'sku' => (string)$p->get_sku(), 
                'barcode' => (string)$p->get_meta('_barcode'),
                'category_slug' => $c_slug, 
                'category_name' => $c_name
            ];
        }

        set_transient($cache_key, $data, 7 * DAY_IN_SECONDS);
        set_transient($ver_key, $last_db_mod, 7 * DAY_IN_SECONDS);
        // --- OPTIMASI CACHE END ---

        return rest_ensure_response($data);
    }

    public function get_orders($r) {
        $orders = wc_get_orders(['limit'=>30, 'orderby'=>'date', 'order'=>'DESC']);
        $data = [];
        foreach($orders as $o) {
            $items = [];
            foreach($o->get_items() as $i) {
                $items[] = [ 'name' => $i->get_name(), 'qty' => $i->get_quantity() ];
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

    public function create_order($r) {
        $p = $r->get_json_params();
        try {
            $order = wc_create_order(['customer_id'=>0]);
            foreach($p['items'] as $i) { 
                $prod=wc_get_product(intval($i['id'])); 
                if($prod) $order->add_product($prod, intval($i['qty'])); 
            }
            
            $order->set_billing_first_name('Walk-in'); 
            $order->set_payment_method($p['payment_method']??'cash');
            
            // Simpan info pembayaran (Tunai/Kembali) sebagai note
            if(isset($p['amount_tendered'])) {
                $note = "POS Transaction via " . strtoupper($p['payment_method']??'CASH');
                if(($p['payment_method']??'') === 'cash') {
                    $note .= ". Bayar: " . wc_price($p['amount_tendered']) . ", Kembali: " . wc_price($p['change']??0);
                }
                $order->add_order_note($note);
            }

            $order->calculate_totals(); 
            $order->payment_complete();
            
            return rest_ensure_response([
                'success'=>true, 
                'order_number'=>$order->get_order_number(), 
                'total'=>$order->get_total()
            ]);
        } catch( \Exception $e ) { return new WP_Error('err', $e->getMessage()); }
    }
}