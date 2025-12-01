<?php
namespace Kresuber\POS_Pro\Frontend;

if ( ! defined( 'ABSPATH' ) ) exit;

class UI {

	public function add_rewrite_rules() {
		add_rewrite_rule( '^pos/?$', 'index.php?kresuber_pos=1', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'kresuber_pos';
		return $vars;
	}

	public function load_pos_app() {
		if ( get_query_var( 'kresuber_pos' ) == 1 ) {
            if ( ! is_user_logged_in() ) {
                auth_redirect();
                exit;
            }

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( 'Akses Ditolak. Anda tidak memiliki izin kasir.', '403 Forbidden', [ 'response' => 403 ] );
			}

			$template_path = KRESUBER_POS_PRO_PATH . 'templates/app.php';
			if ( file_exists( $template_path ) ) {
				include $template_path;
				exit;
			}
		}
	}
}