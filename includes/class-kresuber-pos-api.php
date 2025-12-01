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

    public function get_products($request) {
        $args = [
            'limit' => -1,
            'status' => 'publish',
        ];
        
        $search = $request->get_param('search');
        if($search) {
            $args['s'] = $search;
        }
        
        $category = $request->get_param('category');
        if($category && $category != 'all') {
            $args['category'] = [$category];
        }

        $products = wc_get_products($args);
        $data = [];

        foreach ($products as $product) {
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();

            $data[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'image' => $image_url,
                'stock' => $product->get_stock_quantity() ?? '∞',
                'sku'   => $product->get_sku()
            ];
        }

        return rest_ensure_response($data);
    }

    public function create_order($request) {
        $params = $request->get_json_params();
        $items = $params['items'];
        $payment_method = $params['payment_method'] ?? 'cod';

        try {
            $order = wc_create_order();
            
            foreach ($items as $item) {
                $product = wc_get_product($item['id']);
                if ($product) {
                    $order->add_product($product, $item['qty']);
                }
            }

            $order->set_payment_method($payment_method);
            $order->calculate_totals();
            
            if ($params['status'] === 'completed') {
                $order->update_status('completed', 'Order created via Kresuber POS Pro');
            } else {
                $order->update_status('processing', 'Order created via Kresuber POS Pro');
            }

            return rest_ensure_response([
                'success' => true,
                'order_id' => $order->get_id(),
                'total' => $order->get_total()
            ]);

        } catch (Exception $e) {
            return new WP_Error('create_order_error', $e->getMessage(), ['status' => 500]);
        }
    }
}