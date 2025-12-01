<?php
namespace Kresuber\POS_Pro\Frontend;

if ( ! defined( 'ABSPATH' ) ) exit;

class UI {
    public function add_rewrite_rules() { add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' ); }
    public function add_query_vars( $vars ) { $vars[] = 'kresuber_pos'; return $vars; }

    public function load_pos_app() {
        if ( get_query_var( 'kresuber_pos' ) == 1 ) {
            if ( ! is_user_logged_in() ) { auth_redirect(); exit; }
            if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Akses Ditolak', 403 ); }

            // Global Config Injection
            global $kresuber_config;
            $kresuber_config = [
                'logo' => get_option( 'kresuber_pos_logo', '' ),
                'qris' => get_option( 'kresuber_qris_image', '' ),
                'printer_width' => get_option( 'kresuber_printer_width', '58mm' ),
                'printer_conn' => get_option( 'kresuber_printer_conn', 'browser' ),
                'cashiers' => json_decode( get_option( 'kresuber_cashiers', '[]' ) )
            ];

            $template = KRESUBER_POS_PRO_PATH . 'templates/app.php';
            if ( file_exists( $template ) ) { include $template; exit; }
            else { wp_die( "Template Missing." ); }
        }
    }
}