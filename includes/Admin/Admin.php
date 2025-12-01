<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.7.0';

    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_theme' );
        register_setting( 'kresuber_pos_settings', 'kresuber_printer_width' );
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image' );
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
        $theme = get_option( 'kresuber_pos_theme', 'retail' );
        $qris = get_option( 'kresuber_qris_image' );
        $width = get_option( 'kresuber_printer_width', '58mm' );
        $cashiers_json = get_option( 'kresuber_cashiers', '[]' );
        
        // Theme Definitions
        $themes = [
            'retail'  => ['label' => 'Toko Retail',  'color' => '#00A78E', 'bg' => '#F0FBF8'],
            'grosir'  => ['label' => 'Toko Grosir',  'color' => '#0B5FFF', 'bg' => '#F1F6FF'],
            'sembako' => ['label' => 'Warung Sembako', 'color' => '#F59E0B', 'bg' => '#FFF7ED'],
            'kelontong'=>['label' => 'Warung Kelontong','color'=> '#7C4DFF', 'bg' => '#F8F7FF'],
            'sayur'   => ['label' => 'Warung Sayur', 'color' => '#10B981', 'bg' => '#F0FFF4'],
            'buah'    => ['label' => 'Warung Buah',  'color' => '#FF6B6B', 'bg' => '#FFF5F5'],
        ];
        ?>
        <div class="k-wrap">
            <header class="k-header">
                <div class="k-brand">
                    <h1>Kresuber POS Pro <span class="k-pill">v1.7.0</span></h1>
                    <p>Sistem Kasir Toko Modern & Terintegrasi</p>
                </div>
                <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="k-btn k-btn-primary">Buka Aplikasi POS</a>
            </header>

            <form method="post" action="options.php" class="k-form">
                <?php settings_fields('kresuber_pos_settings'); do_settings_sections('kresuber_pos_settings'); ?>
                
                <div class="k-grid">
                    <!-- Card: Tema -->
                    <div class="k-card">
                        <div class="k-card-head"><h3>Tampilan & Tema Toko</h3></div>
                        <div class="k-card-body">
                            <label class="k-label">Pilih Jenis Toko (Tema Warna)</label>
                            <div class="k-theme-grid">
                                <?php foreach($themes as $key => $val): ?>
                                <label class="k-theme-option">
                                    <input type="radio" name="kresuber_pos_theme" value="<?php echo $key; ?>" <?php checked($theme, $key); ?>>
                                    <div class="k-swatch" style="--accent:<?php echo $val['color']; ?>; --bg:<?php echo $val['bg']; ?>">
                                        <div class="k-swatch-header"></div>
                                        <span><?php echo $val['label']; ?></span>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="k-mt-20">
                                <label class="k-label">Logo Toko</label>
                                <div class="k-media-input">
                                    <div class="k-preview-img" id="logo-preview">
                                        <?php echo $logo ? '<img src="'.esc_url($logo).'">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                    </div>
                                    <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                    <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_pos_logo">Upload Logo</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Hardware & Payment -->
                    <div class="k-card">
                        <div class="k-card-head"><h3>Hardware & Pembayaran</h3></div>
                        <div class="k-card-body">
                            <label class="k-label">QRIS Pembayaran (Statis)</label>
                            <div class="k-media-input">
                                <div class="k-preview-img" id="qris-preview">
                                    <?php echo $qris ? '<img src="'.esc_url($qris).'">' : '<span class="dashicons dashicons-qr"></span>'; ?>
                                </div>
                                <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_qris_image">Upload QRIS</button>
                            </div>
                            <hr class="k-divider">
                            <label class="k-label">Lebar Printer Thermal</label>
                            <select name="kresuber_printer_width" class="k-input">
                                <option value="58mm" <?php selected($width, '58mm'); ?>>58mm (Portable / Bluetooth)</option>
                                <option value="80mm" <?php selected($width, '80mm'); ?>>80mm (Desktop)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Card: Kasir -->
                    <div class="k-card full-width">
                        <div class="k-card-head"><h3>Manajemen Kasir</h3></div>
                        <div class="k-card-body">
                            <label class="k-label">Nama Kasir (Pisahkan dengan koma)</label>
                            <textarea id="cashier_input" class="k-input" rows="3" placeholder="Contoh: Budi, Siti, Admin"><?php echo implode(', ', json_decode($cashiers_json) ?: []); ?></textarea>
                            <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr($cashiers_json); ?>">
                        </div>
                        <div class="k-card-foot">
                            <?php submit_button('Simpan Pengaturan', 'primary', 'submit', false); ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('.upload-btn').click(function(e){ 
                e.preventDefault(); var t=$(this).data('target'); var p=$(this).siblings('.k-preview-img');
                var f=wp.media({title:'Pilih Gambar',multiple:false}); 
                f.on('select',function(){ var u=f.state().get('selection').first().toJSON().url; $(t).val(u); p.html('<img src=\"'+u+'\">'); }); 
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
