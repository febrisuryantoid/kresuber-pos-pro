<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kresuber_POS_UI {
    public function add_rewrite_rules() {
        add_rewrite_rule('^pos/?$', 'index.php?kresuber_pos=1', 'top');
    }

    public function add_query_vars($vars) {
        $vars[] = 'kresuber_pos';
        return $vars;
    }

    public function load_pos_app() {
        if (get_query_var('kresuber_pos') == 1) {
            if (!current_user_can('manage_woocommerce')) {
                auth_redirect();
            }
            include KRESUBER_POS_DIR . 'templates/app.php';
            exit;
        }
    }
}