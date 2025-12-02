<?php
namespace Kresuber\POS_Pro\Frontend;
if ( ! defined( 'ABSPATH' ) ) exit;

class UI {
    public function add_rewrite_rules() { add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' ); }
    public function add_query_vars( $vars ) { $vars[] = 'kresuber_pos'; return $vars; }
    
    public function load_pos_app() {
        if ( get_query_var( 'kresuber_pos' ) == 1 ) {
            if ( ! is_user_logged_in() || ! current_user_can('manage_woocommerce') ) { auth_redirect(); exit; }
            
            // Tema Definisi (Server Side config)
            $colors = [
                'retail' => '#10B981', 'blue' => '#3B82F6', 'sunset' => '#F97316', 
                'dark' => '#1F2937', 'pink' => '#EC4899'
            ];
            $theme_key = get_option( 'kresuber_pos_theme', 'retail' );
            
            global $kresuber_config;
            $kresuber_config = [
                'logo' => get_option('kresuber_pos_logo', ''),
                'qris' => get_option('kresuber_qris_image', ''),
                'printer_width' => get_option('kresuber_printer_width', '58mm'),
                'cashiers' => json_decode(get_option('kresuber_cashiers', '["Admin"]')),
                'address' => get_option('kresuber_store_address', ''),
                'theme_color' => $colors[$theme_key] ?? '#10B981',
                'site_name' => get_bloginfo('name')
            ];
            
            include KRESUBER_POS_PRO_PATH . 'templates/app.php';
            exit;
        }
    }
}