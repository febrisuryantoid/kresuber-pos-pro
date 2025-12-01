<?php
namespace Kresuber\POS_Pro\API;
use WP_Error, WP_REST_Controller, WP_REST_Server;
use Kresuber\POS_Pro\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) exit;

class RestController extends WP_REST_Controller {
    protected $namespace = 'kresuber-pos/v1';

    public function register_routes() {
        register_rest_route( $this->namespace, '/products', [ 'methods' => 'GET', 'callback' => [ $this, 'get_products' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/orders', [ 'methods' => 'GET', 'callback' => [ $this, 'get_orders' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/order', [ 'methods' => 'POST', 'callback' => [ $this, 'create_order' ], 'permission_callback' => [ $this, 'perm' ] ] );
        register_rest_route( $this->namespace, '/health', [ 'methods' => 'GET', 'callback' => [ $this, 'get_health' ], 'permission_callback' => '__return_true' ] );
    }

    public function perm() { return current_user_can('manage_woocommerce'); }

    public function get_health() {
        return rest_ensure_response([
            'status' => 'ok',
            'db' => 'connected',
            'time' => time()
        ]);
    }

    public function get_products($r) {
        Logger::log("Fetching products started");
        $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
        $data = [];
        foreach($products as $p) {
            $img = $p->get_image_id() ? wp_get_attachment_image_url($p->get_image_id(), 'medium') : wc_placeholder_img_src();
            $cats = $p->get_category_ids();
            $c_slug = 'uncategorized'; $c_name = 'Lainnya';
            if(!empty($cats) && ($t=get_term($cats[0]))) { $c_slug=$t->slug; $c_name=$t->name; }
            $data[] = [
                'id'=>$p->get_id(), 'name'=>$p->get_name(), 'price'=>(float)$p->get_price(),
                'image'=>$img, 'stock'=>$p->get_stock_quantity()??999, 'stock_status'=>$p->get_stock_status(),
                'sku'=>(string)$p->get_sku(), 'barcode'=>(string)$p->get_meta('_barcode'),
                'category_slug'=>$c_slug, 'category_name'=>$c_name
            ];
        }
        Logger::log("Fetching products done. Count: " + count($data));
        return rest_ensure_response($data);
    }

    public function get_orders($r) {
        $orders = wc_get_orders(['limit'=>20, 'orderby'=>'date', 'order'=>'DESC']);
        $data = [];
        foreach($orders as $o) {
            $items = []; foreach($o->get_items() as $i) $items[] = $i->get_name().' x'.$i->get_quantity();
            $data[] = [
                'id'=>$o->get_id(), 'number'=>$o->get_order_number(), 'status'=>$o->get_status(),
                'total'=>$o->get_formatted_order_total(), 'date'=>$o->get_date_created()->date('d M H:i'),
                'items_summary'=>implode(', ', $items)
            ];
        }
        return rest_ensure_response($data);
    }

    public function create_order($r) {
        $p = $r->get_json_params();
        try {
            $order = wc_create_order(['customer_id'=>0]);
            foreach($p['items'] as $i) { $prod=wc_get_product($i['id']); if($prod) $order->add_product($prod, $i['qty']); }
            $order->set_payment_method($p['payment_method']??'cash');
            $order->calculate_totals(); $order->payment_complete();
            return rest_ensure_response(['success'=>true, 'order_number'=>$order->get_order_number(), 'total'=>$order->get_total(), 'date'=>$order->get_date_created()->date('d M Y H:i')]);
        } catch(\Exception $e) { return new WP_Error('err', $e->getMessage()); }
    }
}
