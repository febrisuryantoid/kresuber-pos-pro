<?php
namespace Kresuber\POS_Pro\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Admin {
    private $version = '1.7.1';

    public function register_settings() {
        register_setting( 'kresuber_pos_settings', 'kresuber_pos_logo' );
        register_setting( 'kresuber_pos_settings', 'kresuber_qris_image' );
        register_setting( 'kresuber_pos_settings', 'kresuber_printer_width' );
        register_setting( 'kresuber_pos_settings', 'kresuber_cashiers' );
        register_setting( 'kresuber_pos_settings', 'kresuber_business_type' );
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
        $theme = get_option( 'kresuber_business_type', 'retail' );

        $themes = [
            'retail' => ['label' => 'Retail (Teal)', 'color' => '#00A78E', 'bg' => '#F0FBF8'],
            'grosir' => ['label' => 'Grosir (Indigo)', 'color' => '#0B5FFF', 'bg' => '#F1F6FF'],
            'sembako' => ['label' => 'Sembako (Amber)', 'color' => '#F59E0B', 'bg' => '#FFF7ED'],
            'kelontong'=>['label' => 'Kelontong (Purple)','color'=> '#7C4DFF', 'bg' => '#F8F7FF'],
            'sayur' => ['label' => 'Sayur (Green)', 'color' => '#10B981', 'bg' => '#F0FFF4'],
            'buah' => ['label' => 'Buah (Salmon)', 'color' => '#FF6B6B', 'bg' => '#FFF5F5'],
        ];

        // Stats Dummy (Real implementation needs separate query)
        $order_count = wc_get_order_count();
        ?>
        <div class="kresuber-wrap">
            <!-- Top Bar -->
            <div class="k-topbar">
                <div class="k-brand">
                    <div class="k-logo"><span class="dashicons dashicons-store"></span></div>
                    <div class="k-title">
                        <h1>Kresuber POS Pro</h1>
                        <span class="k-badge">v1.7.1 Active</span>
                    </div>
                </div>
                <div class="k-actions">
                    <a href="<?php echo home_url('/pos'); ?>" target="_blank" class="k-btn k-btn-primary">
                        <span class="dashicons dashicons-desktop"></span> Buka Aplikasi Kasir
                    </a>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="k-stats">
                <div class="k-stat-card">
                    <div class="k-stat-icon blue"><span class="dashicons dashicons-cart"></span></div>
                    <div class="k-stat-info">
                        <h3><?php echo $order_count; ?></h3>
                        <p>Total Pesanan</p>
                    </div>
                </div>
                <div class="k-stat-card">
                    <div class="k-stat-icon green"><span class="dashicons dashicons-money"></span></div>
                    <div class="k-stat-info">
                        <h3>Aktif</h3>
                        <p>Status Sistem</p>
                    </div>
                </div>
                 <div class="k-stat-card">
                    <div class="k-stat-icon purple"><span class="dashicons dashicons-admin-users"></span></div>
                    <div class="k-stat-info">
                        <h3><?php echo count($cashiers); ?></h3>
                        <p>Kasir Terdaftar</p>
                    </div>
                </div>
            </div>

            <?php settings_errors(); ?>

            <form method="post" action="options.php" class="k-main-form">
                <?php settings_fields('kresuber_pos_settings'); do_settings_sections('kresuber_pos_settings'); ?>
                
                <div class="k-content-grid">
                    
                    <!-- Left: Main Settings -->
                    <div class="k-col-main">
                        <div class="k-card">
                            <div class="k-card-header">
                                <h3>Tampilan Toko</h3>
                                <p>Sesuaikan identitas visual aplikasi POS Anda.</p>
                            </div>
                            <div class="k-card-body">
                                <div class="k-form-group">
                                    <label>Tema Warna</label>
                                    <div class="k-theme-grid">
                                        <?php foreach($themes as $key => $val): ?>
                                        <label class="k-theme-option <?php echo $theme === $key ? 'selected' : ''; ?>">
                                            <input type="radio" name="kresuber_business_type" value="<?php echo $key; ?>" <?php checked($theme, $key); ?>>
                                            <span class="k-dot" style="background: <?php echo $val['color']; ?>"></span>
                                            <span class="k-name"><?php echo $val['label']; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="k-form-group">
                                    <label>Logo Aplikasi</label>
                                    <div class="k-upload-wrapper">
                                        <div class="k-img-preview" id="logo-preview">
                                            <?php echo $logo ? '<img src="'.esc_url($logo).'">' : '<span class="dashicons dashicons-format-image"></span>'; ?>
                                        </div>
                                        <div class="k-upload-actions">
                                            <input type="hidden" name="kresuber_pos_logo" id="kresuber_pos_logo" value="<?php echo esc_attr($logo); ?>">
                                            <button type="button" class="k-btn k-btn-outline upload-btn" data-target="#kresuber_pos_logo">Pilih Logo</button>
                                            <?php if($logo): ?><button type="button" class="k-link-remove remove-btn" data-target="#kresuber_pos_logo">Hapus</button><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="k-card">
                            <div class="k-card-header">
                                <h3>Pembayaran & Hardware</h3>
                            </div>
                            <div class="k-card-body">
                                <div class="k-row">
                                    <div class="k-col">
                                        <label>QRIS (Statis)</label>
                                        <div class="k-upload-wrapper small">
                                            <div class="k-img-preview" id="qris-preview">
                                                <?php echo $qris ? '<img src="'.esc_url($qris).'">' : '<span class="dashicons dashicons-qr"></span>'; ?>
                                            </div>
                                            <div class="k-upload-actions">
                                                <input type="hidden" name="kresuber_qris_image" id="kresuber_qris_image" value="<?php echo esc_attr($qris); ?>">
                                                <button type="button" class="k-btn k-btn-sm k-btn-outline upload-btn" data-target="#kresuber_qris_image">Upload</button>
                                                <?php if($qris): ?><button type="button" class="k-link-remove remove-btn" data-target="#kresuber_qris_image">Hapus</button><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="k-col">
                                        <label>Lebar Printer</label>
                                        <select name="kresuber_printer_width" class="k-input">
                                            <option value="58mm" <?php selected($width, '58mm'); ?>>58mm (Thermal Kecil)</option>
                                            <option value="80mm" <?php selected($width, '80mm'); ?>>80mm (Desktop)</option>
                                        </select>
                                        <p class="k-hint">Sesuaikan dengan kertas printer thermal Anda.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Sidebar -->
                    <div class="k-col-side">
                        <div class="k-card">
                            <div class="k-card-header">
                                <h3>Daftar Kasir</h3>
                            </div>
                            <div class="k-card-body">
                                <p class="k-hint">Masukkan nama kasir yang bertugas (pisahkan dengan koma).</p>
                                <?php $cashier_str = implode(', ', $cashiers); ?>
                                <textarea id="cashier_input" class="k-textarea" rows="6" placeholder="Budi, Siti, Admin"><?php echo esc_textarea($cashier_str); ?></textarea>
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
                e.preventDefault(); var t=$(this).data('target'); var p=$(this).parent().siblings('.k-img-preview');
                var f=wp.media({title:'Pilih Gambar',multiple:false}); 
                f.on('select',function(){ var u=f.state().get('selection').first().toJSON().url; $(t).val(u); p.html('<img src=\"'+u+'\">'); location.reload(); }); 
                f.open(); 
            });
            $('.remove-btn').click(function(){ $($(this).data('target')).val(''); location.reload(); });
            $('.k-theme-option').click(function(){ $('.k-theme-option').removeClass('selected'); $(this).addClass('selected'); });
            $('form').submit(function(){
                var arr = $('#cashier_input').val().split(',').map(s => s.trim()).filter(s => s);
                $('#kresuber_cashiers_json').val(JSON.stringify(arr));
            });
        });
        </script>
        <?php
    }
}
