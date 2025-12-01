<?php
namespace Kresuber\POS_Pro\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UI {

    public function add_rewrite_rules() {
        add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'kresuber_pos';
        return $vars;
    }

    public function load_pos_app() {
        $is_pos_request = get_query_var( 'kresuber_pos' ) == 1;

        if ( ! $is_pos_request && isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            if ( preg_match( '#/pos/?$#i', $request_path ) ) {
                $is_pos_request = true;
            }
        }

        if ( ! $is_pos_request ) {
            return;
        }

        // Cek WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_die( 'WooCommerce diperlukan.', 'Error', [ 'response' => 500 ] );
        }

        // Cek Login
        if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_shop_orders' ) ) ) {
            wp_redirect( wp_login_url( home_url( '/pos' ) ) );
            exit;
        }

        // Header Cleaning
        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'X-Frame-Options: SAMEORIGIN' );
        }

        // Config Vars
        $theme_key = get_option( 'kresuber_pos_theme', 'retail' );
        $themes = [
            'retail' => '#00A78E', 'grosir' => '#0B5FFF', 'sembako' => '#F59E0B',
            'kelontong' => '#7C4DFF', 'sayur' => '#10B981', 'buah' => '#FF6B6B'
        ];
        
        $kresuber_config = [
            'logo'          => get_option( 'kresuber_pos_logo', '' ),
            'qris'          => get_option( 'kresuber_qris_image', '' ),
            'printer_width' => get_option( 'kresuber_printer_width', '58mm' ),
            'cashiers'      => json_decode( get_option( 'kresuber_cashiers', '["Kasir"]' ) ) ?: ['Kasir'],
            'theme_color'   => $themes[ $theme_key ] ?? '#00A78E',
            'site_name'     => get_bloginfo( 'name' ),
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
        ];

        // Load Template dengan Buffer Cleaning
        // Ini menghapus output sampah dari plugin lain (misal spasi/debug text)
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        $template_path = KRESUBER_POS_PRO_PATH . 'templates/app.php';
        
        if ( file_exists( $template_path ) ) {
            include $template_path;
            exit;
        }
    }
}