<?php
namespace Kresuber\POS_Pro\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UI {

    /**
     * Menambahkan Rewrite Rule untuk endpoint /pos/
     * Menjadikan domain.com/pos sebagai titik akses aplikasi.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' );
    }

    /**
     * Mendaftarkan query var agar WordPress mengenali parameter URL custom.
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'kresuber_pos';
        return $vars;
    }

    /**
     * Menangani logika loading aplikasi POS.
     * Dijalankan pada hook 'template_redirect' dengan prioritas 1 (sangat awal).
     */
    public function load_pos_app() {
        // 1. Deteksi apakah user mengakses halaman POS
        $is_pos_request = get_query_var( 'kresuber_pos' ) == 1;

        // [FALLBACK SYSTEM]
        // Jika Rewrite Rule WordPress macet (sering terjadi saat ganti hosting/permalink),
        // kita deteksi manual URL request browser. Ini menjamin POS tetap terbuka 100%.
        if ( ! $is_pos_request && isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            // Cek apakah URL berakhiran '/pos' atau '/pos/' (case insensitive)
            if ( preg_match( '#/pos/?$#i', $request_path ) ) {
                $is_pos_request = true;
            }
        }

        // Jika bukan request POS, berhenti di sini agar tidak membebani server.
        if ( ! $is_pos_request ) {
            return;
        }

        // --- MULAI LOADING APLIKASI POS ---

        // 2. Cek Dependensi WooCommerce
        // Mencegah error fatal jika plugin WooCommerce tidak sengaja nonaktif.
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_die( 
                '<h1>Sistem Terhenti</h1><p>Aplikasi POS membutuhkan plugin <strong>WooCommerce</strong> dalam status aktif.</p>', 
                'Kresuber POS Error', 
                [ 'response' => 500, 'back_link' => true ] 
            );
        }

        // 3. Cek Login & Hak Akses
        // Hanya Admin dan Shop Manager yang boleh masuk.
        if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_orders' ) ) ) {
            // Redirect ke login, lalu kembali ke /pos setelah login sukses
            $login_url = wp_login_url( home_url( '/pos' ) );
            wp_redirect( $login_url );
            exit;
        }

        // 4. Optimasi Header HTTP (Lightweight Mode)
        // Mencegah browser/server menyimpan cache halaman aplikasi yang dinamis.
        if ( ! headers_sent() ) {
            nocache_headers();
            // Security headers tambahan
            header( 'X-Frame-Options: SAMEORIGIN' ); // Mencegah clickjacking
            header( 'X-Content-Type-Options: nosniff' );
        }

        // 5. Persiapkan Data Konfigurasi (Config)
        // Data ini akan dikirim ke template untuk dipakai oleh JavaScript (Vue.js).
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
        
        // Array Config Global
        $kresuber_config = [
            'logo'          => get_option( 'kresuber_pos_logo', '' ),
            'qris'          => get_option( 'kresuber_qris_image', '' ),
            'printer_width' => get_option( 'kresuber_printer_width', '58mm' ),
            // Fallback aman jika JSON rusak
            'cashiers'      => json_decode( get_option( 'kresuber_cashiers', '[]' ) ) ?: ['Kasir Utama'],
            'theme_color'   => $themes[ $theme_key ] ?? '#00A78E',
            'site_name'     => $site_name,
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
        ];

        // 6. Load Template Aplikasi
        // Menggunakan konstanta path absolut agar server tidak bingung mencari file.
        $template_path = KRESUBER_POS_PRO_PATH . 'templates/app.php';
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
            exit; // PENTING: Matikan loading sisa WordPress agar POS ringan & cepat.
        } else {
            // Error handling user-friendly jika file hilang
            wp_die( 
                '<h1>File Sistem Tidak Ditemukan</h1><p>Template aplikasi (<code>templates/app.php</code>) hilang. Mohon instal ulang plugin Kresuber POS.</p>', 
                'Error Fatal', 
                [ 'response' => 500 ] 
            );
        }
    }
}