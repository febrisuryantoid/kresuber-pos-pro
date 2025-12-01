<?php
namespace Kresuber\POS_Pro\Frontend;

if ( ! defined( 'ABSPATH' ) ) exit;

class UI {
    public function add_rewrite_rules() { add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' ); }
    public function add_query_vars( $vars ) { $vars[] = 'kresuber_pos'; return $vars; }

    public function load_pos_app() {
        if ( get_query_var( 'kresuber_pos' ) == 1 ) {
            
            if ( ! is_user_logged_in() ) {
                auth_redirect();
                exit;
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( '<h1>Akses Ditolak</h1><p>Hanya admin/kasir yang diizinkan.</p>', 403 );
            }

            // Ambil QRIS URL
            global $kresuber_qris_url;
            $kresuber_qris_url = get_option( 'kresuber_qris_image', '' );

            $template = KRESUBER_POS_PRO_PATH . 'templates/app.php';
            if ( file_exists( $template ) ) {
                include $template;
                exit;
            } else {
                wp_die("Error: Template file tidak ditemukan.");
            }
        }
    }
}