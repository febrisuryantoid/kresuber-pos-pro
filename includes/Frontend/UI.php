<?php
namespace Kresuber\POS_Pro\Frontend;
if ( ! defined( 'ABSPATH' ) ) exit;

class UI {
    public function add_rewrite_rules() { add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' ); }
    public function add_query_vars( $vars ) { $vars[] = 'kresuber_pos'; return $vars; }
    public function load_pos_app() {
        if ( get_query_var( 'kresuber_pos' ) == 1 ) {
            if ( ! is_user_logged_in() || ! current_user_can('manage_woocommerce') ) { auth_redirect(); exit; }
            
            global $kresuber_config;
            $theme_key = get_option( 'kresuber_business_type', 'retail' );
            $themes = [
                'retail' => '#3b82f6', 'grosir' => '#8b5cf6', 'sembako' => '#ef4444',
                'sayur' => '#22c55e', 'buah' => '#f97316', 'cafe' => '#334155'
            ];
            
            $kresuber_config = [
                'logo' => get_option('kresuber_pos_logo', ''),
                'qris' => get_option('kresuber_qris_image', ''),
                'printer_width' => get_option('kresuber_printer_width', '58mm'),
                'cashiers' => json_decode(get_option('kresuber_cashiers', '[]')),
                'theme_color' => $themes[$theme_key] ?? '#3b82f6',
                'site_name' => get_bloginfo('name')
            ];
            
            include KRESUBER_POS_PRO_PATH . 'templates/app.php';
            exit;
        }
    }
}
