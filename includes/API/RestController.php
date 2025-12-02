<?php
namespace Kresuber\POS_Pro\API;
use WP_Error, WP_REST_Controller;

if ( ! defined( 'ABSPATH' ) ) exit;

class RestController extends WP_REST_Controller {
    protected $namespace = 'kresuber-pos/v1';

    public function register_routes() {
        register_rest_route( $this->namespace, '/products', [ 'methods' => 'GET', 'callback' => [ $this, 'get_products' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/product', [ 'methods' => 'POST', 'callback' => [ $this, 'manage_product' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/product/(?P<id>\d+)', [ 'methods' => 'DELETE', 'callback' => [ $this, 'delete_product' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/orders', [ 'methods' => 'GET', 'callback' => [ $this, 'get_orders' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/order', [ 'methods' => 'POST', 'callback' => [ $this, 'create_order' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/upload', [ 'methods' => 'POST', 'callback' => [ $this, 'upload_image' ], 'permission_callback' => [ $this, 'perm' ] ] );
    }

    public function perm() { return current_user_can('manage_woocommerce'); }

    // --- UPLOAD IMAGE ---
    public function upload_image($r) {
        $files = $r->get_file_params();
        if (empty($files['file'])) return new WP_Error('no_file', 'Tidak ada file', ['status' => 400]);

        $file = $files['file'];
        
        // Use WP Media Handler
        if ( ! function_exists( 'media_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }

        // Fake $_FILES to work with media_handle_upload
        $_FILES['upload_file'] = $file;
        $id = media_handle_upload( 'upload_file', 0 );

        if ( is_wp_error( $id ) ) return $id;

        return rest_ensure_response([
            'success' => true,
            'url' => wp_get_attachment_url($id)
        ]);
    }

    public function get_products($r) {
        $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
        $data = [];
        foreach($products as $p) {
            $img = $p->get_image_id() ? wp_get_attachment_image_url($p->get_image_id(), 'medium') : '';
            $cats = $p->get_category_ids();
            $c_slug = 'lainnya'; $c_name = 'Lainnya';
            if(!empty($cats) && ($t=get_term($cats[0], 'product_cat'))) { $c_slug=$t->slug; $c_name=$t->name; }
            
            $data[] = [
                'id' => $p->get_id(),
                'name' => $p->get_name(),
                'price' => (float) $p->get_price(),
                'cost_price' => (float) $p->get_meta('_kresuber_cost_price'),
                'image' => $img,
                'stock' => $p->get_stock_quantity(),
                'sku' => (string) $p->get_sku(),
                'barcode' => (string) $p->get_meta('_barcode'),
                'icon_override' => (string) $p->get_meta('_kresuber_icon'),
                'category_slug' => $c_slug,
                'category_name' => $c_name
            ];
        }
        return rest_ensure_response($data);
    }

    public function manage_product($r) {
        $p = $r->get_json_params();
        $id = isset($p['id']) ? intval($p['id']) : 0;
        
        try {
            if($id > 0) {
                $product = wc_get_product($id);
                if(!$product) return new WP_Error('not_found', 'Produk tidak ditemukan');
            } else {
                $product = new \WC_Product_Simple();
            }

            $product->set_name(sanitize_text_field($p['name']));
            $product->set_regular_price(floatval($p['price']));
            
            // Image handling (URL from upload)
            if(!empty($p['image_url'])) {
                $attach_id = attachment_url_to_postid($p['image_url']);
                if($attach_id) $product->set_image_id($attach_id);
            }

            if(isset($p['sku'])) $product->set_sku(sanitize_text_field($p['sku']));
            
            if(isset($p['stock']) && $p['stock'] !== '') {
                $product->set_manage_stock(true);
                $product->set_stock_quantity(intval($p['stock']));
            } else {
                $product->set_manage_stock(false);
            }

            if(!empty($p['category'])) {
                $term = term_exists($p['category'], 'product_cat');
                if(!$term) $term = wp_insert_term($p['category'], 'product_cat');
                if(!is_wp_error($term)) $product->set_category_ids([$term['term_id']]);
            }
            
            if(isset($p['cost_price'])) $product->update_meta_data('_kresuber_cost_price', floatval($p['cost_price']));
            if(isset($p['barcode'])) $product->update_meta_data('_barcode', sanitize_text_field($p['barcode']));
            if(isset($p['icon'])) $product->update_meta_data('_kresuber_icon', sanitize_text_field($p['icon']));

            $product->set_status('publish');
            $pid = $product->save();

            return rest_ensure_response(['success'=>true, 'id'=>$pid]);
        } catch(\Exception $e) {
            return new WP_Error('err', $e->getMessage());
        }
    }

    public function delete_product($r) {
        $id = $r['id'];
        $product = wc_get_product($id);
        if($product) { $product->delete(true); return rest_ensure_response(['success'=>true]); }
        return new WP_Error('err', 'Gagal hapus');
    }

    public function get_orders($r) {
        $orders = wc_get_orders(['limit'=>30, 'orderby'=>'date', 'order'=>'DESC']);
        $data = [];
        foreach($orders as $o) {
            $items = []; foreach($o->get_items() as $i) $items[] = ['name'=>$i->get_name(), 'qty'=>$i->get_quantity()];
            $st = $o->get_status();
            
            // Map Status to Label & Color
            $st_label = wc_get_order_status_name($st);
            if ($st === 'on-hold') $st_label = 'Hutang / Menunggu';
            
            $data[] = [
                'id'=>$o->get_id(),
                'number'=>$o->get_order_number(),
                'status'=>$st,
                'status_label'=>$st_label,
                'total_formatted'=>strip_tags($o->get_formatted_order_total()),
                'date'=>$o->get_date_created()->date('d/m H:i'),
                'customer'=>$o->get_formatted_billing_full_name() ?: 'Walk-in',
                'contact'=>$o->get_meta('_debt_contact') ?: '',
                'items'=>$items
            ];
        }
        return rest_ensure_response($data);
    }

    public function create_order($r) {
        $p = $r->get_json_params();
        try {
            $order = wc_create_order(['customer_id'=>0]);
            foreach($p['items'] as $i) { 
                $prod = wc_get_product(intval($i['id'])); 
                if($prod) $order->add_product($prod, intval($i['qty'])); 
            }
            
            $pay_method = $p['payment_method'] ?? 'cash';
            
            // Debt Logic
            if ($pay_method === 'debt') {
                $order->set_status('on-hold', 'Dicatat sebagai Hutang via POS.');
                $order->update_meta_data('_debt_contact', $p['debt_name'] . ' (' . $p['debt_phone'] . ')');
                $order->set_billing_first_name($p['debt_name']);
                $order->set_billing_phone($p['debt_phone']);
                $order->set_payment_method('cod'); // Fallback
                $order->set_payment_method_title('Hutang / Bayar Nanti');
            } else {
                $order->set_billing_first_name('Pos Walk-in');
                $order->set_payment_method($pay_method);
                $order->calculate_totals();
                $order->payment_complete(); // Completed
            }
            
            $order->calculate_totals();
            $order->save();

            return rest_ensure_response(['success'=>true, 'order_number'=>$order->get_order_number(), 'total'=>$order->get_total()]);
        } catch( \Exception $e ) { return new WP_Error('err', $e->getMessage()); }
    }
}
