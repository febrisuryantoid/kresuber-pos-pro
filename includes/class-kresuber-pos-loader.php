<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kresuber_POS_Loader {

    public function run() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once KRESUBER_POS_DIR . 'includes/class-kresuber-pos-api.php';
        require_once KRESUBER_POS_DIR . 'includes/class-kresuber-pos-ui.php';
    }

    private function init_hooks() {
        // Init UI Endpoint (domain.com/pos)
        $ui = new Kresuber_POS_UI();
        add_action('init', [$ui, 'add_rewrite_rules']);
        add_filter('query_vars', [$ui, 'add_query_vars']);
        add_action('template_redirect', [$ui, 'load_pos_app']);

        // Init API REST
        $api = new Kresuber_POS_API();
        add_action('rest_api_init', [$api, 'register_routes']);
    }
}