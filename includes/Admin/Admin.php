<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.7.0';

    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image' );
        register_setting( 'kresuber_pos_settings', 'kresuber_printer_width' );
        register_setting( 'kresuber_pos_settings', 'kresuber_cashiers' );
    }

    public function register_menu() {
        add_menu_page( 'Kresuber POS', 'Kresuber POS', 'manage_woocommerce', 'kresuber-pos', [ $this, 'render_dashboard' ], 'dashicons-store', 56 );
    }

    public function enqueue_styles() {
        wp_enqueue_media();
        wp_enqueue_style( 'kresuber-admin', KRESUBER_POS_PRO_URL . 'assets/css/admin.css', [], $this->version );
    }

    public function render_dashboard() {
        $logo = get_option( 'kresuber_pos_logo' );
        $qris = get_option( 'kresuber_qris_image' );
        $width = get_option( 'kresuber_printer_width', '58mm' );
        $cashiers_json = get_option( 'kresuber_cashiers', '[]' );
        $cashiers = json_decode($cashiers_json) ?: [];
        ?>
        <div class="wrap kresuber-wrap">
            <h1>Kresuber POS Pro <small>v1.7.0</small></h1>
            <?php settings_errors(); ?>
            <div class="k-grid">
                <div class="k-card">
                    <h2>Status & Akses</h2>
                    <p>Aplikasi POS siap digunakan.</p>
                    <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="button button-primary button-hero">Buka Aplikasi Kasir</a>
                    <hr>
                    <p><small>Jika POS macet saat loading, coba "Hard Refresh" (Ctrl+F5).</small></p>
                </div>
                <div class="k-card">
                    <form method="post" action="options.php">
                        <?php settings_fields('kresuber_pos_settings'); do_settings_sections('kresuber_pos_settings'); ?>
                        <h2>Konfigurasi Toko</h2>
                        <table class="form-table">
                            <tr>
                                <th>Logo POS</th>
                                <td>
                                    <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                    <button type="button" class="button upload-btn" data-target="#kresuber_pos_logo">Pilih Logo</button>
                                    <?php if($logo) echo '<img src="'.esc_url($logo).'" style="height:40px;vertical-align:middle;margin-left:10px">'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>QRIS</th>
                                <td>
                                    <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                    <button type="button" class="button upload-btn" data-target="#kresuber_qris_image">Upload QRIS</button>
                                    <?php if($qris) echo '<span style="color:green;margin-left:10px">✓ Terpasang</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Printer</th>
                                <td>
                                    <select name="kresuber_printer_width">
                                        <option value="58mm" <?php selected($width, '58mm'); ?>>58mm (Kecil)</option>
                                        <option value="80mm" <?php selected($width, '80mm'); ?>>80mm (Besar)</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <h3>Manajemen Kasir</h3>
                        <p>Masukkan nama kasir (pisahkan dengan koma).</p>
                        <?php $cashier_str = implode(', ', $cashiers); ?>
                        <textarea id="cashier_input" class="large-text" rows="2"><?php echo esc_textarea($cashier_str); ?></textarea>
                        <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr($cashiers_json); ?>">
                        
                        <hr>
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('.upload-btn').click(function(e){ e.preventDefault(); var t=$(this).data('target'); var f=wp.media({title:'Pilih',multiple:false}); f.on('select',function(){ $(t).val(f.state().get('selection').first().toJSON().url); }); f.open(); });
            
            $('form').submit(function(){
                var val = $('#cashier_input').val();
                var arr = val.split(',').map(s => s.trim()).filter(s => s !== '');
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
        });
        </script>
        <style>.k-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.k-card{background:#fff;padding:20px;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)}</style>
        <?php
    }
}
