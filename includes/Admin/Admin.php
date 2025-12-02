<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.9.3.2';

    public function register_settings() {
        register_setting( 'kresuber_pos_group', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_group', 'kresuber_pos_theme' );
        register_setting( 'kresuber_pos_group', 'kresuber_printer_width' );
        register_setting( 'kresuber_pos_group', 'kresuber_qris_image' );
        register_setting( 'kresuber_pos_group', 'kresuber_cashiers' );
        register_setting( 'kresuber_pos_group', 'kresuber_store_address' );
        register_setting( 'kresuber_pos_group', 'kresuber_wa_template' );
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
        $theme = get_option( 'kresuber_pos_theme', 'retail' );
        $qris = get_option( 'kresuber_qris_image' );
        $width = get_option( 'kresuber_printer_width', '58mm' );
        $cashiers_json = get_option( 'kresuber_cashiers', '[]' );
        $address = get_option( 'kresuber_store_address', '' );
        
        $themes = [
            'retail'  => ['label' => 'Retail Hijau',  'color' => '#10B981', 'bg' => '#ECFDF5'],
            'blue'    => ['label' => 'Bisnis Biru',  'color' => '#3B82F6', 'bg' => '#EFF6FF'],
            'sunset'  => ['label' => 'Senja Oranye', 'color' => '#F97316', 'bg' => '#FFF7ED'],
            'dark'    => ['label' => 'Elegan Hitam', 'color' => '#1F2937', 'bg' => '#F3F4F6'],
            'pink'    => ['label' => 'Butik Pink',   'color' => '#EC4899', 'bg' => '#FDF2F8'],
            'purple'  => ['label' => 'Digital Ungu', 'color' => '#8B5CF6', 'bg' => '#F5F3FF'],
        ];
        ?>
        <div class="kp-wrap">
            <div class="kp-header">
                <div class="kp-title">
                    <span class="dashicons dashicons-store" style="font-size: 32px; width: 32px; height: 32px; color: #2563EB;"></span>
                    <div>
                        <h1>Kresuber POS Pro <span class="kp-badge">v1.9.3.2</span></h1>
                        <p>Panel Kontrol Sistem Kasir Warung & PPOB</p>
                    </div>
                </div>
                <div class="kp-actions">
                    <a href="<?php echo esc_url( home_url( '/pos' ) ); ?>" target="_blank" class="kp-btn kp-btn-primary">
                        <span class="dashicons dashicons-external"></span> Buka Aplikasi Kasir
                    </a>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('kresuber_pos_group'); do_settings_sections('kresuber_pos_group'); ?>
                
                <div class="kp-grid">
                    <div class="kp-col">
                        <div class="kp-card">
                            <div class="kp-card-header">
                                <h3><span class="dashicons dashicons-admin-appearance"></span> Tampilan & Identitas</h3>
                            </div>
                            <div class="kp-card-body">
                                <div class="kp-form-group">
                                    <label>Logo Toko (Struk & Header)</label>
                                    <div class="kp-media-upload">
                                        <div class="kp-preview-box" id="logo-preview">
                                            <?php echo $logo ? '<img src="'.esc_url($logo).'">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                        </div>
                                        <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                        <button type="button" class="button" data-target="#kresuber_pos_logo">Pilih Logo</button>
                                    </div>
                                </div>
                                <div class="kp-form-group">
                                    <label>Tema Warna Aplikasi</label>
                                    <div class="kp-theme-grid">
                                        <?php foreach($themes as $k => $v): ?>
                                        <label class="kp-theme-item <?php echo $theme === $k ? 'active' : ''; ?>">
                                            <input type="radio" name="kresuber_pos_theme" value="<?php echo esc_attr($k); ?>" <?php checked($theme, $k); ?>>
                                            <span class="kp-swatch" style="background: <?php echo esc_attr($v['color']); ?>"></span>
                                            <span class="kp-theme-name"><?php echo esc_html($v['label']); ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kp-col">
                        <div class="kp-card">
                             <div class="kp-card-header">
                                <h3><span class="dashicons dashicons-money"></span> Pembayaran & Data</h3>
                            </div>
                            <div class="kp-card-body">
                                <div class="kp-form-group">
                                    <label>Upload Gambar QRIS</label>
                                    <div class="kp-media-upload">
                                        <div class="kp-preview-box" id="qris-preview">
                                            <?php echo $qris ? '<img src="'.esc_url($qris).'">' : '<span class="dashicons dashicons-qr"></span>'; ?>
                                        </div>
                                        <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                        <button type="button" class="button" data-target="#kresuber_qris_image">Upload QRIS</button>
                                    </div>
                                </div>
                                <div class="kp-form-group">
                                    <label>Daftar Nama Kasir</label>
                                    <textarea id="cashier_input" class="kp-textarea" rows="2" placeholder="Pisahkan dengan koma"><?php echo implode(', ', json_decode($cashiers_json) ?: []); ?></textarea>
                                    <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr($cashiers_json); ?>">
                                </div>
                                <div class="kp-form-group">
                                    <label>Alamat Toko (Struk)</label>
                                    <textarea name="kresuber_store_address" class="kp-textarea" rows="2"><?php echo esc_textarea($address); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="kp-save-area">
                            <?php submit_button('Simpan Semua Pengaturan', 'primary large w-full'); ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('button[data-target]').click(function(e){ 
                e.preventDefault(); 
                var t = $(this).data('target'), p = $(this).siblings('.kp-preview-box'), f = wp.media({title:'Pilih Gambar', multiple:false}); 
                f.on('select',function(){ var u = f.state().get('selection').first().toJSON().url; $(t).val(u); p.html('<img src=\"'+u+'\">'); }); f.open(); 
            });
            $('form').submit(function(){
                var raw = $('#cashier_input').val();
                var arr = raw.split(',').map(s => s.trim()).filter(s => s);
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
            $('.kp-theme-item input').change(function(){ $('.kp-theme-item').removeClass('active'); if($(this).is(':checked')) $(this).parent().addClass('active'); });
        });
        </script>
        <?php
    }
}
