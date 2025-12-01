<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {

	public function register_menu() {
		add_menu_page(
			__( 'Kresuber POS', 'kresuber-pos-pro' ),
			__( 'Kresuber POS', 'kresuber-pos-pro' ),
			'manage_woocommerce',
			'kresuber-pos',
			[ $this, 'render_dashboard' ],
			'dashicons-store',
			56
		);
	}

	public function enqueue_styles() {
		wp_enqueue_style( 'kresuber-admin', KRESUBER_POS_PRO_URL . 'assets/css/admin.css', [], KRESUBER_POS_PRO_VERSION );
	}

	public function render_dashboard() {
		$pos_url = home_url( '/pos' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Kresuber POS Pro Dashboard', 'kresuber-pos-pro' ); ?></h1>
			<div class="card" style="background:#fff; padding:20px; max-width:600px; border:1px solid #ccd0d4; box-shadow:0 1px 1px rgba(0,0,0,.04);">
				<h2>Status Aplikasi</h2>
				<p>Aplikasi POS aktif. Akses melalui endpoint <code>/pos</code>.</p>
				<a href="<?php echo esc_url( $pos_url ); ?>" target="_blank" class="button button-primary button-hero">Buka Aplikasi POS &rarr;</a>
			</div>
		</div>
		<?php
	}
}