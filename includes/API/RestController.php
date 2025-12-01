<?php
namespace Kresuber\POS_Pro\API;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) exit;

class RestController extends WP_REST_Controller {

	protected $namespace = 'kresuber-pos/v1';

	public function register_routes() {
		register_rest_route( $this->namespace, '/products', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_products' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		register_rest_route( $this->namespace, '/order', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'create_order' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );
	}

	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	public function get_products( $request ) {
		$args = [ 'limit' => -1, 'status' => 'publish' ];
		$products = wc_get_products( $args );
		$data     = [];

		foreach ( $products as $product ) {
			$image_id  = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src();

			$cat_ids = $product->get_category_ids();
			$cat_slug = 'uncategorized';
            $cat_name = 'Uncategorized';
			if ( ! empty( $cat_ids ) ) {
				$term = get_term( $cat_ids[0], 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$cat_slug = $term->slug;
                    $cat_name = $term->name;
				}
			}

			$data[] = [
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'price'         => (float) $product->get_price(),
				'regular_price' => (float) $product->get_regular_price(),
				'image'         => $image_url,
				'stock'         => $product->get_stock_quantity() ?? 999,
                'stock_status'  => $product->get_stock_status(),
				'sku'           => (string) $product->get_sku(),
				'barcode'       => (string) $product->get_meta( '_barcode' ),
				'category_slug' => $cat_slug,
                'category_name' => $cat_name
			];
		}
		return rest_ensure_response( $data );
	}

	public function create_order( $request ) {
		$params = $request->get_json_params();

		if ( empty( $params['items'] ) ) {
			return new WP_Error( 'no_items', 'Keranjang kosong.', [ 'status' => 400 ] );
		}

		try {
			$order = wc_create_order( [ 'customer_id' => 0 ] ); // 0 = Guest

			foreach ( $params['items'] as $item ) {
				$product = wc_get_product( intval( $item['id'] ) );
				if ( $product ) {
					$order->add_product( $product, intval( $item['qty'] ) );
				}
			}

			$order->set_billing_first_name( 'Walk-in' );
			$order->set_billing_last_name( 'Customer' );
            $order->set_billing_country( 'ID' );
            
            $payment_method = sanitize_text_field($params['payment_method'] ?? 'cash');
			$order->set_payment_method( $payment_method );
			$order->set_payment_method_title( ucfirst( $payment_method ) );

			$order->calculate_totals();
            
            // Meta Data Kasir
            $order->add_meta_data( '_pos_cashier_id', get_current_user_id() );
            $order->add_meta_data( '_pos_amount_tendered', $params['amount_tendered'] ?? 0 );
            $order->add_meta_data( '_pos_change', $params['change'] ?? 0 );

			$order->update_status( 'completed', 'POS Order via Kresuber Pro' );

			return rest_ensure_response( [
				'success'      => true,
				'order_id'     => $order->get_id(),
				'order_number' => $order->get_order_number(),
				'total'        => $order->get_total(),
				'date'         => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			] );

		} catch ( \Exception $e ) {
			return new WP_Error( 'create_order_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}
}