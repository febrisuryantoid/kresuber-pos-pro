<?php
namespace Kresuber\POS_Pro\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UI {

    /**
     * Menambahkan Rewrite Rule untuk endpoint /pos/
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' );
    }

    /**
     * Mendaftarkan query var agar WordPress mengenali parameter URL
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'kresuber_pos';
        return $vars;
    }

    /**
     * Menangani logika loading aplikasi POS
     * Menggunakan prioritas 1 agar dieksekusi sebelum redirect lain
     */
    public function load_pos_app() {
        global $wp_query;

        // 1. Deteksi Standar via Query Var (Ideal)
        $is_pos = get_query_var( 'kresuber_pos' ) == 1;

        // 2. Deteksi Fallback via URI (Jika Rewrite Rule macet)
        if ( ! $is_pos && isset( $_SERVER['REQUEST_URI'] ) ) {
            $path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            // Cek jika URL berakhiran /pos atau /pos/
            if ( preg_match( '#/pos/?$#i', $path ) ) {
                $is_pos = true;
            }
        }

        if ( $is_pos ) {
            // Stop cache headers untuk halaman App
            if ( ! headers_sent() ) {
                nocache_headers();
            }

            // Cek Permission: Hanya Admin & Shop Manager
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_orders' ) ) {
                $login_url = wp_login_url( home_url( '/pos' ) );
                if ( headers_sent() ) {
                    echo "<meta http-equiv='refresh' content='0;url=$login_url'>";
                    exit;
                } else {
                    wp_redirect( $login_url );
                    exit;
                }
            }

            // Persiapkan Variabel Config untuk View
            $themes = [
                'retail'    => '#00A78E', 
                'grosir'    => '#0B5FFF', 
                'sembako'   => '#F59E0B',
                'kelontong' => '#7C4DFF', 
                'sayur'     => '#10B981', 
                'buah'      => '#FF6B6B'
            ];
            
            $theme_key = get_option( 'kresuber_pos_theme', 'retail' );
            $site_name = get_bloginfo( 'name' );
            
            // Konfigurasi JS (Global Variable)
            $kresuber_config = [
                'logo'          => get_option( 'kresuber_pos_logo', '' ),
                'qris'          => get_option( 'kresuber_qris_image', '' ),
                'printer_width' => get_option( 'kresuber_printer_width', '58mm' ),
                'cashiers'      => json_decode( get_option( 'kresuber_cashiers', '[]' ) ) ?: ['Kasir 1'],
                'theme_color'   => $themes[ $theme_key ] ?? '#00A78E',
                'site_name'     => $site_name,
                'ajax_url'      => admin_url( 'admin-ajax.php' ), // Cadangan jika butuh
            ];

            // Load Template
            $template_path = KRESUBER_POS_PRO_PATH . 'templates/app.php';
            
            if ( file_exists( $template_path ) ) {
                include $template_path;
                exit;
            } else {
                wp_die( 'Error: File template POS (app.php) tidak ditemukan. Silakan instal ulang plugin.' );
            }
        }
    }
}