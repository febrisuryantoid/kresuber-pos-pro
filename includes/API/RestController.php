<?php
namespace Kresuber\POS_Pro\API;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;
use WC_Order;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestController extends WP_REST_Controller {
    protected $namespace = 'kresuber-pos/v1';

    public function register_routes() {
        // Route: Ambil Data Produk (Mendukung Caching)
        register_rest_route( $this->namespace, '/products', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_products' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // Route: Buat Pesanan Baru
        register_rest_route( $this->namespace, '/order', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_order' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'items' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param) && !empty($param);
                    }
                ]
            ]
        ] );

        // Route: Riwayat Pesanan
        register_rest_route( $this->namespace, '/orders', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_orders' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
    }

    /**
     * Cek permission: Shop Manager atau Admin
     * Kita izinkan 'edit_shop_orders' agar role Shop Manager/Kasir bisa akses.
     */
    public function check_permission() {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' );
    }

    /**
     * 1. GET PRODUCTS
     * Mengambil semua produk dengan optimasi memori dan caching.
     */
    public function get_products( $request ) {
        global $wpdb;

        // Cek Cache Version berdasarkan waktu update terakhir produk di DB
        // Query ini aman untuk Products (karena Products masih menggunakan tabel posts standar di WP saat ini)
        $last_update = $wpdb->get_var( "SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = 'product'" );
        $cache_key   = 'kresuber_pos_products_full';
        $ver_key     = 'kresuber_pos_ver';
        
        $cached_data = get_transient( $cache_key );
        $cached_ver  = get_transient( $ver_key );

        // Jika cache ada, versinya sama, dan tidak dipaksa reload -> Return Cache
        if ( $cached_data && $cached_ver == $last_update && ! isset( $request['force'] ) ) {
            return rest_ensure_response( $cached_data );
        }

        // Persiapan Heavy Lifting
        if ( function_exists( 'set_time_limit' ) ) set_time_limit( 0 );
        if ( function_exists( 'ini_set' ) ) ini_set( 'memory_limit', '512M' );

        // Ambil Produk via WC CRUD (HPOS Safe)
        // limit -1 untuk mengambil semua (hati-hati jika produk > 5000, sebaiknya pagination di masa depan)
        $products = wc_get_products( [
            'limit'  => -1,
            'status' => 'publish',
        ] );

        $data = [];
        foreach ( $products as $product ) {
            // Skip jika bukan produk (safety check)
            if ( ! is_a( $product, 'WC_Product' ) ) continue;

            // Gambar
            $img_id = $product->get_image_id();
            $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : wc_placeholder_img_src();

            // Kategori (Ambil yang pertama saja untuk grouping di POS)
            $cat_ids = $product->get_category_ids();
            $cat_slug = 'lainnya';
            $cat_name = 'Lainnya';
            
            if ( ! empty( $cat_ids ) ) {
                $term = get_term( $cat_ids[0], 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $cat_slug = $term->slug;
                    $cat_name = $term->name;
                }
            }

            // Variasi Harga (jika ada sale)
            $price = (float) $product->get_price();
            $reg_price = (float) $product->get_regular_price();
            if ( ! $reg_price ) $reg_price = $price;

            $data[] = [
                'id'            => $product->get_id(),
                'name'          => $product->get_name(),
                'sku'           => (string) $product->get_sku(),
                'barcode'       => (string) $product->get_meta('_barcode'), // Meta barcode umum
                'price'         => $price,
                'regular_price' => $reg_price,
                'stock'         => $product->get_stock_quantity() ?? 9999,
                'stock_status'  => $product->get_stock_status(),
                'image'         => $img_url,
                'category_slug' => $cat_slug,
                'category_name' => $cat_name,
                'type'          => $product->get_type(),
            ];
        }

        // Simpan Cache selama 1 minggu (akan direfresh jika ada update produk)
        set_transient( $cache_key, $data, 7 * DAY_IN_SECONDS );
        set_transient( $ver_key, $last_update, 7 * DAY_IN_SECONDS );

        return rest_ensure_response( $data );
    }

    /**
     * 2. CREATE ORDER
     * Membuat order WooCommerce standard yang mengurangi stok (HPOS Compliant).
     */
    public function create_order( $request ) {
        $params = $request->get_json_params();
        
        if ( empty( $params['items'] ) ) {
            return new WP_Error( 'no_items', 'Keranjang kosong', [ 'status' => 400 ] );
        }

        try {
            // Buat Objek Order Baru (HPOS Compatible)
            $order = wc_create_order();
            
            // Tambahkan Produk
            foreach ( $params['items'] as $item ) {
                $product = wc_get_product( $item['id'] );
                if ( ! $product ) continue;
                
                // Tambahkan ke order (ID Produk, Qty)
                $order->add_product( $product, intval( $item['qty'] ) );
            }

            // Data Kasir & Billing (Default Walk-in)
            $cashier_name = isset( $params['cashier'] ) ? sanitize_text_field( $params['cashier'] ) : 'Kasir';
            
            $order->set_billing_first_name( 'Walk-in' );
            $order->set_billing_last_name( 'Customer' );
            $order->set_billing_email( 'pos-order@local.store' );
            $order->set_created_via( 'kresuber_pos' );
            
            // Metode Pembayaran
            $payment_method = isset( $params['payment_method'] ) ? sanitize_text_field( $params['payment_method'] ) : 'cash';
            $order->set_payment_method( $payment_method );
            $order->set_payment_method_title( strtoupper( $payment_method ) );

            // Hitung Total
            $order->calculate_totals();

            // Metadata Pembayaran (Tunai/Kembali) - HPOS Safe (menggunakan setter meta)
            $tendered = isset( $params['amount_tendered'] ) ? floatval( $params['amount_tendered'] ) : 0;
            $change   = isset( $params['change'] ) ? floatval( $params['change'] ) : 0;

            if ( $payment_method === 'cash' && $tendered > 0 ) {
                $order->add_meta_data( '_pos_cash_tendered', $tendered );
                $order->add_meta_data( '_pos_cash_change', $change );
                $note = sprintf( "POS Transaksi Tunai. Terima: %s, Kembali: %s. (Kasir: %s)", wc_price($tendered), wc_price($change), $cashier_name );
            } else {
                $note = sprintf( "POS Transaksi via %s. (Kasir: %s)", strtoupper($payment_method), $cashier_name );
            }

            $order->add_order_note( $note );

            // Set Status & Kurangi Stok
            // 'completed' secara otomatis mengurangi stok jika dikonfigurasi di WC, 
            // tapi kita bisa paksa wc_reduce_stock_levels() untuk memastikan.
            $order->update_status( 'completed', 'Order selesai via Kresuber POS.' );
            
            // Simpan Order (Penting untuk HPOS)
            $order->save();

            // Return Data Struk
            return rest_ensure_response( [
                'success'      => true,
                'id'           => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'total'        => $order->get_total(),
                'date'         => $order->get_date_created()->date_i18n( 'd/m/Y H:i' ),
                'cashier'      => $cashier_name
            ] );

        } catch ( \Exception $e ) {
            return new WP_Error( 'order_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    /**
     * 3. GET ORDERS HISTORY
     * Mengambil riwayat order untuk ditampilkan di POS (HPOS Compliant).
     */
    public function get_orders( $request ) {
        // Menggunakan wc_get_orders yang support HPOS dan Legacy
        $orders = wc_get_orders( [
            'limit'   => 20,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => [ 'completed', 'processing' ],
            'created_via' => 'kresuber_pos' // Filter khusus order POS jika didukung
        ] );

        $data = [];
        foreach ( $orders as $order ) {
            $items_list = [];
            foreach ( $order->get_items() as $item ) {
                $items_list[] = $item->get_name() . ' x' . $item->get_quantity();
            }

            $data[] = [
                'id'              => $order->get_id(),
                'number'          => $order->get_order_number(),
                'status'          => $order->get_status(),
                'total_formatted' => $order->get_formatted_order_total(),
                'date'            => $order->get_date_created()->date_i18n( 'd/m/y H:i' ),
                'items_summary'   => implode( ', ', $items_list )
            ];
        }

        return rest_ensure_response( $data );
    }
}