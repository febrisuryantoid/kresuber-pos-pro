<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.6.1';

    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_settings', 'kresuber_business_type' );
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
        $theme = get_option( 'kresuber_business_type', 'retail' );
        $qris = get_option( 'kresuber_qris_image' );
        $width = get_option( 'kresuber_printer_width', '58mm' );
        $cashiers = get_option( 'kresuber_cashiers', '[]' );
        
        $themes = [
            'retail' => ['label' => 'Toko Retail (Biru)', 'color' => '#3b82f6'],
            'grosir' => ['label' => 'Toko Grosir (Ungu)', 'color' => '#8b5cf6'],
            'sembako' => ['label' => 'Warung Sembako (Merah)', 'color' => '#ef4444'],
            'sayur' => ['label' => 'Warung Sayur (Hijau)', 'color' => '#22c55e'],
            'buah' => ['label' => 'Warung Buah (Oranye)', 'color' => '#f97316'],
            'cafe' => ['label' => 'Cafe / Resto (Hitam)', 'color' => '#334155'],
        ];
        ?>
        <div class="wrap kresuber-wrap">
            <div class="k-header">
                <div class="k-brand">
                    <h1>Kresuber POS Pro <span class="k-badge">v1.6.1</span></h1>
                    <p>Solusi Kasir Terintegrasi WooCommerce</p>
                </div>
                <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="k-btn-launch">
                    <span class="dashicons dashicons-external"></span> Buka Aplikasi POS
                </a>
            </div>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php settings_fields('kresuber_pos_settings'); do_settings_sections('kresuber_pos_settings'); ?>
                
                <div class="k-grid">
                    <!-- Branding -->
                    <div class="k-card">
                        <div class="k-card-header"><span class="dashicons dashicons-art"></span> Branding & Tema</div>
                        <div class="k-card-body">
                            <div class="k-form-group">
                                <label>Jenis Usaha (Tema Warna)</label>
                                <select name="kresuber_business_type" class="k-input">
                                    <?php foreach($themes as $key => $val): ?>
                                        <option value="<?php echo $key; ?>" <?php selected($theme, $key); ?>><?php echo $val['label']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="k-form-group">
                                <label>Logo Toko</label>
                                <div class="k-upload-wrapper">
                                    <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                    <div class="k-preview" id="logo-preview">
                                        <?php echo $logo ? '<img src="'.esc_url($logo).'">' : '<span>No Logo</span>'; ?>
                                    </div>
                                    <button type="button" class="button upload-btn" data-target="#kresuber_pos_logo">Pilih Logo</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hardware -->
                    <div class="k-card">
                        <div class="k-card-header"><span class="dashicons dashicons-printer"></span> Hardware & Pembayaran</div>
                        <div class="k-card-body">
                            <div class="k-form-group">
                                <label>Printer Thermal</label>
                                <select name="kresuber_printer_width" class="k-input">
                                    <option value="58mm" <?php selected($width, '58mm'); ?>>58mm (Portable)</option>
                                    <option value="80mm" <?php selected($width, '80mm'); ?>>80mm (Desktop)</option>
                                </select>
                            </div>
                            <div class="k-form-group">
                                <label>QRIS Statis</label>
                                <div class="k-upload-wrapper">
                                    <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                    <div class="k-preview" id="qris-preview">
                                        <?php echo $qris ? '<img src="'.esc_url($qris).'">' : '<span>No QRIS</span>'; ?>
                                    </div>
                                    <button type="button" class="button upload-btn" data-target="#kresuber_qris_image">Upload QRIS</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cashier -->
                    <div class="k-card full-width">
                        <div class="k-card-header"><span class="dashicons dashicons-id"></span> Manajemen Kasir</div>
                        <div class="k-card-body">
                            <p class="k-desc">Masukkan nama kasir (pisahkan dengan koma).</p>
                            <?php $cashier_str = implode(', ', json_decode($cashiers) ?: []); ?>
                            <textarea id="cashier_input" class="large-text code" rows="3"><?php echo esc_textarea($cashier_str); ?></textarea>
                            <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr($cashiers); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="k-footer">
                    <?php submit_button('Simpan Pengaturan', 'primary large'); ?>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('.upload-btn').click(function(e){ 
                e.preventDefault(); 
                var t=$(this).data('target'); 
                var p=$(this).siblings('.k-preview');
                var f=wp.media({title:'Pilih Gambar', multiple:false}); 
                f.on('select',function(){ 
                    var url = f.state().get('selection').first().toJSON().url;
                    $(t).val(url); 
                    p.html('<img src=\"'+url+'\">');
                }); 
                f.open(); 
            });
            $('form').submit(function(){
                var arr = $('#cashier_input').val().split(',').map(s => s.trim()).filter(s => s);
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
        });
        </script>
        <?php
    }
}
