<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.6.1';

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
        <div class="kresuber-wrap">
            <!-- Header -->
            <div class="k-header">
                <div class="k-brand">
                    <div class="k-logo-icon"><span class="dashicons dashicons-store"></span></div>
                    <div>
                        <h1>Kresuber POS Pro</h1>
                        <p>SaaS Point of Sale System</p>
                    </div>
                </div>
                <div class="k-header-actions">
                    <span class="k-pill">v1.6.1 Stable</span>
                    <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="k-btn k-btn-primary">
                        Buka Aplikasi POS <span class="dashicons dashicons-arrow-right-alt"></span>
                    </a>
                </div>
            </div>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php settings_fields('kresuber_pos_settings'); do_settings_sections('kresuber_pos_settings'); ?>
                
                <div class="k-layout">
                    <!-- Main Config -->
                    <div class="k-main">
                        <div class="k-card">
                            <div class="k-card-head">
                                <h3>Konfigurasi Toko</h3>
                                <p>Atur identitas visual aplikasi kasir Anda.</p>
                            </div>
                            <div class="k-card-body">
                                <div class="k-form-group">
                                    <label>Logo Aplikasi</label>
                                    <div class="k-upload-area">
                                        <div class="k-preview" id="logo-preview">
                                            <?php echo $logo ? '<img src="'.esc_url($logo).'">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                        </div>
                                        <div class="k-upload-controls">
                                            <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                            <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_pos_logo">Upload Logo</button>
                                            <?php if($logo): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_pos_logo">Hapus</button><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="k-card">
                            <div class="k-card-head">
                                <h3>Pembayaran & Struk</h3>
                                <p>Pengaturan metode pembayaran non-tunai dan printer.</p>
                            </div>
                            <div class="k-card-body">
                                <div class="k-row">
                                    <div class="k-col">
                                        <label>QRIS (Statis)</label>
                                        <div class="k-upload-area small">
                                            <div class="k-preview" id="qris-preview">
                                                <?php echo $qris ? '<img src="'.esc_url($qris).'">' : '<span class="dashicons dashicons-qr"></span>'; ?>
                                            </div>
                                            <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                            <button type="button" class="k-btn k-btn-sm k-btn-outline upload-btn" data-target="#kresuber_qris_image">Upload</button>
                                        </div>
                                    </div>
                                    <div class="k-col">
                                        <label>Lebar Kertas Printer</label>
                                        <select name="kresuber_printer_width" class="k-select">
                                            <option value="58mm" <?php selected($width, '58mm'); ?>>58mm (Standard)</option>
                                            <option value="80mm" <?php selected($width, '80mm'); ?>>80mm (Wide)</option>
                                        </select>
                                        <p class="k-hint">Pilih 58mm untuk printer bluetooth portable.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="k-sidebar">
                        <div class="k-card">
                            <div class="k-card-head">
                                <h3>Kasir Bertugas</h3>
                            </div>
                            <div class="k-card-body">
                                <p class="k-hint">Daftar nama kasir (pisahkan koma).</p>
                                <?php $cashier_str = implode(', ', $cashiers); ?>
                                <textarea id="cashier_input" class="k-textarea" rows="5" placeholder="Budi, Siti, Admin"><?php echo esc_textarea($cashier_str); ?></textarea>
                                <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr($cashiers_json); ?>">
                            </div>
                            <div class="k-card-foot">
                                <?php submit_button('Simpan Perubahan', 'primary w-full', 'submit', false); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($){
            $('.upload-btn').click(function(e){ 
                e.preventDefault(); var t=$(this).data('target'); var p=$(this).parent().siblings('.k-preview');
                var f=wp.media({title:'Pilih Gambar',multiple:false}); 
                f.on('select',function(){ var u=f.state().get('selection').first().toJSON().url; $(t).val(u); p.html('<img src=\"'+u+'\">'); }); 
                f.open(); 
            });
            $('.remove-btn').click(function(){ var t=$(this).data('target'); $(t).val(''); location.reload(); });
            $('form').submit(function(){
                var arr = $('#cashier_input').val().split(',').map(s => s.trim()).filter(s => s);
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
        });
        </script>
        <?php
    }
}
