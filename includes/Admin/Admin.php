<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.7.1';

    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_theme' );
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
        $theme = get_option( 'kresuber_pos_theme', 'retail' );
        $qris = get_option( 'kresuber_qris_image' );
        $width = get_option( 'kresuber_printer_width', '58mm' );
        $cashiers = json_decode(get_option( 'kresuber_cashiers', '[]' )) ?: [];
        
        $themes = [
            'retail'  => ['label'=>'Retail', 'color'=>'#00A78E', 'bg'=>'#F0FBF8'],
            'grosir'  => ['label'=>'Grosir', 'color'=>'#0B5FFF', 'bg'=>'#F1F6FF'],
            'sembako' => ['label'=>'Sembako','color'=>'#F59E0B', 'bg'=>'#FFF7ED'],
            'kelontong'=>['label'=>'Kelontong','color'=>'#7C4DFF', 'bg'=>'#F8F7FF'],
            'sayur'   => ['label'=>'Sayur', 'color'=>'#10B981', 'bg'=>'#F0FFF4'],
            'buah'    => ['label'=>'Buah',  'color'=>'#FF6B6B', 'bg'=>'#FFF5F5'],
        ];
        ?>
        <div class="k-wrap">
            <header class="k-header">
                <div class="k-brand">
                    <div class="k-icon-box"><span class="dashicons dashicons-store"></span></div>
                    <div>
                        <h1>Kresuber POS Pro</h1>
                        <p>Sistem Kasir UMKM Terintegrasi</p>
                    </div>
                </div>
                <div class="k-actions">
                    <span class="k-pill">v1.7.1 Stable</span>
                    <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="k-btn k-btn-primary">Buka Aplikasi POS <span class="dashicons dashicons-arrow-right-alt"></span></a>
                </div>
            </header>

            <form method="post" action="options.php" class="k-form">
                <?php settings_fields('kresuber_pos_settings'); do_settings_sections('kresuber_pos_settings'); ?>
                
                <div class="k-grid-layout">
                    <!-- Settings Column -->
                    <div class="k-main-col">
                        <div class="k-card">
                            <div class="k-card-head"><h3>Tampilan & Tema</h3></div>
                            <div class="k-card-body">
                                <div class="k-form-group">
                                    <label class="k-label">Pilih Tema Warna</label>
                                    <div class="k-theme-grid">
                                        <?php foreach($themes as $key => $p): ?>
                                        <label class="k-theme-option <?php echo $theme === $key ? 'selected' : ''; ?>">
                                            <input type="radio" name="kresuber_pos_theme" value="<?php echo $key; ?>" <?php checked($theme, $key); ?> class="hidden">
                                            <div class="k-swatch" style="background: <?php echo $p['bg']; ?>; border-color: <?php echo $p['color']; ?>;">
                                                <div class="k-swatch-accent" style="background: <?php echo $p['color']; ?>;"></div>
                                                <span style="color: <?php echo $p['color']; ?>; font-weight:bold"><?php echo $p['label']; ?></span>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="k-form-group">
                                    <label class="k-label">Logo Aplikasi</label>
                                    <div class="k-media-wrap">
                                        <div class="k-img-preview" id="logo-prev">
                                            <?php echo $logo ? '<img src="'.esc_url($logo).'">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                        </div>
                                        <div class="k-media-btns">
                                            <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                            <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_pos_logo" data-prev="#logo-prev">Upload</button>
                                            <?php if($logo): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_pos_logo">Hapus</button><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="k-card">
                            <div class="k-card-head"><h3>Hardware & Pembayaran</h3></div>
                            <div class="k-card-body">
                                <div class="k-row">
                                    <div class="k-col">
                                        <label class="k-label">QRIS (Statis)</label>
                                        <div class="k-media-wrap">
                                            <div class="k-img-preview" id="qris-prev">
                                                <?php echo $qris ? '<img src="'.esc_url($qris).'">' : '<span class="dashicons dashicons-qr"></span>'; ?>
                                            </div>
                                            <div class="k-media-btns">
                                                <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                                <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_qris_image" data-prev="#qris-prev">Upload</button>
                                                <?php if($qris): ?><button type="button" class="k-link-danger remove-btn" data-target="#kresuber_qris_image">Hapus</button><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="k-col">
                                        <label class="k-label">Lebar Printer</label>
                                        <select name="kresuber_printer_width" class="k-select">
                                            <option value="58mm" <?php selected($width, '58mm'); ?>>58mm (Mobile/Bluetooth)</option>
                                            <option value="80mm" <?php selected($width, '80mm'); ?>>80mm (Desktop)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kasir Column -->
                    <div class="k-side-col">
                        <div class="k-card">
                            <div class="k-card-head"><h3>Kasir</h3></div>
                            <div class="k-card-body">
                                <label class="k-label">Daftar Nama Kasir</label>
                                <p class="k-hint">Pisahkan dengan koma (contoh: Budi, Siti).</p>
                                <?php $c_str = implode(', ', $cashiers); 
                                      $json_cashiers = get_option('kresuber_cashiers', '[]');
                                ?>
                                <textarea id="cashier_input" class="k-textarea" rows="5"><?php echo esc_textarea($c_str); ?></textarea>
                                <input type="hidden" name="kresuber_cashiers" id="kresuber_cashiers_json" value="<?php echo esc_attr($json_cashiers); ?>">
                            </div>
                            <div class="k-card-foot">
                                <?php submit_button('Simpan', 'primary w-full', 'submit', false); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('.upload-btn').click(function(e){ 
                e.preventDefault(); var t=$(this).data('target'); var p=$($(this).data('prev'));
                var f=wp.media({title:'Pilih',multiple:false}); 
                f.on('select',function(){ var u=f.state().get('selection').first().toJSON().url; $(t).val(u); p.html('<img src=\"'+u+'\">'); location.reload(); }); 
                f.open(); 
            });
            $('.remove-btn').click(function(){ $($(this).data('target')).val(''); location.reload(); });
            $('.k-theme-option').click(function(){ $('.k-theme-option').removeClass('selected'); $(this).addClass('selected'); });
            $('form').submit(function(){
                var arr = $('#cashier_input').val().split(',').map(s=>s.trim()).filter(s=>s);
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
        });
        </script>
        <?php
    }
}