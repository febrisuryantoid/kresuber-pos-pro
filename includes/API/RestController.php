<?php
namespace Kresuber\POS_Pro\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestController {

    /**
     * Register Routes
     */
    public function register_routes() {
        $namespace = 'kresuber-pos/v1';

        // 1. Health Check (Ping)
        register_rest_route( $namespace, '/health', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'health_check' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ]);

        // 2. Get Products (Paginated)
        register_rest_route( $namespace, '/products', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_products' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default'           => 50, // Default batch size
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);

        // 3. Create Order
        register_rest_route( $namespace, '/order', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_order' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ]);
        
        // 4. Get Recent Orders
        register_rest_route( $namespace, '/orders', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_recent_orders' ],
            'permission_callback' => [ $this, 'check_permission' ]
        ]);
    }

    /**
     * Permission Callback
     * Hanya Admin & Shop Manager
     */
    public function check_permission() {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' );
    }

    /**
     * Health Check Endpoint
     */
    public function health_check() {
        return rest_ensure_response([
            'status' => 'ok', 
            'version' => KRESUBER_POS_PRO_VERSION,
            'time' => time()
        ]);
    }

    /**
     * Get Products with Pagination
     */
    public function get_products( $request ) {
        $page = $request->get_param( 'page' );
        $limit = $request->get_param( 'per_page' );

        // Query WooCommerce standar yang aman
        $args = [
            'limit'    => $limit,
            'page'     => $page,
            'status'   => 'publish',
            'paginate' => true, // Return object with total/max_num_pages
        ];
        
        $results = wc_get_products( $args );
        
        $products = [];
        $wc_products = $results->products;

        foreach ( $wc_products as $product ) {
            // Optimasi gambar
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src();
            
            // Kategori
            $cats = $product->get_category_ids();
            $cat_slug = 'uncategorized';
            $cat_name = 'Lainnya';
            
            if ( ! empty( $cats ) ) {
                $term = get_term( $cats[0], 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $cat_slug = $term->slug;
                    $cat_name = $term->name;
                }
            }

            // Variasi harga (jika ada)
            $price = (float) $product->get_price();
            $regular = (float) $product->get_regular_price();

            $products[] = [
                'id'            => $product->get_id(),
                'name'          => $product->get_name(),
                'price'         => $price,
                'regular_price' => $regular > 0 ? $regular : $price,
                'image'         => $image_url,
                'stock'         => $product->get_stock_quantity() ?? 9999,
                'sku'           => (string) $product->get_sku(),
                'barcode'       => (string) $product->get_meta( '_barcode' ), // Kompatibilitas barcode custom
                'category_slug' => $cat_slug,
                'category_name' => $cat_name
            ];
        }

        return rest_ensure_response([
            'products' => $products,
            'pagination' => [
                'total_items' => $results->total,
                'total_pages' => $results->max_num_pages,
                'current_page' => $page
            ]
        ]);
    }

    /**
     * Create Order Atomic
     */
    public function create_order( $request ) {
        $params = $request->get_json_params();
        
        if ( empty( $params['items'] ) ) {
            return new \WP_Error( 'no_items', 'Keranjang belanja kosong.', [ 'status' => 400 ] );
        }

        $items = $params['items'];
        $payment_method = sanitize_text_field( $params['payment_method'] ?? 'cash' );
        $amount_tendered = floatval( $params['amount_tendered'] ?? 0 );
        $change = floatval( $params['change'] ?? 0 );
        $cashier_name = sanitize_text_field( $params['cashier'] ?? 'Kasir' );

        try {
            // Gunakan WC_Order factory
            $order = wc_create_order();
            
            foreach ( $items as $item ) {
                $product_id = absint( $item['id'] );
                $qty = absint( $item['qty'] );
                
                $product = wc_get_product( $product_id );
                
                if ( ! $product ) continue;

                // Cek Stok Real-time sebelum add
                if ( $product->managing_stock() && ! $product->is_in_stock() ) {
                    throw new \Exception( "Stok produk {$product->get_name()} habis saat checkout." );
                }

                // Add product to order (ini otomatis trigger hold stock di Woo modern)
                $item_id = $order->add_product( $product, $qty );
                
                if ( ! $item_id ) {
                    throw new \Exception( "Gagal menambahkan produk {$product->get_name()} ke pesanan." );
                }
            }

            // Set Billing Data (POS Generic)
            $address = [
                'first_name' => 'POS Walk-in',
                'last_name'  => 'Customer',
                'email'      => 'pos@local.store',
                'phone'      => '',
                'address_1'  => 'Toko Fisik',
                'city'       => '',
                'state'      => '',
                'postcode'   => '',
                'country'    => 'ID'
            ];
            
            $order->set_address( $address, 'billing' );
            $order->set_address( $address, 'shipping' );

            // Payment Details
            $order->set_payment_method( $payment_method );
            $order->set_payment_method_title( ucfirst( $payment_method ) );
            $order->set_customer_note( "Kasir: $cashier_name" );
            
            // Simpan detail pembayaran di meta
            $order->update_meta_data( '_pos_amount_tendered', $amount_tendered );
            $order->update_meta_data( '_pos_change', $change );
            $order->update_meta_data( '_pos_cashier', $cashier_name );
            $order->update_meta_data( '_created_via', 'kresuber_pos' );

            // Kalkulasi Total
            $order->calculate_totals();
            
            // Selesaikan Order (Ini akan mengurangi stok secara permanen)
            $order->payment_complete(); 
            
            // Opsional: Langsung ubah ke Completed agar masuk laporan penjualan hari ini
            $order->update_status( 'completed', "Order POS dibuat oleh $cashier_name. Tunai: " . wc_price($amount_tendered) );

            return rest_ensure_response([
                'success'      => true,
                'order_id'     => $order->get_id(),
                'number'       => $order->get_order_number(),
                'total'        => $order->get_total(),
                'date'         => $order->get_date_created()->date( 'd M Y H:i' ),
                'cashier'      => $cashier_name
            ]);

        } catch ( \Exception $e ) {
            return new \WP_Error( 'create_order_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    /**
     * Get Recent Orders
     */
    public function get_recent_orders() {
        $orders = wc_get_orders([
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_created_via',
            'meta_value' => 'kresuber_pos'
        ]);

        $data = [];
        foreach($orders as $order) {
            $data[] = [
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'date' => $order->get_date_created()->date( 'd/m H:i' ),
                'total_formatted' => $order->get_formatted_order_total(),
                'status' => wc_get_order_status_name( $order->get_status() )
            ];
        }

        return rest_ensure_response($data);
    }
}