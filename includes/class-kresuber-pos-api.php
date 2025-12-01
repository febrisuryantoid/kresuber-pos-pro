<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kresuber_POS_API {

    public function register_routes() {
        register_rest_route('kresuber-pos/v1', '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('kresuber-pos/v1', '/order', [
            'methods' => 'POST',
            'callback' => [$this, 'create_order'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }

    public function check_permission() {
        return current_user_can('manage_woocommerce');
    }

    // Mengambil semua produk untuk disinkronkan ke IndexedDB browser
    public function get_products($request) {
        // Ambil SEMUA produk published (Heavy query, cached in browser later)
        $args = [
            'limit' => -1, 
            'status' => 'publish',
        ];
        
        $products = wc_get_products($args);
        $data = [];

        foreach ($products as $product) {
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();
            
            // Ambil Kategori pertama untuk filtering sederhana
            $cats = $product->get_category_ids();
            $cat_slug = 'uncategorized';
            if(!empty($cats)) {
                $term = get_term($cats[0], 'product_cat');
                if($term && !is_wp_error($term)) $cat_slug = $term->slug;
            }

            $data[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => (float) $product->get_price(),
                'regular_price' => (float) $product->get_regular_price(),
                'image' => $image_url,
                'stock' => $product->get_stock_quantity() ?? 9999,
                'sku'   => (string) $product->get_sku(),
                'barcode' => (string) $product->get_meta('_barcode'), // Support plugin barcode lain
                'category_slug' => $cat_slug
            ];
        }

        return rest_ensure_response($data);
    }

    public function create_order($request) {
        $params = $request->get_json_params();
        $items = $params['items'];
        $payment_method = $params['payment_method'] ?? 'cash';
        $amount_tendered = $params['amount_tendered'] ?? 0;
        $change = $params['change'] ?? 0;

        try {
            $order = wc_create_order();
            
            foreach ($items as $item) {
                $product = wc_get_product($item['id']);
                if ($product) {
                    $order->add_product($product, $item['qty']);
                }
            }

            // Set Billing Dummy (POS Walk-in Customer)
            $order->set_billing_first_name('Walk-in');
            $order->set_billing_last_name('Customer');
            $order->set_payment_method($payment_method);
            $order->set_payment_method_title(ucfirst($payment_method));
            
            // Simpan info pembayaran POS di note/meta
            $order->add_order_note("POS Order. Tunai: $amount_tendered. Kembali: $change");

            $order->calculate_totals();
            
            // Langsung Completed
            $order->update_status('completed', 'Order created via Kresuber POS Pro');

            return rest_ensure_response([
                'success' => true,
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'total' => $order->get_total(),
                'date' => $order->get_date_created()->date('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            return new WP_Error('create_order_error', $e->getMessage(), ['status' => 500]);
        }
    }
}